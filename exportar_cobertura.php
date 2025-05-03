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

// Arrays para armazenar os dados dos anúncios
$anuncios_full = [];
$tipo_ordenacao = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'dias';

// Função para buscar os anúncios Full do usuário
function buscarAnunciosFull($access_token, $user_id) {
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
                            if (isset($item['body']) && isset($item['body']['shipping']) && 
                                isset($item['body']['shipping']['logistic_type']) && 
                                $item['body']['shipping']['logistic_type'] === 'fulfillment') {
                                
                                $anuncio = $item['body'];
                                
                                // Buscar as vendas dos últimos 60 dias
                                $vendas_60_dias = buscarVendasAnuncio($access_token, $anuncio['id'], 60, $user_id);
                                
                                $vendas_30_dias = array_filter($vendas_60_dias, function($venda) {
                                    return strtotime($venda['date_created']) >= strtotime('-30 days');
                                });
                                
                                // Calculando dias de cobertura de estoque
                                $vendas_30_dias_count = count($vendas_30_dias);
                                $media_vendas_diarias = $vendas_30_dias_count > 0 ? $vendas_30_dias_count / 30 : 0;
                                $dias_cobertura = $media_vendas_diarias > 0 ? $anuncio['available_quantity'] / $media_vendas_diarias : 'INF';
                                
                                $anuncios[] = [
                                    'id' => $anuncio['id'],
                                    'titulo' => $anuncio['title'],
                                    'thumbnail' => $anuncio['thumbnail'],
                                    'sku' => $anuncio['seller_custom_field'] ?? '-',
                                    'vendas_60_dias' => count($vendas_60_dias),
                                    'vendas_30_dias' => $vendas_30_dias_count,
                                    'estoque' => $anuncio['available_quantity'],
                                    'dias_cobertura' => $dias_cobertura,
                                    'preco' => $anuncio['price'],
                                    'link' => $anuncio['permalink'],
                                    'status' => $anuncio['status']
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

// Função para buscar as vendas de um anúncio específico
function buscarVendasAnuncio($access_token, $item_id, $dias, $seller_id) {
    $vendas = [];
    $offset = 0;
    $limit = 50;
    
    // Formatar datas no formato ISO 8601 que a API do ML espera
    $data_inicio = date('Y-m-d\TH:i:s.000-03:00', strtotime('-' . $dias . ' days'));
    $data_atual = date('Y-m-d\TH:i:s.000-03:00');
    
    try {
        do {
            // Construir URL usando o ID do vendedor e formato de data correto
            $url = "https://api.mercadolibre.com/orders/search?" . 
                   "seller=" . $seller_id . 
                   "&item=" . urlencode($item_id) . 
                   "&order.date_created.from=" . urlencode($data_inicio) . 
                   "&order.date_created.to=" . urlencode($data_atual) . 
                   "&limit=" . $limit . 
                   "&offset=" . $offset;
            
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
                
                if (isset($data['results']) && is_array($data['results'])) {
                    foreach ($data['results'] as $order) {
                        // Considerar apenas pedidos não cancelados
                        if ($order['status'] !== 'cancelled') {
                            // Adicionar a venda à lista
                            $vendas[] = [
                                'date_created' => $order['date_created'],
                                'id' => $order['id'],
                                'status' => $order['status']
                            ];
                        }
                    }
                }
                
                // Verificar se há mais resultados a serem paginados
                $total = $data['paging']['total'] ?? 0;
                $offset += $limit;
                
            } else {
                break; // Erro na API
            }
            
        } while ($offset < $total);
        
    } catch (Exception $e) {
        // Silenciar erro
    }
    
    return $vendas;
}

// Executar a busca dos anúncios Full
if (!empty($access_token) && !empty($ml_user_id)) {
    $anuncios_full = buscarAnunciosFull($access_token, $ml_user_id);
    
    // Verificar se houve erro
    if (isset($anuncios_full['error'])) {
        header("Content-Type: text/plain");
        echo $anuncios_full['error'];
        exit;
    }
    
    // Ordenar os anúncios
    if ($tipo_ordenacao === 'dias') {
        // Ordenar por dias de cobertura (crescente)
        usort($anuncios_full, function($a, $b) {
            if ($a['dias_cobertura'] === 'INF' && $b['dias_cobertura'] === 'INF') return 0;
            if ($a['dias_cobertura'] === 'INF') return 1;
            if ($b['dias_cobertura'] === 'INF') return -1;
            return $a['dias_cobertura'] - $b['dias_cobertura'];
        });
    } else {
        // Ordenar por valor de venda (decrescente)
        usort($anuncios_full, function($a, $b) {
            return $b['vendas_30_dias'] - $a['vendas_30_dias'];
        });
    }
}

// Inicializar a variável explicitamente para garantir que não seja null
if (!isset($anuncios_full) || !is_array($anuncios_full)) {
    $anuncios_full = [];
}

// Definir cabeçalhos para download do Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="relatorio_cobertura_estoque_full_' . date('d-m-Y') . '.xls"');
header('Cache-Control: max-age=0');

// Gerar conteúdo Excel (HTML formatado)
echo '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Relatório de Cobertura de Estoque Full - ' . date('d/m/Y') . '</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background-color: #f0f0f0; }
        .vermelho { background-color: #ffcccc; }
        .amarelo { background-color: #ffffcc; }
        .verde { background-color: #ccffcc; }
    </style>
</head>
<body>
    <h2>Relatório de Cobertura de Estoque Full - ' . date('d/m/Y') . '</h2>
    <table>
        <thead>
            <tr>
                <th>Identificador/SKU</th>
                <th>Vendas 60 dias</th>
                <th>Vendas 30 dias</th>
                <th>Estoque Full</th>
                <th>Dias Cobertura</th>
                <th>Valor Vendas</th>
            </tr>
        </thead>
        <tbody>';

// Verificar se há anúncios para exibir
if (!empty($anuncios_full)) {
    foreach ($anuncios_full as $anuncio) {
        $classe = '';
        if ($anuncio['dias_cobertura'] === 'INF' || $anuncio['dias_cobertura'] > 60) {
            $classe = 'vermelho';
        } elseif ($anuncio['dias_cobertura'] > 30) {
            $classe = 'amarelo';
        } else {
            $classe = 'verde';
        }
        
        echo '<tr>
            <td>' . htmlspecialchars($anuncio['id']) . '<br>' . htmlspecialchars($anuncio['sku']) . '</td>
            <td>' . $anuncio['vendas_60_dias'] . '</td>
            <td>' . $anuncio['vendas_30_dias'] . '</td>
            <td>' . $anuncio['estoque'] . '</td>
            <td class="' . $classe . '">' . ($anuncio['dias_cobertura'] === 'INF' ? 'INF' : round($anuncio['dias_cobertura'])) . '</td>
            <td>R$ ' . number_format($anuncio['preco'] * $anuncio['vendas_30_dias'], 2, ',', '.') . '</td>
        </tr>';
    }
} else {
    // Se não houver dados, mostrar mensagem
    echo '<tr><td colspan="6" style="text-align:center">Nenhum anúncio Full encontrado</td></tr>';
}

echo '
        </tbody>
    </table>
</body>
</html>';
?>
