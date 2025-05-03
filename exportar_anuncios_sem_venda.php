<?php
// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Content-Type: text/plain");
    echo "Acesso negado. Faça login primeiro.";
    exit;
}

// Dados básicos
$base_url = 'http://www.annemacedo.com.br/novo2';
$usuario_id = $_SESSION['user_id'];
$usuario_nome = $_SESSION['user_name'] ?? 'Vendedor';

// Conectar ao banco de dados
$db_host = 'mysql.annemacedo.com.br';
$db_name = 'annemacedo02';
$db_user = 'annemacedo02';
$db_pass = 'Vingador13Anne';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    header("Content-Type: text/plain");
    echo "Erro na conexão com o banco de dados: " . $e->getMessage();
    exit;
}

// Buscar token válido do Mercado Livre
$ml_token = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM mercadolivre_tokens 
        WHERE usuario_id = ? AND revogado = 0 AND data_expiracao > NOW()
        ORDER BY data_expiracao DESC 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $ml_token = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header("Content-Type: text/plain");
    echo "Erro ao buscar token: " . $e->getMessage();
    exit;
}

// Verificar se tem token válido
$access_token = '';
if (!empty($ml_token) && !empty($ml_token['access_token'])) {
    $access_token = $ml_token['access_token'];
} else {
    header("Content-Type: text/plain");
    echo "Você não está conectado ao Mercado Livre ou seu token expirou. Por favor, faça a autenticação novamente.";
    exit;
}

// Verificar qual usuário está usando o token
$ml_user_id = '';
if (!empty($access_token)) {
    $ch = curl_init('https://api.mercadolibre.com/users/me');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($status == 200) {
        $user_data = json_decode($response, true);
        $ml_user_id = $user_data['id'] ?? '';
    }
    
    if (empty($ml_user_id)) {
        header("Content-Type: text/plain");
        echo "Não foi possível identificar seu usuário do Mercado Livre. Por favor, reconecte sua conta.";
        exit;
    }
}

// Período para filtrar anúncios sem vendas (em dias)
$periodo_filtro = isset($_GET['periodo']) && is_numeric($_GET['periodo']) ? (int)$_GET['periodo'] : 120;

// Função para buscar os anúncios ativos do usuário
function buscarAnunciosAtivos($access_token, $user_id) {
    $anuncios = [];
    $offset = 0;
    $limit = 50;
    $total = 0;
    
    try {
        // Buscar IDs dos anúncios do usuário
        do {
            $url = "https://api.mercadolibre.com/users/{$user_id}/items/search?limit={$limit}&offset={$offset}";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token
            ]);
            
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($status != 200) {
                return ['error' => 'Erro ao buscar anúncios: HTTP ' . $status];
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['results']) && is_array($data['results'])) {
                // Buscar informações detalhadas dos anúncios em lotes de 20
                $item_chunks = array_chunk($data['results'], 20);
                
                foreach ($item_chunks as $chunk) {
                    $ids = implode(',', $chunk);
                    $items_url = "https://api.mercadolibre.com/items?ids=" . $ids;
                    
                    $ch_items = curl_init($items_url);
                    curl_setopt($ch_items, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch_items, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $access_token
                    ]);
                    
                    $items_response = curl_exec($ch_items);
                    $items_status = curl_getinfo($ch_items, CURLINFO_HTTP_CODE);
                    curl_close($ch_items);
                    
                    if ($items_status == 200) {
                        $items_data = json_decode($items_response, true);
                        
                        foreach ($items_data as $item) {
                            // Verificar se o item tem body e está ativo
                            if (isset($item['body']) && 
                                isset($item['body']['status']) && 
                                $item['body']['status'] === 'active') {
                                
                                $anuncio = $item['body'];
                                
                                $anuncios[] = [
                                    'id' => $anuncio['id'],
                                    'titulo' => $anuncio['title'],
                                    'preco' => $anuncio['price'],
                                    'estoque' => $anuncio['available_quantity'],
                                    'data_criacao' => $anuncio['date_created'],
                                    'ultima_venda' => null, // Será preenchido depois
                                    'dias_sem_venda' => null // Será preenchido depois
                                ];
                            }
                        }
                    }
                }
            }
            
            $total = $data['paging']['total'] ?? 0;
            $offset += $limit;
            
        } while ($offset < $total);
        
        return $anuncios;
        
    } catch (Exception $e) {
        return ['error' => 'Erro ao processar anúncios: ' . $e->getMessage()];
    }
}

