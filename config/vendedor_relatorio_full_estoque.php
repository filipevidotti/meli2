<?php
// Iniciar sessão
session_start();

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

// Incluir biblioteca de funções do Mercado Livre
require_once 'api_mercadolivre.php';

// Verificar se o vendedor tem um token válido do Mercado Livre
$ml_token = getMercadoLivreToken($pdo, $usuario_id);
$token_valido = !empty($ml_token);

if (!$token_valido) {
    $_SESSION['mensagem'] = "Você precisa autorizar o acesso ao Mercado Livre para usar esta função.";
    $_SESSION['tipo_mensagem'] = "warning";
    header("Location: {$base_url}/vendedor_mercadolivre.php");
    exit;
}

// Obter informações do usuário do Mercado Livre
$ml_usuario = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM mercadolivre_usuarios 
        WHERE usuario_id = ? 
        ORDER BY atualizado_em DESC 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $ml_usuario = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro
}

if (empty($ml_usuario['ml_user_id'])) {
    $_SESSION['mensagem'] = "Não foi possível encontrar seu ID de usuário do Mercado Livre.";
    $_SESSION['tipo_mensagem'] = "danger";
    header("Location: {$base_url}/vendedor_mercadolivre.php");
    exit;
}

// Inicializar variáveis
$ml_seller_id = $ml_usuario['ml_user_id'];
$access_token = $ml_token['access_token'];
$anuncios_full = [];
$relatorio_gerado = false;
$erro_api = false;
$mensagem_erro = '';
$total_anuncios = 0;
$total_anuncios_full = 0;
$total_estoque = 0;
$total_vendas = 0;
$periodo_dias = isset($_GET['periodo']) ? intval($_GET['periodo']) : 30;
$categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$ordenar_por = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'cobertura_asc';
$min_cobertura = isset($_GET['min_cobertura']) ? floatval($_GET['min_cobertura']) : 0;
$max_cobertura = isset($_GET['max_cobertura']) ? floatval($_GET['max_cobertura']) : 0;

