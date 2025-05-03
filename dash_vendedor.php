<?php
// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Location: login.php");
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
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Inicializar variáveis
$mensagem = '';
$tipo_mensagem = '';

// Configurar filtros
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-30 days'));
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : '30';

// Ajustar datas com base no período selecionado
if ($periodo !== 'custom') {
    switch ($periodo) {
        case '7':
            $data_inicio = date('Y-m-d', strtotime('-7 days'));
            break;
        case '30':
            $data_inicio = date('Y-m-d', strtotime('-30 days'));
            break;
        case '90':
            $data_inicio = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'month':
            $data_inicio = date('Y-m-01');
            break;
        case 'last_month':
            $data_inicio = date('Y-m-01', strtotime('-1 month'));
            $data_fim = date('Y-m-t', strtotime('-1 month'));
            break;
        case 'year':
            $data_inicio = date('Y-01-01');
            break;
    }
}

// Formatar datas para exibição e para API
$data_inicio_display = date('d/m/Y', strtotime($data_inicio));
$data_fim_display = date('d/m/Y', strtotime($data_fim));
$data_inicio_iso = date('c', strtotime($data_inicio . ' 00:00:00'));
$data_fim_iso = date('c', strtotime($data_fim . ' 23:59:59'));

// MODIFICAÇÃO: Inicializar variáveis para agregação de dados por tipo de anúncio (consolidando Premium e Premium Pro)
$vendas_por_tipo_anuncio = [
    'gold' => ['count' => 0, 'valor' => 0, 'lucro' => 0],
    'premium' => ['count' => 0, 'valor' => 0, 'lucro' => 0], // Consolidado para Premium e Premium Pro
    'free' => ['count' => 0, 'valor' => 0, 'lucro' => 0],
    'outros' => ['count' => 0, 'valor' => 0, 'lucro' => 0]
];

// Variáveis para evolução de vendas
$datas_periodo = [];
$faturamento_diario = [];
$lucro_diario = [];

// Gerar array de datas para o período
$data_atual = new DateTime($data_inicio);
$data_fim_obj = new DateTime($data_fim);
while ($data_atual <= $data_fim_obj) {
    $data_str = $data_atual->format('Y-m-d');
    $datas_periodo[] = $data_str;
    $faturamento_diario[$data_str] = 0;
    $lucro_diario[$data_str] = 0;
    $data_atual->modify('+1 day');
}

// Verificar status de conexão do Mercado Livre
$ml_conectado = false;
$ml_nickname = '';
$ml_user_id = '';
$access_token = '';

try {
    $stmt_ml = $pdo->prepare("
        SELECT t.id, t.access_token, u.ml_nickname, u.ml_user_id
        FROM mercadolivre_tokens t
        JOIN mercadolivre_usuarios u ON t.usuario_id = u.usuario_id
        WHERE t.usuario_id = ? AND t.revogado = 0 AND t.data_expiracao > NOW()
        ORDER BY t.data_expiracao DESC
        LIMIT 1
    ");
    $stmt_ml->execute([$usuario_id]);
    $ml_token = $stmt_ml->fetch();
    
    if ($ml_token) {
        $ml_conectado = true;
        $ml_nickname = $ml_token['ml_nickname'];
        $ml_user_id = $ml_token['ml_user_id'];
        $access_token = $ml_token['access_token'];
    }
} catch (PDOException $e) {
    // Silenciar erro
}

// Obter ID do vendedor a partir do usuário
$sql_vendedor = "SELECT id FROM vendedores WHERE usuario_id = ?";
$stmt_vendedor = $pdo->prepare($sql_vendedor);
$stmt_vendedor->execute([$usuario_id]);
$vendedor = $stmt_vendedor->fetch();
$vendedor_id = $vendedor['id'] ?? 0;

// Inicializar variáveis para dados
$metricas_vendas = [
    'total_vendas' => 0,
    'valor_total' => 0,
    'lucro_total' => 0,
    'margem_media' => 0,
    'vendas_margem_alta' => 0,
    'vendas_margem_media' => 0,
    'vendas_margem_baixa' => 0
];

$metricas_anuncios = [
    'total_anuncios' => 0,
    'anuncios_ativos' => 0,
    'anuncios_pausados' => 0,
    'anuncios_fechados' => 0,
    'anuncios_vinculados' => 0,
    'anuncios_classicos' => 0,
    'anuncios_premium' => 0 // Consolidado para Premium e Premium Pro
];

// Top produtos vendidos - AJUSTADO PARA INCLUIR DADOS DA API
$top_produtos = [];
$produtos_ml = [];

// Passo 1: Buscar vendas do banco de dados local primeiro
if ($vendedor_id) {
    $sql_produtos = "SELECT 
                        p.id,
                        p.nome,
                        p.sku,
                        p.custo,
                        COUNT(v.id) as num_vendas,
                        SUM(v.valor_venda) as valor_total,
                        SUM(v.lucro) as lucro_total,
                        AVG(v.margem_lucro) as margem_media
                    FROM vendas v
                    JOIN produtos p ON v.produto = p.nome
                    WHERE v.vendedor_id = ? 
                    AND v.data_venda BETWEEN ? AND ?
                    GROUP BY p.id";
    
    $stmt_produtos = $pdo->prepare($sql_produtos);
    $stmt_produtos->execute([$vendedor_id, $data_inicio, $data_fim]);
    $produtos_local = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);
    
    // Inicializar array combinado com produtos do banco local
    $produtos_combinados = [];
    foreach ($produtos_local as $produto) {
        $produtos_combinados[$produto['id']] = [
            'id' => $produto['id'],
            'nome' => $produto['nome'],
            'sku' => $produto['sku'],
            'custo' => $produto['custo'],
            'num_vendas' => $produto['num_vendas'],
            'valor_total' => $produto['valor_total'],
            'lucro_total' => $produto['lucro_total'],
            'margem_media' => $produto['margem_media'],
            'origem' => ['local']
        ];
    }
    
    // Passo 2: Buscar e adicionar dados das vendas do ML
    if ($ml_conectado && !empty($ml_orders)) {
        // Array para mapear ml_item_id para produto_id
        $ml_item_to_produto = [];
        
        // Primeiro, vamos criar um mapeamento de item_id para produto_id para melhorar a performance
        $sql_mapeamento = "SELECT ml_item_id, produto_id FROM anuncios_ml 
                          WHERE produto_id IS NOT NULL AND usuario_id = ?";
        $stmt_mapeamento = $pdo->prepare($sql_mapeamento);
        $stmt_mapeamento->execute([$usuario_id]);
        while ($row = $stmt_mapeamento->fetch(PDO::FETCH_ASSOC)) {
            $ml_item_to_produto[$row['ml_item_id']] = $row['produto_id'];
        }
        
        // Buscar informações básicas de todos os produtos
        $sql_todos_produtos = "SELECT id, nome, sku, custo FROM produtos WHERE usuario_id = ?";
        $stmt_todos_produtos = $pdo->prepare($sql_todos_produtos);
        $stmt_todos_produtos->execute([$usuario_id]);
        $produtos_info = [];
        while ($row = $stmt_todos_produtos->fetch(PDO::FETCH_ASSOC)) {
            $produtos_info[$row['id']] = $row;
        }
        
        // Processar as ordens do ML para agrupar por produto
        foreach ($ml_orders as $order) {
            // Pular ordens canceladas
            if (isset($order['status']) && $order['status'] === 'cancelled') {
                continue;
            }
            
            // Verificar se há itens nas ordens
            if (!isset($order['order_items']) || empty($order['order_items'])) {
                continue;
            }
            
            foreach ($order['order_items'] as $item) {
                $item_id = isset($item['item']['id']) ? $item['item']['id'] : '';
                if (empty($item_id) || !isset($ml_item_to_produto[$item_id])) {
                    continue;
                }
                
                $produto_id = $ml_item_to_produto[$item_id];
                
                // Se não temos info deste produto, pular
                if (!isset($produtos_info[$produto_id])) {
                    continue;
                }
                
                // Dados básicos do item
                $quantidade = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                $preco_unitario = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
                $valor_total = $quantidade * $preco_unitario;
                $produto_info = $produtos_info[$produto_id];
                $custo = (float)$produto_info['custo'];
                
                // Calcular taxa do ML (simplificado)
                $taxa_ml_percent = 12; // taxa padrão
                $taxa_ml = ($taxa_ml_percent / 100) * $preco_unitario;
                $lucro = ($preco_unitario - $custo - $taxa_ml) * $quantidade;
                $margem = ($preco_unitario > 0) ? (($preco_unitario - $custo - $taxa_ml) / $preco_unitario) * 100 : 0;
                
                // Atualizar ou criar entrada para este produto
                if (!isset($produtos_ml[$produto_id])) {
                    $produtos_ml[$produto_id] = [
                        'id' => $produto_id,
                        'nome' => $produto_info['nome'],
                        'sku' => $produto_info['sku'],
                        'custo' => $custo,
                        'num_vendas' => 0,
                        'valor_total' => 0,
                        'lucro_total' => 0,
                        'margens' => [] // para calcular média depois
                    ];
                }
                
                // Adicionar esta venda aos totais
                $produtos_ml[$produto_id]['num_vendas'] += $quantidade;
                $produtos_ml[$produto_id]['valor_total'] += $valor_total;
                $produtos_ml[$produto_id]['lucro_total'] += $lucro;
                $produtos_ml[$produto_id]['margens'][] = ['margem' => $margem, 'valor' => $valor_total];
            }
        }
        
        // Calcular margem média ponderada pelo valor da venda
        foreach ($produtos_ml as $id => $produto) {
            if (!empty($produto['margens'])) {
                $total_ponderado = 0;
                $soma_valores = 0;
                
                foreach ($produto['margens'] as $item) {
                    $total_ponderado += $item['margem'] * $item['valor'];
                    $soma_valores += $item['valor'];
                }
                
                $produtos_ml[$id]['margem_media'] = $soma_valores > 0 ? $total_ponderado / $soma_valores : 0;
                unset($produtos_ml[$id]['margens']); // remover dados temporários
            } else {
                $produtos_ml[$id]['margem_media'] = 0;
            }
        }
        
        // Combinar dados do ML com os dados locais
        foreach ($produtos_ml as $id => $produto_ml) {
            if (isset($produtos_combinados[$id])) {
                // Produto existe em ambas as fontes
                $produtos_combinados[$id]['num_vendas'] += $produto_ml['num_vendas'];
                $produtos_combinados[$id]['valor_total'] += $produto_ml['valor_total'];
                $produtos_combinados[$id]['lucro_total'] += $produto_ml['lucro_total'];
                
                // Recalcular margem média ponderada
                $valor_local = $produtos_combinados[$id]['valor_total'] - $produto_ml['valor_total'];
                $margem_local = $produtos_combinados[$id]['margem_media'] * $valor_local;
                $margem_ml = $produto_ml['margem_media'] * $produto_ml['valor_total'];
                $valor_total = $valor_local + $produto_ml['valor_total'];
                
                if ($valor_total > 0) {
                    $produtos_combinados[$id]['margem_media'] = ($margem_local + $margem_ml) / $valor_total;
                }
                
                $produtos_combinados[$id]['origem'][] = 'mercadolivre';
            } else {
                // Produto só existe no ML
                $produto_ml['origem'] = ['mercadolivre'];
                $produtos_combinados[$id] = $produto_ml;
            }
        }
    }
    
    // Converter para array simples e ordenar
    $top_produtos = array_values($produtos_combinados);
    usort($top_produtos, function($a, $b) {
        return $b['valor_total'] <=> $a['valor_total']; // PHP 7+ spaceship operator
    });
    
    // Limitar a 5 produtos
    $top_produtos = array_slice($top_produtos, 0, 5);
}



