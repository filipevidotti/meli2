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

// Formatar datas para exibição
$data_inicio_display = date('d/m/Y', strtotime($data_inicio));
$data_fim_display = date('d/m/Y', strtotime($data_fim));

// Buscar dados para o dashboard
try {
    // Obter ID do vendedor a partir do usuário
    $sql_vendedor = "SELECT id FROM vendedores WHERE usuario_id = ?";
    $stmt_vendedor = $pdo->prepare($sql_vendedor);
    $stmt_vendedor->execute([$usuario_id]);
    $vendedor = $stmt_vendedor->fetch();
    
    $vendedor_id = $vendedor['id'] ?? 0;
    
    if (!$vendedor_id) {
        throw new Exception("Vendedor não encontrado");
    }
    
    // Métricas de vendas
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
    
    // Métricas de anúncios
    $sql_anuncios = "SELECT 
                        COUNT(*) as total_anuncios,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as anuncios_ativos,
                        SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as anuncios_pausados,
                        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as anuncios_fechados,
                        SUM(CASE WHEN produto_id IS NOT NULL THEN 1 ELSE 0 END) as anuncios_vinculados
                    FROM anuncios_ml
                    WHERE usuario_id = ?";
    
    $stmt_anuncios = $pdo->prepare($sql_anuncios);
    $stmt_anuncios->execute([$usuario_id]);
    $metricas_anuncios = $stmt_anuncios->fetch();
    
    // Top produtos vendidos
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
    
    // Vendas recentes
    $sql_recentes = "SELECT 
                        v.id,
                        v.produto,
                        v.data_venda,
                        v.valor_venda,
                        v.lucro,
                        v.margem_lucro
                    FROM vendas v
                    WHERE v.vendedor_id = ?
                    ORDER BY v.data_venda DESC
                    LIMIT 10";
    
    $stmt_recentes = $pdo->prepare($sql_recentes);
    $stmt_recentes->execute([$vendedor_id]);
    $vendas_recentes = $stmt_recentes->fetchAll();
    
    // Vendas por dia (para gráfico)
    $sql_vendas_dia = "SELECT 
                         DATE_FORMAT(data_venda, '%Y-%m-%d') as dia,
                         COUNT(*) as num_vendas,
                         SUM(valor_venda) as valor_total,
                         SUM(lucro) as lucro_total
                    FROM vendas
                    WHERE vendedor_id = ?
                    AND data_venda BETWEEN ? AND ?
                    GROUP BY dia
                    ORDER BY dia";
    
    $stmt_vendas_dia = $pdo->prepare($sql_vendas_dia);
    $stmt_vendas_dia->execute([$vendedor_id, $data_inicio, $data_fim]);
    $vendas_por_dia = $stmt_vendas_dia->fetchAll();
    
    // Calcular algumas métricas adicionais
    $total_anuncios = $metricas_anuncios['total_anuncios'] ?? 0;
    $percentual_vinculados = ($total_anuncios > 0) ? (($metricas_anuncios['anuncios_vinculados'] ?? 0) / $total_anuncios) * 100 : 0;
    $ticket_medio = ($metricas_vendas['total_vendas'] > 0) ? ($metricas_vendas['valor_total'] / $metricas_vendas['total_vendas']) : 0;
    
    // Verificar status de conexão do Mercado Livre
    $ml_conectado = false;
    $ml_nickname = '';
    
    try {
        $stmt_ml = $pdo->prepare("
            SELECT t.id, t.access_token, u.ml_nickname
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
        }
    } catch (PDOException $e) {
        // Silenciar erro
    }
    
} catch (Exception $e) {
    $mensagem = "Erro ao carregar dados: " . $e->getMessage();
    $tipo_mensagem = "danger";
    
    // Inicializar variáveis para evitar erros
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
        'anuncios_vinculados' => 0
    ];
    
    $top_produtos = [];
    $produtos_sem_vendas = [];
    $vendas_recentes = [];
    $vendas_por_dia = [];
    $percentual_vinculados = 0;
    $ticket_medio = 0;
}

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
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                    <a class="nav-link active" href="<?php echo $base_url; ?>/vendedor_dashboard.php">
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
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_analise_abc.php">
                        <i class="fas fa-chart-pie"></i> Curva ABC
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_relatorios.php">
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
                                <small class="text-success">Conectado como: <?php echo htmlspecialchars($ml_nickname); ?></small>
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
        
        <!-- Estatísticas de Margens e Top Produtos -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Distribuição de Margens</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="margens-chart"></canvas>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Margem Alta (>= 20%)</span>
                                <strong class="text-success"><?php echo $metricas_vendas['vendas_margem_alta'] ?? 0; ?> vendas</strong>
                            </div>
                            <div class="progress mb-3" style="height: 10px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $metricas_vendas['total_vendas'] > 0 ? (($metricas_vendas['vendas_margem_alta'] / $metricas_vendas['total_vendas']) * 100) : 0; ?>%" aria-valuenow="<?php echo $metricas_vendas['vendas_margem_alta'] ?? 0; ?>" aria-valuemin="0" aria-valuemax="<?php echo $metricas_vendas['total_vendas'] ?? 0; ?>"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Margem Média (10% - 20%)</span>
                                <strong class="text-warning"><?php echo $metricas_vendas['vendas_margem_media'] ?? 0; ?> vendas</strong>
                            </div>
                            <div class="progress mb-3" style="height: 10px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $metricas_vendas['total_vendas'] > 0 ? (($metricas_vendas['vendas_margem_media'] / $metricas_vendas['total_vendas']) * 100) : 0; ?>%" aria-valuenow="<?php echo $metricas_vendas['vendas_margem_media'] ?? 0; ?>" aria-valuemin="0" aria-valuemax="<?php echo $metricas_vendas['total_vendas'] ?? 0; ?>"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Margem Baixa (< 10%)</span>
                                <strong class="text-danger"><?php echo $metricas_vendas['vendas_margem_baixa'] ?? 0; ?> vendas</strong>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $metricas_vendas['total_vendas'] > 0 ? (($metricas_vendas['vendas_margem_baixa'] / $metricas_vendas['total_vendas']) * 100) : 0; ?>%" aria-valuenow="<?php echo $metricas_vendas['vendas_margem_baixa'] ?? 0; ?>" aria-valuemin="0" aria-valuemax="<?php echo $metricas_vendas['total_vendas'] ?? 0; ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top 5 Produtos por Faturamento</h5>
                            <a href="<?php echo $base_url; ?>/vendedor_produtos.php" class="btn btn-sm btn-outline-secondary">
                                Ver Todos os Produtos
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
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
        </div>
        
        <!-- Últimas Vendas e Produtos sem Vendas -->
        <div class="row mb-4">
            <div class="col-md-7">
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
                                        <th class="text-end">Valor</th>
                                        <th class="text-end">Lucro</th>
                                        <th class="text-end">Margem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($vendas_recentes)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <i class="fas fa-info-circle text-info me-2"></i>
                                                Nenhuma venda registrada
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($vendas_recentes as $venda): ?>
                                            <tr onclick="window.location='<?php echo $base_url; ?>/vendedor_editar_venda.php?id=<?php echo $venda['id']; ?>'" style="cursor: pointer;">
                                                <td><?php echo formatDate($venda['data_venda']); ?></td>
                                                <td><?php echo htmlspecialchars($venda['produto']); ?></td>
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
            
            <div class="col-md-5">
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
        
        <!-- Estatísticas de anúncios -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Status dos Anúncios</h5>
                            <a href="<?php echo $base_url; ?>/vendedor_anuncios.php" class="btn btn-sm btn-outline-secondary">
                                Gerenciar Anúncios
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
            
            <div class="col-md-6">
                <div class="card h-100">
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
            
            periodoSelect.addEventListener('change', function() {
                if (this.value === 'custom') {
                    customDateRange.style.display = 'block';
                } else {
                    customDateRange.style.display = 'none';
                }
            });
            
            // Gráfico de vendas
            const vendasData = {
                labels: [
                    <?php 
                    foreach ($vendas_por_dia as $venda) {
                        echo "'" . date('d/m', strtotime($venda['dia'])) . "',";
                    }
                    ?>
                ],
                datasets: [
                    {
                        label: 'Faturamento',
                        data: [
                            <?php 
                            foreach ($vendas_por_dia as $venda) {
                                echo $venda['valor_total'] . ",";
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
                            foreach ($vendas_por_dia as $venda) {
                                echo $venda['lucro_total'] . ",";
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
            
            const vendasCtx = document.getElementById('vendas-chart').getContext('2d');
            const vendasChart = new Chart(vendasCtx, {
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
            
            // Gráfico de margens
            const margensData = {
                labels: ['Alta (>= 20%)', 'Média (10-20%)', 'Baixa (< 10%)'],
                datasets: [{
                    data: [
                        <?php echo $metricas_vendas['vendas_margem_alta'] ?? 0; ?>,
                        <?php echo $metricas_vendas['vendas_margem_media'] ?? 0; ?>,
                        <?php echo $metricas_vendas['vendas_margem_baixa'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',  // Verde
                        'rgba(255, 193, 7, 0.7)',  // Amarelo
                        'rgba(220, 53, 69, 0.7)'   // Vermelho
                    ],
                    borderColor: [
                        'rgb(40, 167, 69)',  
                        'rgb(255, 193, 7)',  
                        'rgb(220, 53, 69)'   
                    ],
                    borderWidth: 1
                }]
            };
            
            const margensCtx = document.getElementById('margens-chart').getContext('2d');
            new Chart(margensCtx, {
                type: 'doughnut',
                data: margensData,
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
            
            // Gráfico de anúncios
            const anunciosData = {
                labels: ['Ativos', 'Pausados', 'Finalizados'],
                datasets: [{
                    data: [
                        <?php echo $metricas_anuncios['anuncios_ativos'] ?? 0; ?>,
                        <?php echo $metricas_anuncios['anuncios_pausados'] ?? 0; ?>,
                        <?php echo $metricas_anuncios['anuncios_fechados'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',  // Verde
                        'rgba(255, 193, 7, 0.7)',  // Amarelo
                        'rgba(108, 117, 125, 0.7)' // Cinza
                    ],
                    borderWidth: 0
                }]
            };
            
            const anunciosCtx = document.getElementById('anuncios-chart').getContext('2d');
            new Chart(anunciosCtx, {
                type: 'pie',
                data: anunciosData,
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
        });
    </script>
</body>
</html>