// Função para verificar a última venda de cada anúncio
function verificarUltimasVendas($access_token, &$anuncios, $user_id, $periodo_dias) {
    try {
        $hoje = new DateTime();
        $data_limite = (new DateTime())->sub(new DateInterval("P{$periodo_dias}D"));
        $data_limite_str = $data_limite->format('Y-m-d\TH:i:s.000-03:00');
        
        // Para cada anúncio, verificar se teve vendas
        foreach ($anuncios as &$anuncio) {
            $offset = 0;
            $limit = 1; // Precisamos apenas da última venda
            $teve_vendas = false;
            
            $url = "https://api.mercadolibre.com/orders/search?" . 
                   "seller={$user_id}" . 
                   "&item={$anuncio['id']}" . 
                   "&order.date_created.from={$data_limite_str}" . 
                   "&sort=date_desc" . // Ordenar por mais recente
                   "&limit={$limit}";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token
            ]);
            
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($status == 200) {
                $data = json_decode($response, true);
                
                if (isset($data['results']) && count($data['results']) > 0) {
                    $ultima_venda = $data['results'][0];
                    
                    // Verificar se a venda não foi cancelada
                    if ($ultima_venda['status'] !== 'cancelled') {
                        $teve_vendas = true;
                        $data_ultima_venda = new DateTime($ultima_venda['date_created']);
                        $dias_desde_ultima_venda = $hoje->diff($data_ultima_venda)->days;
                        
                        $anuncio['ultima_venda'] = $ultima_venda['date_created'];
                        $anuncio['dias_sem_venda'] = $dias_desde_ultima_venda;
                    }
                }
            }
            
            // Se não teve vendas no período, calcular dias desde a criação do anúncio
            if (!$teve_vendas) {
                $data_criacao = new DateTime($anuncio['data_criacao']);
                $dias_desde_criacao = $hoje->diff($data_criacao)->days;
                
                $anuncio['ultima_venda'] = null;
                $anuncio['dias_sem_venda'] = $dias_desde_criacao;
            }
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Função para filtrar anúncios sem vendas
function filtrarAnunciosSemVendas($anuncios) {
    return array_filter($anuncios, function($anuncio) {
        return $anuncio['ultima_venda'] === null;
    });
}

// Função para calcular potencial de vendas (preço * estoque)
function calcularPotencialVendas($preco, $estoque) {
    return $preco * $estoque;
}

// Executar a busca
$anuncios = buscarAnunciosAtivos($access_token, $ml_user_id);

// Verificar se houve erro
if (isset($anuncios['error'])) {
    header("Content-Type: text/plain");
    echo $anuncios['error'];
    exit;
}

// Verificar últimas vendas
verificarUltimasVendas($access_token, $anuncios, $ml_user_id, $periodo_filtro);

// Filtrar anúncios sem vendas
$anuncios_sem_venda = filtrarAnunciosSemVendas($anuncios);

// Ordenar por estoque (decrescente)
usort($anuncios_sem_venda, function($a, $b) {
    return $b['estoque'] - $a['estoque'];
});

// Inicializar a variável explicitamente para garantir que não seja null
if (!isset($anuncios_sem_venda) || !is_array($anuncios_sem_venda)) {
    $anuncios_sem_venda = [];
}

// Definir cabeçalhos para download do Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="anuncios_sem_venda_' . date('d-m-Y') . '.xls"');
header('Cache-Control: max-age=0');

// Gerar conteúdo Excel (HTML formatado)
echo '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Anúncios sem Venda - ' . date('d/m/Y') . '</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background-color: #f0f0f0; }
    </style>
</head>
<body>
    <h2>Anúncios sem Venda - ' . date('d/m/Y') . '</h2>
    <p>Filtrando anúncios sem venda nos últimos ' . $periodo_filtro . ' dias</p>
    <table>
        <thead>
            <tr>
                <th>Identificador</th>
                <th>Título</th>
                <th>Preço</th>
                <th>Estoque</th>
                <th>Dias sem Venda</th>
                <th>Potencial de Vendas</th>
            </tr>
        </thead>
        <tbody>';

// Verificar se há anúncios para exibir
if (!empty($anuncios_sem_venda)) {
    foreach ($anuncios_sem_venda as $anuncio) {
        echo '<tr>
            <td>' . htmlspecialchars($anuncio['id']) . '</td>
            <td>' . htmlspecialchars($anuncio['titulo']) . '</td>
            <td>R$ ' . number_format($anuncio['preco'], 2, ',', '.') . '</td>
            <td>' . $anuncio['estoque'] . '</td>
            <td>' . $anuncio['dias_sem_venda'] . '</td>
            <td>R$ ' . number_format(($anuncio['preco'] * $anuncio['estoque']), 2, ',', '.') . '</td>
        </tr>';
    }
} else {
    // Se não houver dados, mostrar mensagem
    echo '<tr><td colspan="6" style="text-align:center">Nenhum anúncio sem venda encontrado</td></tr>';
}

echo '
        </tbody>
    </table>
</body>
</html>';
?>
