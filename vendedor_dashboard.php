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

// Buscar estatísticas do vendedor
try {
    // Total de produtos cadastrados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $total_produtos = $stmt->fetchColumn();
    
    // Total de anúncios no Mercado Livre
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM anuncios_ml WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $total_anuncios = $stmt->fetchColumn();
    
    // Total de vendas nos últimos 30 dias
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vendas WHERE usuario_id = ? AND data_venda >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute([$usuario_id]);
    $total_vendas_30d = $stmt->fetchColumn();
    
    // Total de faturamento nos últimos 30 dias
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(valor_total), 0) FROM vendas WHERE usuario_id = ? AND data_venda >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute([$usuario_id]);
    $faturamento_30d = $stmt->fetchColumn();
    
    // Vendas recentes
    $sql = "SELECT v.id, v.data_venda, v.valor_total, v.status, 
                   p.nome as produto_nome, a.titulo as anuncio_titulo
            FROM vendas v
            LEFT JOIN produtos p ON v.produto_id = p.id
            LEFT JOIN anuncios_ml a ON v.anuncio_id = a.id
            WHERE v.usuario_id = ?
            ORDER BY v.data_venda DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $vendas_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $erro = "Erro ao buscar dados: " . $e->getMessage();
}

// Exibir mensagem de sessão, se existir
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'] ?? 'info';
    
    // Limpar a mensagem da sessão
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}