$produtos_sem_vendas = [];
$vendas_recentes = [];
$vendas_ml_recentes = [];

try {
    // Buscar métricas básicas das vendas do banco local
    if ($vendedor_id) {
        // Métricas de vendas locais
        $sql_vendas = "SELECT 
                        COUNT(*) as total_vendas,
                        SUM(valor_venda) as valor_total,
                        SUM(lucro) as lucro_total,
                        AVG(margem_lucro) as margem_media,
                        SUM(CASE WHEN margem_lucro >= 20 THEN 1 ELSE 0 END) as vendas_margem_alta,
                        SUM(CASE WHEN margem_lucro < 20 AND margem_lucro >= 10 THEN 1 ELSE 0 END) as vendas_margem_media,
                        SUM(CASE WHEN margem_lucro < 10 THEN 1 ELSE 0 END) as vendas_margem_baixa
                    FROM vendas 
                    WHERE vendedor_id = ? 
                    AND data_venda BETWEEN ? AND ?";
        
        $stmt_vendas = $pdo->prepare($sql_vendas);
        $stmt_vendas->execute([$vendedor_id, $data_inicio, $data_fim]);
        $metricas_vendas = $stmt_vendas->fetch();
        
        // Vendas recentes locais
        $sql_recentes = "SELECT 
                            v.id,
                            v.produto,
                            v.data_venda,
                            v.valor_venda,
                            v.lucro,
                            v.margem_lucro,
                            'local' as origem
                        FROM vendas v
                        WHERE v.vendedor_id = ?
                        ORDER BY v.data_venda DESC
                        LIMIT 5";
        
        $stmt_recentes = $pdo->prepare($sql_recentes);
        $stmt_recentes->execute([$vendedor_id]);
        $vendas_recentes = $stmt_recentes->fetchAll();
    }
    
    // Métricas de anúncios
    $sql_anuncios = "SELECT 
                        COUNT(*) as total_anuncios,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as anuncios_ativos,
                        SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as anuncios_pausados,
                        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as anuncios_fechados,
                        SUM(CASE WHEN produto_id IS NOT NULL THEN 1 ELSE 0 END) as anuncios_vinculados,
                        SUM(CASE WHEN tipo_anuncio = 'gold' THEN 1 ELSE 0 END) as anuncios_classicos,
                        SUM(CASE WHEN tipo_anuncio IN ('gold_special', 'gold_pro') THEN 1 ELSE 0 END) as anuncios_premium
                    FROM anuncios_ml
                    WHERE usuario_id = ?";
    
    $stmt_anuncios = $pdo->prepare($sql_anuncios);
    $stmt_anuncios->execute([$usuario_id]);
    $metricas_anuncios = $stmt_anuncios->fetch();
    
    $anuncios_por_tipo = [
        'classico' => $metricas_anuncios['anuncios_classicos'] ?? 0,
        'premium' => $metricas_anuncios['anuncios_premium'] ?? 0
    ];
    
    // Buscar vendas do Mercado Livre via API
    $vendas_ml_recentes = [];
    
    if ($ml_conectado) {
        // Buscar TODAS as ordens no período, não apenas as 5 mais recentes
        $offset = 0;
        $limit = 50;
        $total_ordens = 1; // Inicializar para entrar no loop
        $ml_orders = [];
        
        while ($offset < $total_ordens) {
            $url = "https://api.mercadolibre.com/orders/search?seller={$ml_user_id}&sort=date_desc&limit={$limit}&offset={$offset}&order.date_created.from={$data_inicio_iso}&order.date_created.to={$data_fim_iso}";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token
            ]);
            
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($status == 200) {
                $orders_data = json_decode($response, true);
                $total_ordens = $orders_data['paging']['total'] ?? 0;
                $current_results = $orders_data['results'] ?? [];
                $ml_orders = array_merge($ml_orders, $current_results);
                $offset += $limit;
            } else {
                // Se falhou, sair do loop
                break;
            }
        }
        
        // Processar todas as ordens para estatísticas
        foreach ($ml_orders as $order) {
            // Pular ordens canceladas
            if (isset($order['status']) && $order['status'] === 'cancelled') {
                continue;
            }
            
            // Verificar se há itens nas ordens
            if (!isset($order['order_items']) || empty($order['order_items'])) {
                continue;
            }
            
            // Obter data da venda para agrupamento
            $data_venda = substr($order['date_created'] ?? date('Y-m-d'), 0, 10);
            
            // Obter detalhes dos itens
            foreach ($order['order_items'] as $item) {
                // Inicializar todas as variáveis
                $titulo = isset($item['item']['title']) ? $item['item']['title'] : 'Produto sem título';
                $quantidade = isset($item['quantity']) ? $item['quantity'] : 1;
                $preco_unitario = isset($item['unit_price']) ? $item['unit_price'] : 0;
                $valor_total = $quantidade * $preco_unitario;
                $item_id = isset($item['item']['id']) ? $item['item']['id'] : '';
                $lucro = 0;
                $margem = 0;
                $id_produto_vinculado = 0;
                $tipo_anuncio = 'outros';
                
                // MODIFICAÇÃO: Obter informações detalhadas do item diretamente da API do ML
                if (!empty($item_id) && !empty($access_token)) {
                    $item_url = "https://api.mercadolibre.com/items/{$item_id}";
                    $ch_item = curl_init($item_url);
                    curl_setopt($ch_item, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch_item, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $access_token
                    ]);
                    
                    $response_item = curl_exec($ch_item);
                    $status_item = curl_getinfo($ch_item, CURLINFO_HTTP_CODE);
                    curl_close($ch_item);
                    
                    if ($status_item == 200) {
                        $item_data = json_decode($response_item, true);
                        // Obter o tipo de anúncio diretamente da API
                        $ml_listing_type = $item_data['listing_type_id'] ?? '';
                        
                        // Atualizar o tipo de anúncio na tabela local para futuras consultas
                        if (!empty($ml_listing_type)) {
                            $stmt_update = $pdo->prepare("UPDATE anuncios_ml 
                                                        SET tipo_anuncio = ? 
                                                        WHERE ml_item_id = ? AND usuario_id = ?");
                            $stmt_update->execute([$ml_listing_type, $item_id, $usuario_id]);
                            
                            $tipo_anuncio = $ml_listing_type;
                        }
                    }
                }
                
                // Verificar se existe um produto vinculado a este item
                if (!empty($item_id)) {
                    $stmt_item = $pdo->prepare("SELECT p.id, p.custo FROM produtos p 
                                              JOIN anuncios_ml a ON p.id = a.produto_id 
                                              WHERE a.ml_item_id = ? AND a.usuario_id = ?");
                    $stmt_item->execute([$item_id, $usuario_id]);
                    $produto_vinculado = $stmt_item->fetch();
                    
                    if ($produto_vinculado) {
                        $custo = $produto_vinculado['custo'];
                        $id_produto_vinculado = $produto_vinculado['id'];
                        
                        // Calcular taxa ML aproximada baseada no tipo de anúncio
                        $taxa_ml_percent = 12; // Valor padrão
                        
                        switch ($tipo_anuncio) {
                            case 'gold_special':
                                $taxa_ml_percent = 16;
                                break;
                            case 'gold_pro':
                                $taxa_ml_percent = 18;
                                break;
                            case 'gold':
                                $taxa_ml_percent = 12;
                                break;
                            case 'free':
                                $taxa_ml_percent = 0;
                                break;
                            default:
                                $taxa_ml_percent = 12;
                                break;
                        }
                        
                        $taxa_ml = ($taxa_ml_percent / 100) * $preco_unitario;
                        $lucro = ($preco_unitario - $custo - $taxa_ml) * $quantidade;
                        $margem = ($preco_unitario > 0) ? (($preco_unitario - $custo - $taxa_ml) / $preco_unitario) * 100 : 0;
                    }
                }
                
                // MODIFICAÇÃO: Mapear o tipo de anúncio para a categoria consolidada
                $tipo_anuncio_grupo = 'outros';
                switch ($tipo_anuncio) {
                    case 'gold':
                        $tipo_anuncio_grupo = 'gold';
                        break;
                    case 'gold_special':
                    case 'gold_pro':
                        $tipo_anuncio_grupo = 'premium'; // Consolidando Premium e Premium Pro
                        break;
                    case 'free':
                        $tipo_anuncio_grupo = 'free';
                        break;
                }
                
                // Adicionar aos totais por tipo de anúncio (usando a categoria consolidada)
                $vendas_por_tipo_anuncio[$tipo_anuncio_grupo]['count'] += $quantidade;
                $vendas_por_tipo_anuncio[$tipo_anuncio_grupo]['valor'] += $valor_total;
                $vendas_por_tipo_anuncio[$tipo_anuncio_grupo]['lucro'] += $lucro;
                
                // Adicionar aos valores diários para o gráfico
                if (isset($faturamento_diario[$data_venda])) {
                    $faturamento_diario[$data_venda] += $valor_total;
                    $lucro_diario[$data_venda] += $lucro;
                }
                
                // Adicionar à lista de vendas recentes para exibição (limitado às 5 mais recentes)
                if (count($vendas_ml_recentes) < 5) {
                    $vendas_ml_recentes[] = [
                        'id' => $order['id'] ?? '',
                        'produto' => $titulo,
                        'data_venda' => $order['date_created'] ?? date('Y-m-d H:i:s'),
                        'valor_venda' => $valor_total,
                        'lucro' => $lucro,
                        'margem_lucro' => $margem,
                        'origem' => 'mercadolivre',
                        'tipo_anuncio' => $tipo_anuncio,
                        'tipo_anuncio_grupo' => $tipo_anuncio_grupo,
                        'produto_id' => $id_produto_vinculado,
                        'status' => $order['status'] ?? 'desconhecido'
                    ];
                }
                
                // Incrementar métricas de vendas
                $metricas_vendas['total_vendas'] = ($metricas_vendas['total_vendas'] ?? 0) + 1;
                $metricas_vendas['valor_total'] = ($metricas_vendas['valor_total'] ?? 0) + $valor_total;
                $metricas_vendas['lucro_total'] = ($metricas_vendas['lucro_total'] ?? 0) + $lucro;
                
                // Atualizar valores de margens
                if ($margem >= 20) {
                    $metricas_vendas['vendas_margem_alta'] = ($metricas_vendas['vendas_margem_alta'] ?? 0) + 1;
                } elseif ($margem >= 10) {
                    $metricas_vendas['vendas_margem_media'] = ($metricas_vendas['vendas_margem_media'] ?? 0) + 1;
                } else {
                    $metricas_vendas['vendas_margem_baixa'] = ($metricas_vendas['vendas_margem_baixa'] ?? 0) + 1;
                }
            }
        }
        
        // Calcular margem média atualizada
        if (($metricas_vendas['valor_total'] ?? 0) > 0) {
            $metricas_vendas['margem_media'] = (($metricas_vendas['lucro_total'] ?? 0) / $metricas_vendas['valor_total']) * 100;
        }
    }
    
    // Top produtos vendidos
    if ($vendedor_id) {
        $sql_produtos = "SELECT 
                            p.id,
                            p.nome,
                            p.sku,
                            p.custo,
                            COUNT(v.id) as num_vendas,
                            SUM(v.valor_venda) as valor_total,
                            SUM(v.lucro) as lucro_total,
                            AVG(v.margem_lucro) as margem_media
                        FROM vendas v
                        JOIN produtos p ON v.produto = p.nome
                        WHERE v.vendedor_id = ? 
                        AND v.data_venda BETWEEN ? AND ?
                        GROUP BY p.id
                        ORDER BY valor_total DESC
                        LIMIT 5";
        
        $stmt_produtos = $pdo->prepare($sql_produtos);
        $stmt_produtos->execute([$vendedor_id, $data_inicio, $data_fim]);
        $top_produtos = $stmt_produtos->fetchAll();
        
        // Produtos sem vendas
        $sql_sem_vendas = "SELECT 
                                p.id,
                                p.nome,
                                p.sku,
                                p.custo,
                                COUNT(a.id) as num_anuncios
                            FROM produtos p
                            LEFT JOIN anuncios_ml a ON p.id = a.produto_id
                            LEFT JOIN (
                                SELECT produto, COUNT(*) as venda_count
                                FROM vendas
                                WHERE vendedor_id = ?
                                AND data_venda BETWEEN ? AND ?
                                GROUP BY produto
                            ) v ON p.nome = v.produto
                            WHERE p.usuario_id = ? AND v.venda_count IS NULL
                            GROUP BY p.id
                            ORDER BY p.nome
                            LIMIT 10";
        
        $stmt_sem_vendas = $pdo->prepare($sql_sem_vendas);
        $stmt_sem_vendas->execute([$vendedor_id, $data_inicio, $data_fim, $usuario_id]);
        $produtos_sem_vendas = $stmt_sem_vendas->fetchAll();
        
        // Adicionar valores das vendas da tabela local aos dados diários também
        $sql_vendas_diarias = "SELECT 
                                DATE(data_venda) as dia,
                                SUM(valor_venda) as valor_total,
                                SUM(lucro) as lucro_total
                            FROM vendas
                            WHERE vendedor_id = ?
                            AND data_venda BETWEEN ? AND ?
                            GROUP BY dia
                            ORDER BY dia";
        
        $stmt_vendas_diarias = $pdo->prepare($sql_vendas_diarias);
        $stmt_vendas_diarias->execute([$vendedor_id, $data_inicio, $data_fim]);
        $vendas_diarias_locais = $stmt_vendas_diarias->fetchAll();
        
        foreach ($vendas_diarias_locais as $venda_dia) {
            $dia = $venda_dia['dia'];
            if (isset($faturamento_diario[$dia])) {
                $faturamento_diario[$dia] += $venda_dia['valor_total'];
                $lucro_diario[$dia] += $venda_dia['lucro_total'];
            }
        }
    }
    
    // Combinar vendas locais e do ML para exibição
    if (!empty($vendas_ml_recentes)) {
        // Adicionar vendas do ML à lista de vendas recentes
        $vendas_recentes = array_merge($vendas_ml_recentes, $vendas_recentes);
        
        // Ordenar por data, mais recentes primeiro
        usort($vendas_recentes, function($a, $b) {
            return strtotime($b['data_venda']) - strtotime($a['data_venda']);
        });
        
        // Limitar a 10 vendas
        $vendas_recentes = array_slice($vendas_recentes, 0, 10);
    }

} catch (Exception $e) {
    $mensagem = "Erro ao carregar dados: " . $e->getMessage();
    $tipo_mensagem = "danger";
    
    // Inicializar variáveis para evitar erros
    if (!isset($metricas_vendas) || !is_array($metricas_vendas)) {
        $metricas_vendas = [
            'total_vendas' => 0,
            'valor_total' => 0,
            'lucro_total' => 0,
            'margem_media' => 0,
            'vendas_margem_alta' => 0,
            'vendas_margem_media' => 0,
            'vendas_margem_baixa' => 0
        ];
    }
    
    if (!isset($metricas_anuncios) || !is_array($metricas_anuncios)) {
        $metricas_anuncios = [
            'total_anuncios' => 0,
            'anuncios_ativos' => 0,
            'anuncios_pausados' => 0,
            'anuncios_fechados' => 0,
            'anuncios_vinculados' => 0
        ];
    }
}