// Verificar se está solicitando a geração do relatório
if (isset($_GET['gerar']) && $_GET['gerar'] == '1') {
    try {
        // Definir período para análise de vendas
        $data_fim = new DateTime();
        $data_inicio = clone $data_fim;
        $data_inicio->modify("-{$periodo_dias} days");
        
        // Buscar todos os anúncios do vendedor que são do tipo Full
        $offset = 0;
        $limit = 50;
        $has_more = true;
        $anuncios_coletados = [];
        
        while ($has_more) {
            $response = mercadoLivreGet("/users/{$ml_seller_id}/items/search?status=active&limit={$limit}&offset={$offset}", $access_token);
            
            if ($response['status_code'] == 200 && isset($response['data']['results'])) {
                $item_ids = $response['data']['results'];
                
                foreach ($item_ids as $item_id) {
                    // Buscar detalhes do anúncio
                    $item_response = mercadoLivreGet("/items/{$item_id}", $access_token);
                    
                    if ($item_response['status_code'] == 200) {
                        $item_data = $item_response['data'];
                        
                        // Verificar se é um anúncio Full (shipping mode = fulfillment)
                        $is_full = false;
                        if (isset($item_data['shipping']) && isset($item_data['shipping']['mode']) && $item_data['shipping']['mode'] === 'fulfillment') {
                            $is_full = true;
                        }
                        
                        if (!$is_full) {
                            continue; // Pular se não for Full
                        }
                        
                        // Aplicar filtro de categoria se necessário
                        if (!empty($categoria) && $item_data['category_id'] != $categoria) {
                            continue;
                        }
                        
                        $anuncios_coletados[] = $item_data;
                        $total_anuncios_full++;
                    }
                }
                
                $offset += count($item_ids);
                $has_more = count($item_ids) == $limit && $offset < $response['data']['paging']['total'];
                $total_anuncios = $response['data']['paging']['total'];
            } else {
                $has_more = false;
                $erro_api = true;
                $mensagem_erro = "Erro ao buscar anúncios: " . ($response['data']['message'] ?? 'Erro desconhecido');
            }
        }
        
        if ($erro_api) {
            $mensagem = $mensagem_erro;
            $tipo_mensagem = "danger";
        } else {
            if (empty($anuncios_coletados)) {
                $mensagem = "Nenhum anúncio do tipo Full encontrado. Verifique se você tem produtos utilizando o serviço Mercado Livre Full.";
                $tipo_mensagem = "warning";
            } else {
                // Buscar vendas no período para calcular taxa média diária
                $vendas_periodo = buscarOrdensVendedor(
                    $ml_seller_id,
                    $access_token,
                    'paid',
                    $data_inicio->format('Y-m-d'),
                    $data_fim->format('Y-m-d')
                );
                
                // Calcular vendas por produto
                $vendas_por_produto = [];
                
                if ($vendas_periodo['total'] > 0) {
                    foreach ($vendas_periodo['ordens'] as $ordem) {
                        if (!empty($ordem['order_items'])) {
                            foreach ($ordem['order_items'] as $item) {
                                $produto_id = $item['item']['id'];
                                $quantidade = $item['quantity'];
                                
                                if (isset($vendas_por_produto[$produto_id])) {
                                    $vendas_por_produto[$produto_id] += $quantidade;
                                } else {
                                    $vendas_por_produto[$produto_id] = $quantidade;
                                }
                                
                                $total_vendas += $quantidade;
                            }
                        }
                    }
                }
                
                // Calcular métricas de cobertura de estoque para cada anúncio
                foreach ($anuncios_coletados as $anuncio) {
                    $estoque_atual = $anuncio['available_quantity'];
                    $total_estoque += $estoque_atual;
                    
                    // Buscar vendas deste produto no período
                    $vendas_produto = $vendas_por_produto[$anuncio['id']] ?? 0;
                    
                    // Calcular taxa média diária de vendas
                    $taxa_diaria = $periodo_dias > 0 ? $vendas_produto / $periodo_dias : 0;
                    
                    // Calcular cobertura de estoque em dias
                    $cobertura_dias = $taxa_diaria > 0 ? $estoque_atual / $taxa_diaria : ($estoque_atual > 0 ? 999 : 0);
                    
                    // Buscar informações do produto no banco de dados
                    $produto_info = null;
                    try {
                        $stmt = $pdo->prepare("
                            SELECT p.nome, p.custo, p.sku, a.produto_id 
                            FROM anuncios_ml a
                            LEFT JOIN produtos p ON a.produto_id = p.id
                            WHERE a.ml_item_id = ? AND a.usuario_id = ?
                        ");
                        $stmt->execute([$anuncio['id'], $usuario_id]);
                        $produto_info = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        // Silenciar erro
                    }
                    
                    // Definir status de cobertura
                    $status_cobertura = 'normal';
                    if ($cobertura_dias < 15) {
                        $status_cobertura = 'baixa';
                    } elseif ($cobertura_dias > 60) {
                        $status_cobertura = 'alta';
                    }
                    
                    // Adicionar anúncio à lista com métricas calculadas
                    $anuncio_dados = [
                        'id' => $anuncio['id'],
                        'titulo' => $anuncio['title'],
                        'preco' => $anuncio['price'],
                        'estoque_atual' => $estoque_atual,
                        'vendas_periodo' => $vendas_produto,
                        'taxa_diaria' => $taxa_diaria,
                        'cobertura_dias' => $cobertura_dias,
                        'status_cobertura' => $status_cobertura,
                        'permalink' => $anuncio['permalink'],
                        'thumbnail' => $anuncio['thumbnail'],
                        'categoria_id' => $anuncio['category_id'],
                        'produto_nome' => $produto_info['nome'] ?? null,
                        'produto_id' => $produto_info['produto_id'] ?? null,
                        'sku' => $produto_info['sku'] ?? null,
                        'custo' => $produto_info['custo'] ?? null,
                        'lead_time' => 0, // Tempo estimado de reposição (dias) - padrão
                        'fator_seguranca' => 1.5, // Fator de segurança para estoque - padrão
                    ];
                    
                    // Calcular ponto de reposição
                    $anuncio_dados['ponto_reposicao'] = ceil($anuncio_dados['taxa_diaria'] * $anuncio_dados['lead_time'] * $anuncio_dados['fator_seguranca']);
                    
                    // Calcular estoque sugerido para 30 dias
                    $anuncio_dados['estoque_sugerido_30'] = ceil($anuncio_dados['taxa_diaria'] * 30 * $anuncio_dados['fator_seguranca']);
                    
                    // Calcular estoque sugerido para 60 dias
                    $anuncio_dados['estoque_sugerido_60'] = ceil($anuncio_dados['taxa_diaria'] * 60 * $anuncio_dados['fator_seguranca']);
                    
                    // Calcular quantidade a repor
                    $anuncio_dados['qtd_reposicao'] = max(0, $anuncio_dados['estoque_sugerido_30'] - $anuncio_dados['estoque_atual']);
                    
                    // Aplicar filtros de cobertura
                    if (($min_cobertura > 0 && $cobertura_dias < $min_cobertura) || 
                        ($max_cobertura > 0 && $cobertura_dias > $max_cobertura)) {
                        continue;
                    }
                    
                    // Adicionar à lista final
                    $anuncios_full[] = $anuncio_dados;
                }
                
                // Ordenar anúncios conforme solicitado
                switch ($ordenar_por) {
                    case 'cobertura_asc':
                        usort($anuncios_full, function($a, $b) {
                            return $a['cobertura_dias'] <=> $b['cobertura_dias'];
                        });
                        break;
                    case 'cobertura_desc':
                        usort($anuncios_full, function($a, $b) {
                            return $b['cobertura_dias'] <=> $a['cobertura_dias'];
                        });
                        break;
                    case 'vendas_desc':
                        usort($anuncios_full, function($a, $b) {
                            return $b['vendas_periodo'] <=> $a['vendas_periodo'];
                        });
                        break;
                    case 'vendas_asc':
                        usort($anuncios_full, function($a, $b) {
                            return $a['vendas_periodo'] <=> $b['vendas_periodo'];
                        });
                        break;
                    case 'estoque_asc':
                        usort($anuncios_full, function($a, $b) {
                            return $a['estoque_atual'] <=> $b['estoque_atual'];
                        });
                        break;
                    case 'estoque_desc':
                        usort($anuncios_full, function($a, $b) {
                            return $b['estoque_atual'] <=> $a['estoque_atual'];
                        });
                        break;
                    case 'reposicao_desc':
                        usort($anuncios_full, function($a, $b) {
                            return $b['qtd_reposicao'] <=> $a['qtd_reposicao'];
                        });
                        break;
                }
                
                $relatorio_gerado = true;
                
                if (empty($anuncios_full)) {
                    $mensagem = "Nenhum anúncio encontrado que atenda aos critérios de cobertura selecionados.";
                    $tipo_mensagem = "info";
                }
            }
        }
    } catch (Exception $e) {
        $erro_api = true;
        $mensagem_erro = "Erro ao gerar relatório: " . $e->getMessage();
        $mensagem = $mensagem_erro;
        $tipo_mensagem = "danger";
    }
}

// Buscar categorias para o filtro
$categorias = [];
try {
    $stmt = $pdo->query("
        SELECT DISTINCT a.categoria_id, 
               (SELECT nome FROM categorias_ml WHERE id = a.categoria_id) as nome
        FROM anuncios_ml a
        WHERE a.usuario_id = $usuario_id AND a.categoria_id IS NOT NULL
        ORDER BY nome
    ");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro, usar array vazio
}

// Exibir mensagem de sessão, se existir
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'] ?? 'info';
    
    // Limpar a mensagem da sessão
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}

// Funções de formatação
function formatarPreco($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatarNumero($valor, $decimais = 0) {
    return number_format($valor, $decimais, ',', '.');
}

function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cobertura de Estoque - Full - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .anuncio-img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(255, 154, 0, 0.05);
        }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1) !important;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .cobertura-badge {
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
            border-radius: 50rem;
        }
        .cobertura-baixa {
            background-color: #f8d7da;
            color: #842029;
        }
        .cobertura-normal {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .cobertura-alta {
            background-color: #fff3cd;
            color: #664d03;
        }
        .table-fixed-header {
            overflow-y: auto;
            max-height: 800px;
        }
        .table-fixed-header thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background-color: #f8f9fa;
        }
        .print-section {
            display: none;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .print-section {
                display: block;
            }
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            body {
                padding-top: 0;
            }
            .table-fixed-header {
                overflow-y: visible;
                max-height: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">CalcMeli</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($usuario_nome); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/vendedor_config.php"><i class="fas fa-cog"></i> Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/login.php?logout=1"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar no-print">
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_produtos.php">
                        <i class="fas fa-box"></i> Produtos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_anuncios.php">
                        <i class="fas fa-tags"></i> Anúncios ML
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_vendas.php">
                        <i class="fas fa-shopping-cart"></i> Vendas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo $base_url; ?>/vendedor_relatorios.php">
                        <i class="fas fa-chart-bar"></i> Relatórios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_config.php">
                        <i class="fas fa-cog"></i> Configurações
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Conteúdo principal -->
    <main class="main-content">
        <div class="container-fluid">
            <!-- Cabeçalho para impressão -->
            <div class="print-section mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h1>Relatório de Cobertura de Estoque - Mercado Livre Full</h1>
                    <div>
                        <p class="mb-0">Data: <?php echo date('d/m/Y'); ?></p>
                        <p class="mb-0">Período: <?php echo $periodo_dias; ?> dias</p>
                    </div>
                </div>
                <hr>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <div>
                    <h1 class="h2">Cobertura de Estoque - Mercado Livre Full</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/vendedor_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/vendedor_relatorios.php">Relatórios</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Cobertura de Estoque Full</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <?php if ($relatorio_gerado && !empty($anuncios_full)): ?>
                    <button type="button" class="btn btn-outline-primary me-2" onclick="exportarCSV()">
                        <i class="fas fa-file-csv"></i> Exportar CSV
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show no-print" role="alert">
                <?php echo htmlspecialchars($mensagem); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Filtros e Formulário -->
            <div class="card border-0 shadow-sm mb-4 no-print">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Parâmetros do Relatório</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="" class="row g-3">
                        <div class="col-md-3">
                            <label for="periodo" class="form-label">Período de Análise</label>
                            <select class="form-select" id="periodo" name="periodo">
                                <option value="30" <?php echo $periodo_dias == 30 ? 'selected' : ''; ?>>Últimos 30 dias</option>
                                <option value="60" <?php echo $periodo_dias == 60 ? 'selected' : ''; ?>>Últimos 60 dias</option>
                                <option value="90" <?php echo $periodo_dias == 90 ? 'selected' : ''; ?>>Últimos 90 dias</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="categoria" class="form-label">Categoria (opcional)</label>
                            <select class="form-select" id="categoria" name="categoria">
                                <option value="">Todas as Categorias</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['categoria_id']; ?>" <?php echo $categoria === $cat['categoria_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nome'] ?? $cat['categoria_id']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="ordenar" class="form-label">Ordenar por</label>
                            <select class="form-select" id="ordenar" name="ordenar">
                                <option value="cobertura_asc" <?php echo $ordenar_por === 'cobertura_asc' ? 'selected' : ''; ?>>Menor Cobertura</option>
                                <option value="cobertura_desc" <?php echo $ordenar_por === 'cobertura_desc' ? 'selected' : ''; ?>>Maior Cobertura</option>
                                <option value="vendas_desc" <?php echo $ordenar_por === 'vendas_desc' ? 'selected' : ''; ?>>Mais Vendidos</option>
                                <option value="vendas_asc" <?php echo $ordenar_por === 'vendas_asc' ? 'selected' : ''; ?>>Menos Vendidos</option>
                                <option value="estoque_asc" <?php echo $ordenar_por === 'estoque_asc' ? 'selected' : ''; ?>>Menor Estoque</option>
                                <option value="estoque_desc" <?php echo $ordenar_por === 'estoque_desc' ? 'selected' : ''; ?>>Maior Estoque</option>
                                <option value="reposicao_desc" <?php echo $ordenar_por === 'reposicao_desc' ? 'selected' : ''; ?>>Prioridade de Reposição</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Filtrar por Cobertura (dias)</label>
                            <div class="input-group">
							
							                                <input type="number" class="form-control" name="min_cobertura" placeholder="Mínimo" value="<?php echo $min_cobertura ?: ''; ?>">
                                <span class="input-group-text">a</span>
                                <input type="number" class="form-control" name="max_cobertura" placeholder="Máximo" value="<?php echo $max_cobertura ?: ''; ?>">
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <input type="hidden" name="gerar" value="1">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Gerar Relatório
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($relatorio_gerado && !empty($anuncios_full)): ?>
            
            <!-- Resumo do Relatório -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card border-0 shadow-sm stats-card h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Total de Produtos Full</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="mb-0"><?php echo count($anuncios_full); ?></h3>
                                <span class="badge bg-primary">Full</span>
                            </div>
                            <small class="text-muted">
                                <?php echo formatarPorcentagem((count($anuncios_full) / $total_anuncios_full) * 100); ?> do total de anúncios Full
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card border-0 shadow-sm stats-card h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Estoque Total</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="mb-0"><?php echo formatarNumero(array_sum(array_column($anuncios_full, 'estoque_atual'))); ?></h3>
                                <i class="fas fa-boxes fs-3 text-primary"></i>
                            </div>
                            <small class="text-muted">
                                Valor estimado: <?php echo formatarPreco(array_sum(array_map(function($a) { return $a['estoque_atual'] * ($a['custo'] ?? 0); }, $anuncios_full))); ?>
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card border-0 shadow-sm stats-card h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Vendas no Período</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="mb-0"><?php echo formatarNumero(array_sum(array_column($anuncios_full, 'vendas_periodo'))); ?></h3>
                                <i class="fas fa-shopping-cart fs-3 text-success"></i>
                            </div>
                            <small class="text-muted">
                                <?php echo formatarNumero(array_sum(array_column($anuncios_full, 'vendas_periodo')) / $periodo_dias, 1); ?> unidades/dia
                            </small>
                        </div>
                    </div>
					
					  <div class="col-md-3 mb-3">
                <div class="card border-0 shadow-sm stats-card h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Reposição Sugerida</h6>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="mb-0"><?php echo formatarNumero(array_sum(array_column($anuncios_full, 'qtd_reposicao'))); ?></h3>
                            <i class="fas fa-dolly fs-3 text-warning"></i>
                        </div>
                        <small class="text-muted">
                            Para cobertura de 30 dias
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráficos -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Distribuição de Cobertura de Estoque</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="coberturaChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Top 10 Produtos por Volume de Vendas</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="vendasChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Explicação dos Cálculos -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Entendendo os Cálculos</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6><i class="fas fa-calculator text-primary me-2"></i> Taxa Diária de Vendas</h6>
                            <p>Calculada dividindo o total de vendas do período pelo número de dias analisados (<?php echo $periodo_dias; ?>). Representa a quantidade média que você vende por dia de cada produto.</p>
                        </div>
                        
                        <div class="mb-3">
                            <h6><i class="fas fa-hourglass-half text-primary me-2"></i> Cobertura de Estoque</h6>
                            <p>Indica por quantos dias seu estoque atual durará, considerando a taxa diária de vendas. Calculada dividindo o estoque atual pela taxa diária de vendas.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6><i class="fas fa-exclamation-triangle text-primary me-2"></i> Ponto de Reposição</h6>
                            <p>Quantidade mínima de estoque que, quando atingida, indica que você deve iniciar o processo de reposição. Calculada multiplicando a taxa diária pelo tempo de reposição (lead time) e por um fator de segurança.</p>
                        </div>
                        
                        <div>
                            <h6><i class="fas fa-dolly text-primary me-2"></i> Estoque Sugerido</h6>
                            <p>Quantidade ideal para manter em estoque, baseada na taxa diária de vendas, multiplicada pelo período desejado (30 ou 60 dias) e por um fator de segurança para evitar rupturas.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabela de Anúncios -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Análise de Cobertura - Produtos Mercado Livre Full</h5>
                <span class="badge bg-primary">
                    <?php echo count($anuncios_full); ?> produtos
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-fixed-header">
                    <table class="table table-hover align-middle mb-0" id="anunciosTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px">Imagem</th>
                                <th>Produto</th>
                                <th class="text-end">Preço</th>
                                <th class="text-center">Estoque Atual</th>
                                <th class="text-center">Vendas (<?php echo $periodo_dias; ?> dias)</th>
                                <th class="text-center">Taxa Diária</th>
                                <th class="text-center">Cobertura (dias)</th>
                                <th class="text-center">Ponto de Reposição</th>
                                <th class="text-center">Estoque Sugerido (30d)</th>
                                <th class="text-center">Estoque Sugerido (60d)</th>
                                <th class="text-center">Reposição Sugerida</th>
                                <th class="no-print" style="width: 80px">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($anuncios_full as $anuncio): ?>
                                <?php
                                // Definir classe para badge de cobertura
                                $cobertura_class = 'cobertura-' . $anuncio['status_cobertura'];
                                ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($anuncio['thumbnail'])): ?>
                                            <img src="<?php echo htmlspecialchars($anuncio['thumbnail']); ?>" alt="<?php echo htmlspecialchars($anuncio['titulo']); ?>" class="anuncio-img">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center anuncio-img">
                                                <i class="fas fa-image text-secondary"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($anuncio['titulo']); ?></div>
                                        <?php if (!empty($anuncio['sku'])): ?>
                                            <small class="text-muted d-block">SKU: <?php echo htmlspecialchars($anuncio['sku']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?php echo formatarPreco($anuncio['preco']); ?></td>
                                    <td class="text-center">
                                        <?php echo formatarNumero($anuncio['estoque_atual']); ?>
                                        <?php if ($anuncio['estoque_atual'] <= $anuncio['ponto_reposicao'] && $anuncio['vendas_periodo'] > 0): ?>
                                            <span class="badge bg-danger ms-1" title="Abaixo do ponto de reposição">
                                                <i class="fas fa-exclamation-circle"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo formatarNumero($anuncio['vendas_periodo']); ?></td>
                                    <td class="text-center"><?php echo formatarNumero($anuncio['taxa_diaria'], 1); ?></td>
                                    <td class="text-center">
                                        <span class="cobertura-badge <?php echo $cobertura_class; ?>">
                                            <?php 
                                            if ($anuncio['cobertura_dias'] > 999) {
                                                echo '∞';
                                            } else {
                                                echo formatarNumero($anuncio['cobertura_dias'], 1);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo formatarNumero($anuncio['ponto_reposicao']); ?></td>
                                    <td class="text-center"><?php echo formatarNumero($anuncio['estoque_sugerido_30']); ?></td>
                                    <td class="text-center"><?php echo formatarNumero($anuncio['estoque_sugerido_60']); ?></td>
                                    <td class="text-center">
                                        <?php if ($anuncio['qtd_reposicao'] > 0): ?>
                                            <span class="badge bg-warning text-dark">
                                                <?php echo formatarNumero($anuncio['qtd_reposicao']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                OK
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <div class="btn-group">
                                            <a href="<?php echo $anuncio['permalink']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver no Mercado Livre">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editarEstoque('<?php echo $anuncio['id']; ?>')" title="Editar Estoque">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (count($anuncios_full) > 10): ?>
            <div class="card-footer bg-white no-print">
                <div class="row">
                    <div class="col">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="tabelaBusca" class="form-control" placeholder="Filtrar produtos...">
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Recomendações de Estoque -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Recomendações para Gestão de Estoque Full</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="alert alert-danger">
                            <h6 class="alert-heading"><i class="fas fa-exclamation-circle me-2"></i> Cobertura Baixa (< 15 dias)</h6>
                            <p class="mb-0">Produtos com cobertura baixa precisam de reposição imediata para evitar ruptura de estoque. Priorize estes itens no seu plano de compras.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-success">
                            <h6 class="alert-heading"><i class="fas fa-check-circle me-2"></i> Cobertura Normal (15-60 dias)</h6>
                            <p class="mb-0">Produtos com cobertura ideal estão bem dimensionados. Monitore regularmente e planeje reposições quando se aproximarem do ponto de reposição.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-warning">
                            <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i> Cobertura Alta (> 60 dias)</h6>
                            <p class="mb-0">Produtos com estoque excessivo podem representar capital parado. Considere promoções ou reduzir as próximas compras destes itens.</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <h6>Estratégias de Gestão de Estoque Full</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul>
                                <li><strong>Planejamento de Compras:</strong> Para produtos com alta rotação, considere implementar um sistema de reposição automática quando atingirem o ponto de reposição.</li>
                                <li><strong>Produtos Sazonais:</strong> Ajuste o estoque sugerido considerando a sazonalidade. O cálculo padrão pode não refletir picos de demanda em datas especiais.</li>
                                <li><strong>Gestão de Fornecedores:</strong> Mapeie o lead time de cada fornecedor para ajustar individualmente os pontos de reposição de cada produto.</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul>
                                <li><strong>Estoque de Segurança:</strong> Para produtos críticos ou com alta variabilidade de demanda, aumente o fator de segurança para evitar rupturas.</li>
                                <li><strong>Produtos sem Vendas:</strong> Avalie a continuidade de produtos que não tiveram vendas no período. Considere promoções ou descontinuação.</li>
                                <li><strong>Custos de Armazenagem Full:</strong> Lembre-se que o serviço Full cobra por armazenagem. Evite manter estoque excessivo de produtos de baixo giro.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
            <?php if ($relatorio_gerado): ?>
            <div class="alert alert-info text-center p-4 mb-4">
                <i class="fas fa-info-circle fa-3x mb-3"></i>
                <h4>Nenhum Anúncio Full Encontrado</h4>
                <p class="mb-0">Não foram encontrados anúncios utilizando o serviço Mercado Livre Full que atendam aos critérios de busca.</p>
            </div>
            <?php else: ?>
            <div class="alert alert-info text-center p-4 mb-4">
                <i class="fas fa-info-circle fa-3x mb-3"></i>
                <h4>Relatório não gerado</h4>
                <p class="mb-0">Selecione os parâmetros desejados e clique em "Gerar Relatório" para analisar a cobertura de estoque dos seus produtos Full.</p>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">O que é Mercado Livre Full?</h5>
                    <p>O <strong>Mercado Livre Full</strong> é um serviço de fulfillment que permite que vendedores armazenem seus produtos nos centros de distribuição do Mercado Livre. Com este serviço:</p>
                    <ul>
                        <li>O Mercado Livre cuida de todo o processo logístico, desde o armazenamento até a entrega ao cliente final</li>
                        <li>Seus produtos ganham o selo "Full" e têm destaque nas buscas</li>
                        <li>As entregas são mais rápidas, aumentando a satisfação dos clientes</li>
                        <li>Você economiza tempo e recursos com embalagem e envio</li>
                    </ul>
                    <p>Este relatório ajuda você a gerenciar seu estoque no Full, calculando taxas de vendas, cobertura de estoque e pontos de reposição para que você nunca fique sem produtos disponíveis.</p>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Modal para Editar Estoque -->
<div class="modal fade" id="editarEstoqueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Estoque</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarEstoque">
                    <input type="hidden" id="anuncio_id" name="anuncio_id">
                    <div class="mb-3">
                        <label for="quantidade" class="form-label">Nova Quantidade</label>
                        <input type="number" class="form-control" id="quantidade" name="quantidade" min="0" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Atualizar Estoque</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($relatorio_gerado && !empty($anuncios_full)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dados para o gráfico de distribuição de cobertura
        const coberturaCtx = document.getElementById('coberturaChart').getContext('2d');
        
        // Agrupar anúncios por faixas de cobertura
        const semCobertura = <?php echo count(array_filter($anuncios_full, function($a) { return $a['vendas_periodo'] == 0; })); ?>;
        const baixaCobertura = <?php echo count(array_filter($anuncios_full, function($a) { return $a['vendas_periodo'] > 0 && $a['cobertura_dias'] < 15; })); ?>;
        const normalCobertura = <?php echo count(array_filter($anuncios_full, function($a) { return $a['vendas_periodo'] > 0 && $a['cobertura_dias'] >= 15 && $a['cobertura_dias'] <= 60; })); ?>;
        const altaCobertura = <?php echo count(array_filter($anuncios_full, function($a) { return $a['vendas_periodo'] > 0 && $a['cobertura_dias'] > 60; })); ?>;
        
        const coberturaChart = new Chart(coberturaCtx, {
            type: 'pie',
            data: {
                labels: ['Sem Vendas', 'Cobertura Baixa (<15 dias)', 'Cobertura Normal (15-60 dias)', 'Cobertura Alta (>60 dias)'],
                datasets: [{
                    data: [semCobertura, baixaCobertura, normalCobertura, altaCobertura],
                    backgroundColor: [
                        '#6c757d',
                        '#dc3545',
                        '#28a745',
                        '#ffc107'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
				plugins: { legend: { position: 'right', labels: { padding: 20 } }, tooltip: { callbacks: { label: function(context) { const label = context.label || ''; const value = context.raw || 0; const total = context.dataset.data.reduce((acc, val) => acc + val, 0); const percentage = Math.round((value / total) * 100) + '%'; return ${label}: ${value} (${percentage}); } } } } } });
				
				    // Dados para o gráfico de top produtos por vendas
        const vendasCtx = document.getElementById('vendasChart').getContext('2d');
        
        // Ordenar anúncios por vendas e pegar os top 10
        const anunciosOrdenados = <?php echo json_encode($anuncios_full); ?>.sort((a, b) => b.vendas_periodo - a.vendas_periodo).slice(0, 10);
        
        const vendasChart = new Chart(vendasCtx, {
            type: 'bar',
            data: {
                labels: anunciosOrdenados.map(a => {
                    // Truncar título para caber melhor no gráfico
                    let titulo = a.titulo;
                    if (titulo.length > 30) {
                        titulo = titulo.substring(0, 30) + '...';
                    }
                    return titulo;
                }),
                datasets: [{
                    label: 'Vendas no Período',
                    data: anunciosOrdenados.map(a => a.vendas_periodo),
                    backgroundColor: '#007bff',
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            title: function(tooltipItems) {
                                const idx = tooltipItems[0].dataIndex;
                                // Mostrar título completo no tooltip
                                return anunciosOrdenados[idx].titulo;
                            }
                        }
                    }
                }
            }
        });
        
        // Busca na tabela
        const tabelaBusca = document.getElementById('tabelaBusca');
        if (tabelaBusca) {
            tabelaBusca.addEventListener('keyup', function() {
                const termo = this.value.toLowerCase();
                const tabela = document.getElementById('anunciosTable');
                const linhas = tabela.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
                
                for (let i = 0; i < linhas.length; i++) {
                    const textoLinha = linhas[i].textContent.toLowerCase();
                    if (textoLinha.indexOf(termo) > -1) {
                        linhas[i].style.display = "";
                    } else {
                        linhas[i].style.display = "none";
                    }
                }
            });
        }
        
        // Modal para editar estoque
        window.editarEstoque = function(anuncioId) {
            document.getElementById('anuncio_id').value = anuncioId;
            
            // Buscar quantidade atual no DOM
            const tabela = document.getElementById('anunciosTable');
            const linhas = tabela.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < linhas.length; i++) {
                const celulas = linhas[i].getElementsByTagName('td');
                const linkCelula = celulas[celulas.length - 1].querySelector('button[onclick^="editarEstoque"]');
                
                if (linkCelula && linkCelula.getAttribute('onclick').includes(anuncioId)) {
                    // Encontrou a linha do anúncio
                    const qtdAtual = parseInt(celulas[3].textContent.trim());
                    document.getElementById('quantidade').value = qtdAtual;
                    break;
                }
            }
            
            const modal = new bootstrap.Modal(document.getElementById('editarEstoqueModal'));
            modal.show();
        };
        
        // Formulário de edição de estoque
        document.getElementById('formEditarEstoque').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const anuncioId = document.getElementById('anuncio_id').value;
            const quantidade = document.getElementById('quantidade').value;
            
            // Aqui você implementaria a chamada AJAX para atualizar o estoque
            // Por exemplo, usando fetch:
            
            /* 
            fetch('<?php echo $base_url; ?>/api/atualizar_estoque.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    anuncio_id: anuncioId,
                    quantidade: quantidade
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Estoque atualizado com sucesso!');
                    window.location.reload();
                } else {
                    alert('Erro ao atualizar estoque: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erro ao processar solicitação: ' + error);
            });
            */
            
            // Como não temos a API implementada, apenas simulamos o sucesso:
            alert('Função em desenvolvimento. O estoque seria atualizado para ' + quantidade + ' unidades.');
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('editarEstoqueModal'));
            modal.hide();
        });
    });
    
    // Função para exportar dados para CSV
    function exportarCSV() {
        let csv = 'ID,Título,SKU,Preço,Estoque Atual,Vendas no Período,Taxa Diária,Cobertura (dias),Ponto de Reposição,Estoque Sugerido (30d),Estoque Sugerido (60d),Reposição Sugerida\n';
        
        const anuncios = <?php echo json_encode($anuncios_full); ?>;
        
        anuncios.forEach(anuncio => {
            const titulo = anuncio.titulo.replace(/"/g, '""');
            const sku = anuncio.sku ? anuncio.sku.replace(/"/g, '""') : '';
            const preco = anuncio.preco;
            const estoque = anuncio.estoque_atual;
            const vendas = anuncio.vendas_periodo;
            const taxa = anuncio.taxa_diaria;
            const cobertura = anuncio.cobertura_dias > 999 ? 'Infinito' : anuncio.cobertura_dias;
            const ponto = anuncio.ponto_reposicao;
            const sugerido30 = anuncio.estoque_sugerido_30;
            const sugerido60 = anuncio.estoque_sugerido_60;
            const reposicao = anuncio.qtd_reposicao;
            
            csv += `${anuncio.id},"${titulo}","${sku}",${preco},${estoque},${vendas},${taxa},${cobertura},${ponto},${sugerido30},${sugerido60},${reposicao}\n`;
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', 'estoque_full_<?php echo date('Y-m-d'); ?>.csv');
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
<?php endif; ?>