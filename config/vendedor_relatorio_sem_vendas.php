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
$anuncios_sem_vendas = [];
$relatorio_gerado = false;
$erro_api = false;
$mensagem_erro = '';
$total_anuncios = 0;
$total_anuncios_sem_vendas = 0;
$total_visitas = 0;
$periodo_dias = isset($_GET['periodo']) ? intval($_GET['periodo']) : 30;
$categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$ordenar_por = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'visitas_desc';

// Verificar se está solicitando a geração do relatório
if (isset($_GET['gerar']) && $_GET['gerar'] == '1') {
    try {
        // Definir período para busca de vendas
        $data_fim = new DateTime();
        $data_inicio = clone $data_fim;
        $data_inicio->modify("-{$periodo_dias} days");
        
        // Buscar todos os anúncios ativos do vendedor
        $offset = 0;
        $limit = 50;
        $has_more = true;
        $todos_anuncios = [];
        
        while ($has_more) {
            $response = mercadoLivreGet("/users/{$ml_seller_id}/items/search?status=active&limit={$limit}&offset={$offset}", $access_token);
            
            if ($response['status_code'] == 200 && isset($response['data']['results'])) {
                $item_ids = $response['data']['results'];
                
                foreach ($item_ids as $item_id) {
                    // Buscar detalhes do anúncio
                    $item_response = mercadoLivreGet("/items/{$item_id}", $access_token);
                    
                    if ($item_response['status_code'] == 200) {
                        $item_data = $item_response['data'];
                        
                        // Aplicar filtro de categoria se necessário
                        if (!empty($categoria) && $item_data['category_id'] != $categoria) {
                            continue;
                        }
                        
                        $todos_anuncios[] = $item_data;
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
            // Buscar vendas no período
            $vendas_periodo = buscarOrdensVendedor(
                $ml_seller_id,
                $access_token,
                'paid',
                $data_inicio->format('Y-m-d'),
                $data_fim->format('Y-m-d')
            );
            
            // Criar array com IDs de produtos vendidos no período
            $produtos_vendidos = [];
            if ($vendas_periodo['total'] > 0) {
                foreach ($vendas_periodo['ordens'] as $ordem) {
                    if (!empty($ordem['order_items'])) {
                        foreach ($ordem['order_items'] as $item) {
                            $produtos_vendidos[] = $item['item']['id'];
                        }
                    }
                }
            }
            
            // Filtrar anúncios sem vendas no período
            foreach ($todos_anuncios as $anuncio) {
                if (!in_array($anuncio['id'], $produtos_vendidos)) {
                    // Buscar estatísticas de visitas
                    $visitas_response = mercadoLivreGet("/items/{$anuncio['id']}/visits/time_window?last={$periodo_dias}d", $access_token);
                    
                    $total_visitas_anuncio = 0;
                    $visitas_por_dia = [];
                    
                    if ($visitas_response['status_code'] == 200) {
                        $total_visitas_anuncio = $visitas_response['data']['total_visits'] ?? 0;
                        $visitas_por_dia = $visitas_response['data']['results'] ?? [];
                    }
                    
                    // Buscar informações do produto no banco de dados
                    $produto_info = null;
                    try {
                        $stmt = $pdo->prepare("
                            SELECT p.nome, p.custo, a.produto_id 
                            FROM anuncios_ml a
                            LEFT JOIN produtos p ON a.produto_id = p.id
                            WHERE a.ml_item_id = ? AND a.usuario_id = ?
                        ");
                        $stmt->execute([$anuncio['id'], $usuario_id]);
                        $produto_info = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        // Silenciar erro
                    }
                    
                    // Adicionar anúncio à lista
                    $anuncios_sem_vendas[] = [
                        'id' => $anuncio['id'],
                        'titulo' => $anuncio['title'],
                        'preco' => $anuncio['price'],
                        'quantidade' => $anuncio['available_quantity'],
                        'permalink' => $anuncio['permalink'],
                        'thumbnail' => $anuncio['thumbnail'],
                        'categoria_id' => $anuncio['category_id'],
                        'status' => $anuncio['status'],
                        'health' => $anuncio['seller_address']['city']['name'] ?? 'N/A',
                        'listing_type' => $anuncio['listing_type_id'],
                        'data_criacao' => $anuncio['date_created'],
                        'visitas' => $total_visitas_anuncio,
                        'visitas_por_dia' => $visitas_por_dia,
                        'produto_nome' => $produto_info['nome'] ?? null,
                        'produto_id' => $produto_info['produto_id'] ?? null,
                        'custo' => $produto_info['custo'] ?? null
                    ];
                    
                    $total_visitas += $total_visitas_anuncio;
                }
            }
            
            $total_anuncios_sem_vendas = count($anuncios_sem_vendas);
            
            // Ordenar anúncios conforme solicitado
            switch ($ordenar_por) {
                case 'visitas_asc':
                    usort($anuncios_sem_vendas, function($a, $b) {
                        return $a['visitas'] <=> $b['visitas'];
                    });
                    break;
                case 'visitas_desc':
                    usort($anuncios_sem_vendas, function($a, $b) {
                        return $b['visitas'] <=> $a['visitas'];
                    });
                    break;
                case 'preco_asc':
                    usort($anuncios_sem_vendas, function($a, $b) {
                        return $a['preco'] <=> $b['preco'];
                    });
                    break;
                case 'preco_desc':
                    usort($anuncios_sem_vendas, function($a, $b) {
                        return $b['preco'] <=> $a['preco'];
                    });
                    break;
                case 'data_asc':
                    usort($anuncios_sem_vendas, function($a, $b) {
                        return strtotime($a['data_criacao']) <=> strtotime($b['data_criacao']);
                    });
                    break;
                case 'data_desc':
                    usort($anuncios_sem_vendas, function($a, $b) {
                        return strtotime($b['data_criacao']) <=> strtotime($a['data_criacao']);
                    });
                    break;
                case 'titulo_asc':
                    usort($anuncios_sem_vendas, function($a, $b) {
                        return strcmp($a['titulo'], $b['titulo']);
                    });
                    break;
                case 'titulo_desc':
                    usort($anuncios_sem_vendas, function($a, $b) {
                        return strcmp($b['titulo'], $a['titulo']);
                    });
                    break;
            }
            
            $relatorio_gerado = true;
            
            if ($total_anuncios_sem_vendas == 0) {
                $mensagem = "Todos os seus anúncios tiveram pelo menos uma venda no período de {$periodo_dias} dias. Parabéns!";
                $tipo_mensagem = "success";
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

function formatarNumero($valor) {
    return number_format($valor, 0, ',', '.');
}

function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

function formatarPorcentagem($valor) {
    return number_format($valor, 2, ',', '.') . '%';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anúncios Sem Vendas - CalcMeli</title>
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
        .alert-pastel-warning {
            background-color: #fff3cd;
            border-color: #ffeeba;
            color: #856404;
        }
        .alert-pastel-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }
        .visitas-badge {
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
            border-radius: 50rem;
        }
        .visitas-baixas {
            background-color: #f8d7da;
            color: #842029;
        }
        .visitas-medias {
            background-color: #fff3cd;
            color: #664d03;
        }
        .visitas-altas {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .listing-type-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }
        .classic {
            background-color: #e2e3e5;
            color: #41464b;
        }
        .premium {
            background-color: #cff4fc;
            color: #055160;
        }
        .free {
            background-color: #fff3cd;
            color: #664d03;
        }
        .gold_special, .gold_pro, .gold {
            background-color: #ffd700;
            color: #000;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
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
    <div class="sidebar">
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h2">Anúncios Sem Vendas</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/vendedor_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/vendedor_relatorios.php">Relatórios</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Anúncios Sem Vendas</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <?php if ($relatorio_gerado && $total_anuncios_sem_vendas > 0): ?>
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
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensagem); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Filtros e Formulário -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Parâmetros do Relatório</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="" class="row g-3">
                        <div class="col-md-4">
                            <label for="periodo" class="form-label">Período de Análise</label>
                            <select class="form-select" id="periodo" name="periodo">
                                <option value="7" <?php echo $periodo_dias == 7 ? 'selected' : ''; ?>>Últimos 7 dias</option>
                                <option value="15" <?php echo $periodo_dias == 15 ? 'selected' : ''; ?>>Últimos 15 dias</option>
                                <option value="30" <?php echo $periodo_dias == 30 ? 'selected' : ''; ?>>Últimos 30 dias</option>
                                <option value="60" <?php echo $periodo_dias == 60 ? 'selected' : ''; ?>>Últimos 60 dias</option>
                                <option value="90" <?php echo $periodo_dias == 90 ? 'selected' : ''; ?>>Últimos 90 dias</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
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
                        
                        <div class="col-md-4">
                            <label for="ordenar" class="form-label">Ordenar por</label>
                            <select class="form-select" id="ordenar" name="ordenar">
                                <option value="visitas_desc" <?php echo $ordenar_por === 'visitas_desc' ? 'selected' : ''; ?>>Mais Visitados</option>
                                <option value="visitas_asc" <?php echo $ordenar_por === 'visitas_asc' ? 'selected' : ''; ?>>Menos Visitados</option>
                                <option value="preco_desc" <?php echo $ordenar_por === 'preco_desc' ? 'selected' : ''; ?>>Maior Preço</option>
                                <option value="preco_asc" <?php echo $ordenar_por === 'preco_asc' ? 'selected' : ''; ?>>Menor Preço</option>
                                <option value="data_desc" <?php echo $ordenar_por === 'data_desc' ? 'selected' : ''; ?>>Mais Recentes</option>
                                <option value="data_asc" <?php echo $ordenar_por === 'data_asc' ? 'selected' : ''; ?>>Mais Antigos</option>
                                <option value="titulo_asc" <?php echo $ordenar_por === 'titulo_asc' ? 'selected' : ''; ?>>Título (A-Z)</option>
                                <option value="titulo_desc" <?php echo $ordenar_por === 'titulo_desc' ? 'selected' : ''; ?>>Título (Z-A)</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <input type="hidden" name="gerar" value="1">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Gerar Relatório
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($relatorio_gerado && $total_anuncios_sem_vendas > 0): ?>
            
            <!-- Resumo do Relatório -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm stats-card h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Total de Anúncios sem Vendas</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="mb-0"><?php echo $total_anuncios_sem_vendas; ?> anúncios</h3>
                                <span class="badge bg-danger rounded-pill"><?php echo formatarPorcentagem(($total_anuncios_sem_vendas / $total_anuncios) * 100); ?></span>
                            </div>
                            <small class="text-muted">
                                De um total de <?php echo $total_anuncios; ?> anúncios ativos
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm stats-card h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Total de Visitas</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="mb-0"><?php echo formatarNumero($total_visitas); ?></h3>
                                <i class="fas fa-eye fs-3 text-primary"></i>
                            </div>
                            <small class="text-muted">
                                Média de <?php echo formatarNumero($total_visitas / $total_anuncios_sem_vendas); ?> visitas por anúncio
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm stats-card h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Período Analisado</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="mb-0">Últimos <?php echo $periodo_dias; ?> dias</h3>
                                <i class="fas fa-calendar-alt fs-3 text-primary"></i>
                            </div>
                            <small class="text-muted">
                                De <?php echo formatarData(date('Y-m-d', strtotime("-{$periodo_dias} days"))); ?> até <?php echo formatarData(date('Y-m-d')); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
			
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
    });
    
    // Função para exportar dados para CSV
    function exportarCSV() {
        let csv = 'ID,Título,Produto,Tipo,Preço,Visitas,Data Criação,Link\n';
        
        const anuncios = <?php echo json_encode($anuncios_sem_vendas); ?>;
        
        anuncios.forEach(anuncio => {
            const titulo = anuncio.titulo.replace(/"/g, '""');
            const produto = anuncio.produto_nome ? anuncio.produto_nome.replace(/"/g, '""') : 'Não vinculado';
            const tipo = anuncio.listing_type;
            const preco = anuncio.preco;
            const visitas = anuncio.visitas;
            const dataCriacao = anuncio.data_criacao.split('T')[0];
            const link = anuncio.permalink;
            
            csv += `${anuncio.id},"${titulo}","${produto}",${tipo},${preco},${visitas},${dataCriacao},"${link}"\n`;
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', 'anuncios_sem_vendas_<?php echo date('Y-m-d'); ?>.csv');
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
<?php endif; ?>