// Calcular algumas métricas adicionais
$total_anuncios = $metricas_anuncios['total_anuncios'] ?? 0;
$percentual_vinculados = ($total_anuncios > 0) ? (($metricas_anuncios['anuncios_vinculados'] ?? 0) / $total_anuncios) * 100 : 0;
$ticket_medio = ($metricas_vendas['total_vendas'] > 0) ? ($metricas_vendas['valor_total'] / $metricas_vendas['total_vendas']) : 0;

// Função para formatar moeda
function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

// Função para formatar percentual
function formatPercentage($value) {
    return number_format((float)$value, 2, ',', '.') . '%';
}

// Função para formatar data
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// MODIFICAÇÃO: Função para obter o texto do tipo de anúncio (versão consolidada)
function getTipoAnuncioText($tipo) {
    switch ($tipo) {
        case 'gold_special':
        case 'gold_pro':
        case 'premium': // Novo tipo consolidado
            return 'Premium';
        case 'gold':
            return 'Clássico';
        case 'free':
            return 'Gratuito';
        default:
            return ucfirst($tipo);
    }
}

// MODIFICAÇÃO: Função para obter a cor do tipo de anúncio
function getTipoAnuncioClass($tipo) {
    switch ($tipo) {
        case 'gold_special':
        case 'gold_pro':
        case 'premium': // Novo tipo consolidado
            return 'bg-warning text-dark';
        case 'gold':
            return 'bg-info';
        case 'free':
            return 'bg-success';
        default:
            return 'bg-secondary';
    }
}