function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Vendedor - CalcMeli</title>
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
            border-left: 4px solid;
            border-radius: 4px;
            transition: transform 0.3s;
        }
        .card-dashboard:hover {
            transform: translateY(-5px);
        }
        .card-produtos {
            border-left-color: #28a745;
        }
        .card-anuncios {
            border-left-color: #007bff;
        }
        .card-vendas {
            border-left-color: #fd7e14;
        }
        .card-faturamento {
            border-left-color: #20c997;
        }
        .dashboard-icon {
            font-size: 2.2rem;
            opacity: 0.8;
        }
        .table th {
            background-color: #f8f9fa;
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

    <!-- Conteúdo principal -->
    <main class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">Dashboard</h1>
                <div>
                    <a href="<?php echo $base_url; ?>/vendedor_produtos.php?add=1" class="btn btn-warning">
                        <i class="fas fa-plus"></i> Novo Produto
                    </a>
                </div>
            </div>

            <?php if (isset($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensagem); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Estatísticas -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card card-dashboard card-produtos h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted">Produtos</h6>
                                    <h2 class="my-2"><?php echo number_format($total_produtos); ?></h2>
                                    <p class="mb-0">
                                        <a href="<?php echo $base_url; ?>/vendedor_produtos.php" class="text-decoration-none">
                                            Ver todos <i class="fas fa-chevron-right small"></i>
                                        </a>
                                    </p>
                                </div>
                                <div class="text-success dashboard-icon">
                                    <i class="fas fa-box"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card card-dashboard card-anuncios h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted">Anúncios ML</h6>
                                    <h2 class="my-2"><?php echo number_format($total_anuncios); ?></h2>
                                    <p class="mb-0">
                                        <a href="<?php echo $base_url; ?>/vendedor_anuncios.php" class="text-decoration-none">
                                            Ver todos <i class="fas fa-chevron-right small"></i>
                                        </a>
                                    </p>
                                </div>
                                <div class="text-primary dashboard-icon">
                                    <i class="fas fa-tags"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card card-dashboard card-vendas h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted">Vendas (30 dias)</h6>
                                    <h2 class="my-2"><?php echo number_format($total_vendas_30d); ?></h2>
                                    <p class="mb-0">
                                        <a href="<?php echo $base_url; ?>/vendedor_vendas.php" class="text-decoration-none">
                                            Ver todas <i class="fas fa-chevron-right small"></i>
                                        </a>
                                    </p>
                                </div>
                                <div class="text-warning dashboard-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card card-dashboard card-faturamento h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted">Faturamento (30 dias)</h6>
                                    <h2 class="my-2"><?php echo formatCurrency($faturamento_30d); ?></h2>
                                    <p class="mb-0">
                                        <a href="<?php echo $base_url; ?>/vendedor_relatorios.php" class="text-decoration-none">
                                            Ver relatórios <i class="fas fa-chevron-right small"></i>
                                        </a>
                                    </p>
                                </div>
                                <div class="text-info dashboard-icon">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Vendas recentes -->
                <div class="col-lg-8 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Vendas Recentes</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($vendas_recentes)): ?>
                                <p class="text-muted text-center my-4">Nenhuma venda recente encontrada.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Data</th>
                                                <th>Produto</th>
                                                <th>Valor</th>
                                                <th>Status</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($vendas_recentes as $venda): ?>
                                                <tr>
                                                    <td><?php echo $venda['id']; ?></td>
                                                    <td><?php echo formatDate($venda['data_venda']); ?></td>
                                                    <td>
                                                        <?php 
                                                        if (!empty($venda['produto_nome'])) {
                                                            echo htmlspecialchars($venda['produto_nome']);
                                                        } elseif (!empty($venda['anuncio_titulo'])) {
                                                            echo htmlspecialchars($venda['anuncio_titulo']);
                                                        } else {
                                                            echo '<span class="text-muted">N/A</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo formatCurrency($venda['valor_total']); ?></td>
                                                    <td>
                                                        <?php
                                                        $status_class = '';
                                                        switch (strtolower($venda['status'])) {
                                                            case 'paid':
                                                            case 'pago':
                                                                $status_class = 'success';
                                                                break;
                                                            case 'pending':
                                                            case 'pendente':
                                                                $status_class = 'warning';
                                                                break;
                                                            case 'cancelled':
                                                            case 'cancelado':
                                                                $status_class = 'danger';
                                                                break;
                                                            default:
                                                                $status_class = 'secondary';
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?php echo $status_class; ?>">
                                                            <?php echo htmlspecialchars(ucfirst($venda['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="<?php echo $base_url; ?>/vendedor_vendas_detalhes.php?id=<?php echo $venda['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Links rápidos -->
                <div class="col-lg-4 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Links Rápidos</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <a href="<?php echo $base_url; ?>/adicionar_produto.php" class="list-group-item list-group-item-action d-flex align-items-center">
                                    <span class="bg-success bg-opacity-10 rounded-circle p-2 me-3">
                                        <i class="fas fa-plus text-success"></i>
                                    </span>
                                    <span>Adicionar Produto</span>
                                </a>
                                <a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php" class="list-group-item list-group-item-action d-flex align-items-center">
                                    <span class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                                        <i class="fas fa-sync text-primary"></i>
                                    </span>
                                    <span>Sincronizar com Mercado Livre</span>
                                </a>
                                <a href="<?php echo $base_url; ?>/importar_produtos.php" class="list-group-item list-group-item-action d-flex align-items-center">
                                    <span class="bg-warning bg-opacity-10 rounded-circle p-2 me-3">
                                        <i class="fas fa-file-import text-warning"></i>
                                    </span>
                                    <span>Importar Produtos</span>
                                </a>
                                <a href="<?php echo $base_url; ?>/vendedor_relatorios.php" class="list-group-item list-group-item-action d-flex align-items-center">
                                    <span class="bg-info bg-opacity-10 rounded-circle p-2 me-3">
                                        <i class="fas fa-chart-bar text-info"></i>
                                    </span>
                                    <span>Gerar Relatório</span>
                                </a>
                                <a href="<?php echo $base_url; ?>/vendedor_config.php" class="list-group-item list-group-item-action d-flex align-items-center">
                                    <span class="bg-secondary bg-opacity-10 rounded-circle p-2 me-3">
                                        <i class="fas fa-cog text-secondary"></i>
                                    </span>
                                    <span>Configurações da Conta</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