// MODIFICAÇÃO: Função para mapear o tipo de anúncio original para o tipo consolidado
function mapTipoAnuncio($tipo) {
    switch ($tipo) {
        case 'gold':
            return 'gold';
        case 'gold_special':
        case 'gold_pro':
            return 'premium'; // Consolidado
        case 'free':
            return 'free';
        default:
            return 'outros';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding-top: 56px;
        }
        .sidebar {
            position: fixed;
            top: 56px;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #343a40;
            width: 240px;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 56px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            font-weight: 500;
            color: #ced4da;
            padding: .75rem 1rem;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255,255,255,.1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,.1);
            border-left: 4px solid #ff9a00;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .main-content {
            margin-left: 240px;
            padding: 1.5rem;
        }
        .btn-warning, .bg-warning {
            background-color: #ff9a00 !important;
            border-color: #ff9a00;
        }
        .card-dashboard {
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075);
            transition: transform 0.3s ease;
        }
        .card-dashboard:hover {
            transform: translateY(-5px);
        }
        .overlay-container {
            position: relative;
            height: 100%;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .stats-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            opacity: 0.2;
            font-size: 2rem;
        }
        .status-badge {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .ml-status-ok {
            background-color: #28a745;
        }
        .ml-status-error {
            background-color: #dc3545;
        }
        .card-metric {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            margin-bottom: 1.5rem;
            height: 100%;
            border-radius: 10px;
            overflow: hidden;
        }
        .card-metric .card-body {			       
            padding: 1.5rem;
        }
        .metric-title {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        .metric-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        .metric-icon {
            font-size: 2.5rem;
            opacity: 0.2;
            position: absolute;
            right: 1.5rem;
            bottom: 1.5rem;
        }
        .bg-gradient-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        .bg-gradient-success {
            background: linear-gradient(135deg, #28a745, #208838);
            color: white;
        }
        .bg-gradient-warning {
            background: linear-gradient(135deg, #ffc107, #d39e00);
            color: white;
        }
        .bg-gradient-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        .bg-gradient-danger {
            background: linear-gradient(135deg, #dc3545, #bd2130);
            color: white;
        }
        .bg-gradient-purple {
            background: linear-gradient(135deg, #6f42c1, #543b94);
            color: white;
        }
        .source-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.4rem;
            vertical-align: middle;
        }
        .tag-ml {
            background-color: #ffe600;
            color: #333;
        }
        .tag-local {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
  

    <!-- Sidebar -->
     <?php require_once 'sidebar.php'; ?>
    <!-- Main content -->
    <main class="main-content">
        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Status do Mercado Livre e Filtro -->
        <div class="row mb-4">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3">
                            <?php if ($ml_conectado): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle"></i>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger">
                                    <i class="fas fa-times-circle"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h6 class="mb-0">Status Mercado Livre</h6>
                            <?php if ($ml_conectado): ?>
                                <small class="text-success">Conectado como: <?php echo htmlspecialchars($ml_nickname); ?> (ID: <?php echo htmlspecialchars($ml_user_id); ?>)</small>
                            <?php else: ?>
                                <small class="text-danger">Desconectado</small>
                                <div>
                                    <a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php?acao=autorizar" class="btn btn-sm btn-primary mt-2">
                                        <i class="fas fa-plug"></i> Conectar
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-7">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-5">
                                <label for="periodo" class="form-label">Período</label>
                                <select class="form-select form-select-sm" id="periodo" name="periodo">
                                    <option value="7" <?php echo $periodo == '7' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                                    <option value="30" <?php echo $periodo == '30' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                                    <option value="90" <?php echo $periodo == '90' ? 'selected' : ''; ?>>Últimos 90 dias</option>
                                    <option value="month" <?php echo $periodo == 'month' ? 'selected' : ''; ?>>Este mês</option>
                                    <option value="last_month" <?php echo $periodo == 'last_month' ? 'selected' : ''; ?>>Mês anterior</option>
                                    <option value="year" <?php echo $periodo == 'year' ? 'selected' : ''; ?>>Este ano</option>
                                    <option value="custom" <?php echo $periodo == 'custom' ? 'selected' : ''; ?>>Personalizado</option>
                                </select>
                            </div>
                            
                            <div class="col-md-5" id="customDateRange" <?php echo $periodo != 'custom' ? 'style="display:none"' : ''; ?>>
                                <label class="form-label">Datas</label>
                                <div class="input-group input-group-sm">
                                    <input type="date" class="form-control form-control-sm" name="data_inicio" value="<?php echo $data_inicio; ?>">
                                    <span class="input-group-text">até</span>
                                    <input type="date" class="form-control form-control-sm" name="data_fim" value="<?php echo $data_fim; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Aplicar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>Dashboard: <?php echo $data_inicio_display; ?> até <?php echo $data_fim_display; ?></h4>
            <div class="btn-group">
                <a href="<?php echo $base_url; ?>/vendedor_vendas.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-list"></i> Ver Todas as Vendas
                </a>
                <a href="<?php echo $base_url; ?>/vendedor_nova_venda.php" class="btn btn-sm btn-warning">
                    <i class="fas fa-plus"></i> Nova Venda
                </a>
            </div>
        </div>

        <!-- Métricas principais -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card card-metric bg-gradient-primary">
                    <div class="card-body">
                        <h6 class="metric-title">Vendas</h6>
                        <h3 class="metric-value"><?php echo number_format($metricas_vendas['total_vendas'] ?? 0, 0, ',', '.'); ?></h3>
                        <i class="fas fa-shopping-cart metric-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card card-metric bg-gradient-success">
                    <div class="card-body">
                        <h6 class="metric-title">Faturamento</h6>
                        <h3 class="metric-value"><?php echo formatCurrency($metricas_vendas['valor_total'] ?? 0); ?></h3>
                        <i class="fas fa-dollar-sign metric-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card card-metric bg-gradient-info">
                    <div class="card-body">
                        <h6 class="metric-title">Lucro Total</h6>
                        <h3 class="metric-value"><?php echo formatCurrency($metricas_vendas['lucro_total'] ?? 0); ?></h3>
                        <i class="fas fa-chart-line metric-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card card-metric bg-gradient-warning">
                    <div class="card-body">
                        <h6 class="metric-title">Margem Média</h6>
                        <h3 class="metric-value"><?php echo formatPercentage($metricas_vendas['margem_media'] ?? 0); ?></h3>
                        <i class="fas fa-percentage metric-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Segunda linha de métricas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card card-metric">
                    <div class="card-body">
                        <h6 class="metric-title">Ticket Médio</h6>
                        <h3 class="metric-value"><?php echo formatCurrency($ticket_medio); ?></h3>
                        <i class="fas fa-receipt metric-icon text-primary"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card card-metric">
                    <div class="card-body">
                        <h6 class="metric-title">Anúncios Ativos</h6>
                        <h3 class="metric-value"><?php echo $metricas_anuncios['anuncios_ativos'] ?? 0; ?></h3>
                        <i class="fas fa-tags metric-icon text-success"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card card-metric">
                    <div class="card-body">
                        <h6 class="metric-title">% Anúncios Vinculados</h6>
                        <h3 class="metric-value"><?php echo formatPercentage($percentual_vinculados); ?></h3>
                        <i class="fas fa-link metric-icon text-info"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card card-metric">
                    <div class="card-body">
                        <h6 class="metric-title">Disponibilidade</h6>
                        <h3 class="metric-value"><?php echo $ml_conectado ? "Online" : "Offline"; ?></h3>
                        <i class="fas fa-signal metric-icon <?php echo $ml_conectado ? 'text-success' : 'text-danger'; ?>"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráfico de Vendas -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Evolução de Vendas</h5>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-secondary active" data-chart-type="line">Linha</button>
                                <button type="button" class="btn btn-outline-secondary" data-chart-type="bar">Barras</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="vendas-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Faturamento por Tipo de Anúncio -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Faturamento por Tipo de Anúncio</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="faturamento-tipo-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Relatório de Vendas por Tipo de Anúncio -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Vendas por Tipo de Anúncio</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Tipo de Anúncio</th>
                                        <th class="text-center">Quantidade de Vendas</th>
                                        <th class="text-end">Faturamento</th>
                                        <th class="text-end">Lucro</th>
                                        <th class="text-end">Margem</th>
                                        <th class="text-center">% do Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_vendas_count = 0;
                                    $total_vendas_valor = 0;
                                    
                                    // Calcular totais primeiro
                                    foreach ($vendas_por_tipo_anuncio as $tipo => $dados) {
                                        $total_vendas_count += $dados['count'];
                                        $total_vendas_valor += $dados['valor'];
                                    }
                                    
                                    // Exibir linhas na tabela
                                    if ($total_vendas_count > 0):
                                        foreach ($vendas_por_tipo_anuncio as $tipo => $dados):
                                            if ($dados['count'] > 0):
                                                $tipo_label = getTipoAnuncioText($tipo);
                                                $tipo_classe = getTipoAnuncioClass($tipo);
                                                $margem = $dados['valor'] > 0 ? ($dados['lucro'] / $dados['valor']) * 100 : 0;
                                                $percentual = $total_vendas_valor > 0 ? ($dados['valor'] / $total_vendas_valor) * 100 : 0;
                                    ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge <?php echo $tipo_classe; ?>">
                                                            <?php echo $tipo_label; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center"><?php echo $dados['count']; ?></td>
                                                    <td class="text-end"><?php echo formatCurrency($dados['valor']); ?></td>
                                                    <td class="text-end"><?php echo formatCurrency($dados['lucro']); ?></td>
                                                    <td class="text-end">
                                                        <span class="badge <?php echo $margem >= 20 ? 'bg-success' : ($margem >= 10 ? 'bg-warning' : 'bg-danger'); ?>">
                                                            <?php echo formatPercentage($margem); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar <?php echo strpos($tipo_classe, 'text-dark') !== false ? str_replace('text-dark', '', $tipo_classe) : $tipo_classe; ?>" 
                                                                 role="progressbar" 
                                                                 style="width: <?php echo $percentual; ?>%"
                                                                 aria-valuenow="<?php echo $percentual; ?>" 
                                                                 aria-valuemin="0" 
                                                                 aria-valuemax="100">
                                                                <?php echo number_format($percentual, 1); ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                    <?php 
                                            endif;
                                        endforeach;
                                    else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">
                                                <i class="fas fa-info-circle text-info me-2"></i>
                                                Nenhuma venda registrada no período selecionado
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    
                                    <?php if ($total_vendas_count > 0): ?>
                                        <!-- Linha de total -->
                                        <tr class="table-active">
                                            <th>Total</th>
                                            <th class="text-center"><?php echo $total_vendas_count; ?></th>
                                            <th class="text-end"><?php echo formatCurrency($total_vendas_valor); ?></th>
                                            <th class="text-end"><?php echo formatCurrency(array_sum(array_column($vendas_por_tipo_anuncio, 'lucro'))); ?></th>
                                            <th class="text-end">
                                                <?php 
                                                $margem_total = $total_vendas_valor > 0 ? (array_sum(array_column($vendas_por_tipo_anuncio, 'lucro')) / $total_vendas_valor) * 100 : 0;
                                                echo formatPercentage($margem_total); 
                                                ?>
                                            </th>
                                            <th class="text-center">100%</th>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Produtos e Status de Anúncios -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card h-100">
    <div class="card-header bg-light">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Produtos por Faturamento</h5>
            <a href="<?php echo $base_url; ?>/vendedor_produtos.php" class="btn btn-sm btn-outline-secondary">
                Ver Todos os Produtos
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php
        // INÍCIO DO CÓDIGO AJUSTADO PARA TOP PRODUTOS
        
        // Array para armazenar os produtos combinados (local + ML)
        $produtos_combinados = [];
        
        // Passo 1: Buscar vendas do banco de dados local
        if ($vendedor_id) {
            $sql_produtos = "SELECT 
                            p.id,
                            p.nome,
                            p.sku,
                            p.custo,
                            COUNT(v.id) as num_vendas,
                            SUM(v.valor_venda) as valor_total,
                            SUM(v.lucro) as lucro_total,
                            AVG(v.margem_lucro) as margem_media,
                            'local' as origem
                        FROM vendas v
                        JOIN produtos p ON v.produto = p.nome
                        WHERE v.vendedor_id = ? 
                        AND v.data_venda BETWEEN ? AND ?
                        GROUP BY p.id";
            
            $stmt_produtos = $pdo->prepare($sql_produtos);
            $stmt_produtos->execute([$vendedor_id, $data_inicio, $data_fim]);
            $produtos_local = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);
            
            // Adicionar produtos do banco local ao array combinado
            foreach ($produtos_local as $produto) {
                $produtos_combinados[$produto['id']] = $produto;
            }
        }
        
        // Passo 2: Se conectado ao ML, processar as vendas da API
        if ($ml_conectado && isset($ml_orders) && !empty($ml_orders)) {
            // Array para armazenar os dados temporários das vendas do ML
            $vendas_ml_por_produto = [];
            
            // Processar todas as ordens para encontrar produtos relacionados
            foreach ($ml_orders as $order) {
                // Pular ordens canceladas
                if (isset($order['status']) && $order['status'] === 'cancelled') {
                    continue;
                }
                
                // Verificar se há itens nas ordens
                if (!isset($order['order_items']) || empty($order['order_items'])) {
                    continue;
                }
                
                // Processar cada item das ordens
                foreach ($order['order_items'] as $item) {
                    $item_id = isset($item['item']['id']) ? $item['item']['id'] : '';
                    if (empty($item_id)) continue;
                    
                    // Buscar o produto vinculado a este item
                    $stmt_produto = $pdo->prepare("SELECT p.id, p.nome, p.sku, p.custo 
                                                 FROM produtos p 
                                                 JOIN anuncios_ml a ON p.id = a.produto_id 
                                                 WHERE a.ml_item_id = ? AND a.usuario_id = ?");
                    $stmt_produto->execute([$item_id, $usuario_id]);
                    $produto_vinculado = $stmt_produto->fetch(PDO::FETCH_ASSOC);
                    
                    if ($produto_vinculado) {
                        $produto_id = $produto_vinculado['id'];
                        $quantidade = isset($item['quantity']) ? $item['quantity'] : 1;
                        $preco_unitario = isset($item['unit_price']) ? $item['unit_price'] : 0;
                        $valor_total = $quantidade * $preco_unitario;
                        
                        // Calcular taxa ML aproximada
                        $taxa_ml_percent = 12; // Valor padrão
                        
                        // Buscar tipo do anúncio 
                        $stmt_anuncio = $pdo->prepare("SELECT tipo_anuncio FROM anuncios_ml 
                                                    WHERE ml_item_id = ? AND usuario_id = ?");
                        $stmt_anuncio->execute([$item_id, $usuario_id]);
                        $anuncio_info = $stmt_anuncio->fetch();
                        
                        if ($anuncio_info) {
                            $tipo_anuncio = $anuncio_info['tipo_anuncio'] ?? 'gold';
                            
                            switch ($tipo_anuncio) {
                                case 'gold_special':
                                    $taxa_ml_percent = 16;
                                    break;
                                case 'gold_pro':
                                    $taxa_ml_percent = 18;
                                    break;
                                case 'free':
                                    $taxa_ml_percent = 0;
                                    break;
                                default:
                                    $taxa_ml_percent = 12;
                                    break;
                            }
                        }
                        
                        $taxa_ml = ($taxa_ml_percent / 100) * $preco_unitario;
                        $lucro = ($preco_unitario - $produto_vinculado['custo'] - $taxa_ml) * $quantidade;
                        $margem = ($preco_unitario > 0) ? (($preco_unitario - $produto_vinculado['custo'] - $taxa_ml) / $preco_unitario) * 100 : 0;
                        
                        // Criar ou atualizar o registro nas vendas do ML por produto
                        if (!isset($vendas_ml_por_produto[$produto_id])) {
                            $vendas_ml_por_produto[$produto_id] = [
                                'id' => $produto_id,
                                'nome' => $produto_vinculado['nome'],
                                'sku' => $produto_vinculado['sku'],
                                'custo' => $produto_vinculado['custo'],
                                'num_vendas' => 0,
                                'valor_total' => 0,
                                'lucro_total' => 0,
                                'margem_valores' => [], // Para calcular média depois
                                'margem_media' => 0,
                                'origem' => 'mercadolivre'
                            ];
                        }
                        
                        // Atualizar os totais
                        $vendas_ml_por_produto[$produto_id]['num_vendas'] += $quantidade;
                        $vendas_ml_por_produto[$produto_id]['valor_total'] += $valor_total;
                        $vendas_ml_por_produto[$produto_id]['lucro_total'] += $lucro;
                        $vendas_ml_por_produto[$produto_id]['margem_valores'][] = $margem;
                    }
                }
            }
            
            // Calcular a margem média para cada produto do ML
            foreach ($vendas_ml_por_produto as $id => $produto_ml) {
                if (!empty($produto_ml['margem_valores'])) {
                    $vendas_ml_por_produto[$id]['margem_media'] = array_sum($produto_ml['margem_valores']) / count($produto_ml['margem_valores']);
                }
                unset($vendas_ml_por_produto[$id]['margem_valores']); // Remover array auxiliar
            }
            
            // Passo 3: Combinar os dados locais com os dados do ML
            foreach ($vendas_ml_por_produto as $id => $produto_ml) {
                if (isset($produtos_combinados[$id])) {
                    // Produto já existe no array combinado, somar os valores
                    $produtos_combinados[$id]['num_vendas'] += $produto_ml['num_vendas'];
                    $produtos_combinados[$id]['valor_total'] += $produto_ml['valor_total'];
                    $produtos_combinados[$id]['lucro_total'] += $produto_ml['lucro_total'];
                    
                    // Recalcular a margem média ponderada
                    $total_vendas = $produtos_combinados[$id]['num_vendas'];
                    $vendas_locais = $total_vendas - $produto_ml['num_vendas'];
                    
                    if ($total_vendas > 0) {
                        $produtos_combinados[$id]['margem_media'] = (
                            ($produtos_combinados[$id]['margem_media'] * $vendas_locais) + 
                            ($produto_ml['margem_media'] * $produto_ml['num_vendas'])
                        ) / $total_vendas;
                    }
                    
                    $produtos_combinados[$id]['origem'] = 'combinado';
                } else {
                    // Produto só existe no ML, adicionar ao array
                    $produtos_combinados[$id] = $produto_ml;
                }
            }
        }
        
        // Passo 4: Transformar o array associativo em um array simples para ordenação
        $top_produtos = array_values($produtos_combinados);
        
        // Passo 5: Ordenar pelo valor total de vendas (decrescente)
        usort($top_produtos, function($a, $b) {
            return $b['valor_total'] - $a['valor_total'];
        });
        
        // Limitar aos 5 produtos mais vendidos
        $top_produtos = array_slice($top_produtos, 0, 5);
        // FIM DO CÓDIGO AJUSTADO PARA TOP PRODUTOS
        ?>
        
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Produto</th>
                        <th class="text-center">Vendas</th>
                        <th class="text-end">Valor Total</th>
                        <th class="text-end">Lucro</th>
                        <th class="text-end">Margem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_produtos)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <i class="fas fa-info-circle text-info me-2"></i>
                                Nenhuma venda registrada no período selecionado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($top_produtos as $produto): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($produto['nome']); ?></strong>
                                        <?php if (!empty($produto['sku'])): ?>
                                            <div class="small text-muted">SKU: <?php echo htmlspecialchars($produto['sku']); ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($produto['origem'])): ?>
                                            <?php if ($produto['origem'] == 'local'): ?>
                                                <span class="badge bg-secondary source-badge">
                                                    <i class="fas fa-store"></i> Local
                                                </span>
                                            <?php elseif ($produto['origem'] == 'mercadolivre'): ?>
                                                <span class="badge bg-warning text-dark source-badge">
                                                    <i class="fas fa-shopping-bag"></i> ML
                                                </span>
                                            <?php elseif ($produto['origem'] == 'combinado'): ?>
                                                <span class="badge bg-info text-white source-badge">
                                                    <i class="fas fa-sync-alt"></i> Combinado
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center"><?php echo $produto['num_vendas']; ?></td>
                                <td class="text-end"><?php echo formatCurrency($produto['valor_total']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($produto['lucro_total']); ?></td>
                                <td class="text-end">
                                    <span class="badge <?php echo $produto['margem_media'] >= 20 ? 'bg-success' : ($produto['margem_media'] >= 10 ? 'bg-warning' : 'bg-danger'); ?>">
                                        <?php echo formatPercentage($produto['margem_media']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Status dos Anúncios</h5>
                            <a href="<?php echo $base_url; ?>/vendedor_anuncios.php" class="btn btn-sm btn-outline-secondary">
                                Gerenciar
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 200px;">
                            <canvas id="anuncios-chart"></canvas>
                        </div>
                        <div class="row mt-4">
                            <div class="col-md-4 text-center">
                                <div class="h3 mb-0"><?php echo $metricas_anuncios['anuncios_ativos'] ?? 0; ?></div>
                                <div class="text-success">Ativos</div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="h3 mb-0"><?php echo $metricas_anuncios['anuncios_pausados'] ?? 0; ?></div>
                                <div class="text-warning">Pausados</div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="h3 mb-0"><?php echo $metricas_anuncios['anuncios_fechados'] ?? 0; ?></div>
                                <div class="text-secondary">Finalizados</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Últimas Vendas e Produtos sem Vendas -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Últimas Vendas</h5>
                            <a href="<?php echo $base_url; ?>/vendedor_vendas.php" class="btn btn-sm btn-outline-secondary">
                                Ver Todas
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data</th>
                                        <th>Produto</th>
                                        <th>Origem</th>
                                        <th>Tipo</th>
                                        <th class="text-end">Valor</th>
                                        <th class="text-end">Lucro</th>
                                        <th class="text-end">Margem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($vendas_recentes)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="fas fa-info-circle text-info me-2"></i>
                                                Nenhuma venda registrada
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($vendas_recentes as $venda): ?>
                                            <?php
                                            // MODIFICAÇÃO: Mapear tipo de anúncio para o tipo consolidado
                                            if (isset($venda['origem']) && $venda['origem'] == 'mercadolivre' && isset($venda['tipo_anuncio'])) {
                                                $tipo_anuncio_exibir = mapTipoAnuncio($venda['tipo_anuncio']);
                                            } else {
                                                $tipo_anuncio_exibir = $venda['tipo_anuncio'] ?? '';
                                            }
                                            ?>
                                            <tr>
                                                <td><?php echo formatDate($venda['data_venda']); ?></td>
                                                <td>
                                                    <div style="max-width: 250px; white-space: normal;">
                                                        <?php echo htmlspecialchars(mb_substr($venda['produto'], 0, 50) . (mb_strlen($venda['produto']) > 50 ? '...' : '')); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (isset($venda['origem']) && $venda['origem'] == 'mercadolivre'): ?>
                                                        <span class="badge bg-warning text-dark source-badge">
                                                            <i class="fas fa-shopping-bag"></i> ML
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary source-badge">
                                                            <i class="fas fa-store"></i> Local
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($tipo_anuncio_exibir)): ?>
                                                        <span class="badge <?php echo getTipoAnuncioClass($tipo_anuncio_exibir); ?> source-badge">
                                                            <?php echo getTipoAnuncioText($tipo_anuncio_exibir); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-dark source-badge">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end"><?php echo formatCurrency($venda['valor_venda']); ?></td>
                                                <td class="text-end"><?php echo formatCurrency($venda['lucro']); ?></td>
                                                <td class="text-end">
                                                    <span class="badge <?php echo $venda['margem_lucro'] >= 20 ? 'bg-success' : ($venda['margem_lucro'] >= 10 ? 'bg-warning' : 'bg-danger'); ?>">
                                                        <?php echo formatPercentage($venda['margem_lucro']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Produtos sem Vendas</h5>
                            <a href="<?php echo $base_url; ?>/vendedor_produtos.php" class="btn btn-sm btn-outline-secondary">
                                Gerenciar
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Produto</th>
                                        <th>Custo</th>
                                        <th>Anúncios</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($produtos_sem_vendas)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                Todos os produtos tiveram vendas no período!
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($produtos_sem_vendas as $produto): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($produto['nome']); ?>
                                                    <?php if (!empty($produto['sku'])): ?>
                                                        <div class="small text-muted">SKU: <?php echo htmlspecialchars($produto['sku']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatCurrency($produto['custo']); ?></td>
                                                <td>
                                                    <?php if ($produto['num_anuncios'] > 0): ?>
                                                        <span class="badge bg-success"><?php echo $produto['num_anuncios']; ?> anúncio(s)</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Sem anúncios</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($produto['num_anuncios'] == 0): ?>
                                                        <a href="<?php echo $base_url; ?>/vendedor_anuncios.php" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-link"></i> Vincular
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ações Recomendadas -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Ações Recomendadas</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php if (!$ml_conectado): ?>
                                <a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php?acao=autorizar" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Conectar ao Mercado Livre</h6>
                                        <small class="text-danger"><i class="fas fa-exclamation-circle"></i> Importante</small>
                                    </div>
                                    <p class="mb-1">Conecte sua conta do Mercado Livre para sincronizar anúncios e vendas.</p>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($percentual_vinculados < 80): ?>
                                <a href="<?php echo $base_url; ?>/vendedor_anuncios.php?vinculados=nao" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Vincular anúncios a produtos</h6>
                                        <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Recomendado</small>
                                    </div>
                                    <p class="mb-1">Você tem anúncios não vinculados a produtos. A vinculação melhora o cálculo de lucros e margens.</p>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($produtos_sem_vendas)): ?>
                                <a href="<?php echo $base_url; ?>/vendedor_produtos.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Verificar produtos sem vendas</h6>
                                        <small class="text-info"><i class="fas fa-info-circle"></i> Sugestão</small>
                                    </div>
                                    <p class="mb-1">Você tem <?php echo count($produtos_sem_vendas); ?> produtos sem vendas no período. Considere revisar os anúncios.</p>
                                </a>
                            <?php endif; ?>
                            
                            <a href="<?php echo $base_url; ?>/vendedor_analise_abc.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Análise de Curva ABC</h6>
                                    <small class="text-info"><i class="fas fa-lightbulb"></i> Dica</small>
                                </div>
                                <p class="mb-1">Descubra quais produtos estão trazendo mais resultados e quais precisam de atenção.</p>
                            </a>
                            
                            <a href="<?php echo $base_url; ?>/vendedor_nova_venda.php" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Registrar vendas manuais</h6>
                                    <small class="text-primary"><i class="fas fa-plus-circle"></i> Ação</small>
                                </div>
                                <p class="mb-1">Não se esqueça de registrar as vendas feitas fora do Mercado Livre para métricas completas.</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mostrar/esconder datas personalizadas
            const periodoSelect = document.getElementById('periodo');
            const customDateRange = document.getElementById('customDateRange');
            
            if (periodoSelect) {
                periodoSelect.addEventListener('change', function() {
                    if (this.value === 'custom') {
                        customDateRange.style.display = 'block';
                    } else {
                        customDateRange.style.display = 'none';
                    }
                });
            }
            
            // Gráfico de vendas
            const vendasCtx = document.getElementById('vendas-chart');
            if (vendasCtx) {
                const vendasData = {
                    labels: [
                        <?php 
                        foreach ($datas_periodo as $data) {
                            echo "'" . date('d/m', strtotime($data)) . "',";
                        }
                        ?>
                    ],
                    datasets: [
                        {
                            label: 'Faturamento',
                            data: [
                                <?php 
                                foreach ($datas_periodo as $data) {
                                    echo isset($faturamento_diario[$data]) ? $faturamento_diario[$data] : 0;
                                    echo ",";
                                }
                                ?>
                            ],
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Lucro',
                            data: [
                                <?php 
                                foreach ($datas_periodo as $data) {
                                    echo isset($lucro_diario[$data]) ? $lucro_diario[$data] : 0;
                                    echo ",";
                                }
                                ?>
                            ],
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                };
                
                const vendasChart = new Chart(vendasCtx.getContext('2d'), {
                    type: 'line',
                    data: vendasData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
                
                // Alternância entre gráfico de linha e barra
                document.querySelectorAll('[data-chart-type]').forEach(button => {
                    button.addEventListener('click', function() {
                        const chartType = this.getAttribute('data-chart-type');
                        
                        // Atualizar classe ativa
                        document.querySelectorAll('[data-chart-type]').forEach(btn => {
                            btn.classList.remove('active');
                        });
                        this.classList.add('active');
                        
                        // Atualizar tipo de gráfico
                        vendasChart.config.type = chartType;
                        vendasChart.update();
                    });
                });
            }
            
            // MODIFICAÇÃO: Gráfico de tipos de anúncios por faturamento (versão consolidada)
            const faturamentoTipoCtx = document.getElementById('faturamento-tipo-chart');
            if (faturamentoTipoCtx) {
                new Chart(faturamentoTipoCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: [
                            <?php
                            // Usar os tipos consolidados
                            foreach ($vendas_por_tipo_anuncio as $tipo => $dados) {
                                if ($dados['count'] > 0) {
                                    echo "'" . getTipoAnuncioText($tipo) . "',";
                                }
                            }
                            ?>
                        ],
                        datasets: [{
                            label: 'Faturamento',
                            data: [
                                <?php
                                foreach ($vendas_por_tipo_anuncio as $tipo => $dados) {
                                    if ($dados['count'] > 0) {
                                        echo $dados['valor'] . ",";
                                    }
                                }
                                ?>
                            ],
                            backgroundColor: [
                                'rgba(23, 162, 184, 0.7)',   // Azul (info/classico)
                                'rgba(255, 193, 7, 0.7)',    // Amarelo (warning/premium consolidado)
                                'rgba(40, 167, 69, 0.7)',    // Verde (success/free)
                                'rgba(108, 117, 125, 0.7)'   // Cinza (secondary/outros)
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'R$ ' + value.toLocaleString('pt-BR');
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'R$ ' + context.raw.toLocaleString('pt-BR', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Gráfico de anúncios por status
            const anunciosCtx = document.getElementById('anuncios-chart');
            if (anunciosCtx) {
                new Chart(anunciosCtx.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: ['Ativos', 'Pausados', 'Finalizados'],
                        datasets: [{
                            data: [
                                <?php echo $metricas_anuncios['anuncios_ativos'] ?? 0; ?>,
                                <?php echo $metricas_anuncios['anuncios_pausados'] ?? 0; ?>,
                                <?php echo $metricas_anuncios['anuncios_fechados'] ?? 0; ?>
                            ],
                            backgroundColor: [
                                'rgba(40, 167, 69, 0.7)',   // Verde (success)
                                'rgba(255, 193, 7, 0.7)',   // Amarelo (warning)
                                'rgba(108, 117, 125, 0.7)'  // Cinza (secondary)
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>