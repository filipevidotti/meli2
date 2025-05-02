<?php
// Configuração básica - mostrar erros para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: emergency_login.php");
    exit;
}

// Definições básicas
$base_url = 'http://www.annemacedo.com.br/novo2';

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
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    echo "Erro na conexão com o banco de dados: " . $e->getMessage();
    exit;
}

// Obter informações básicas
$usuario_id = $_SESSION['user_id'];
$usuario_tipo = $_SESSION['user_type'];
$usuario_nome = $_SESSION['user_name'];

// Obter ou criar vendedor_id se for vendedor
$vendedor_id = 0;
if ($usuario_tipo === 'vendedor') {
    $stmt = $pdo->prepare("SELECT id FROM vendedores WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $vendedor = $stmt->fetch();
    
    if ($vendedor) {
        $vendedor_id = $vendedor['id'];
    } else {
        // Criar vendedor automaticamente
        $stmt = $pdo->prepare("INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)");
        $stmt->execute([$usuario_id, $usuario_nome]);
        $vendedor_id = $pdo->lastInsertId();
    }
}

// Consultar informações básicas sobre vendas
$total_vendas = 0;
$lucro_total = 0;
$num_vendas = 0;

if ($vendedor_id > 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_count, SUM(valor_venda) as valor_total, SUM(lucro) as lucro_total FROM vendas WHERE vendedor_id = ?");
    $stmt->execute([$vendedor_id]);
    $resumo = $stmt->fetch();
    
    $total_vendas = $resumo['valor_total'] ?? 0;
    $lucro_total = $resumo['lucro_total'] ?? 0;
    $num_vendas = $resumo['total_count'] ?? 0;
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
            padding-top: 70px;
        }
        .sidebar {
            min-height: calc(100vh - 70px);
            background-color: #343a40;
            color: white;
        }
        .sidebar .nav-link {
            color: #ced4da;
        }
        .sidebar .nav-link:hover {
            color: #fff;
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #007bff;
        }
        .card-dashboard {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .card-dashboard:hover {
            transform: translateY(-5px);
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
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="direct_index.php">Dashboard</a>
                    </li>
                    <?php if ($usuario_tipo === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_url; ?>/admin/index.php">Admin</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($usuario_nome); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="#">Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="emergency_login.php?logout=1">Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="direct_index.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-chart-line"></i> Dashboard Original
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-shopping-cart"></i> Vendas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-box"></i> Produtos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-calculator"></i> Calculadora ML
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                </div>
                
                <!-- Alert for emergency login -->
                <div class="alert alert-info">
                    <h4><i class="fas fa-info-circle"></i> Modo de Acesso Emergencial</h4>
                    <p>Você está usando o modo de acesso emergencial do CalcMeli. Se o sistema principal já estiver funcionando, você pode acessá-lo normalmente clicando em "Dashboard Original" no menu lateral.</p>
                </div>
                
                <!-- Dashboard cards -->
                <div class="row mt-4">
                    <div class="col-md-4 mb-4">
                        <div class="card card-dashboard bg-primary text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-money-bill-wave"></i> Total de Vendas</h5>
                                <h2 class="display-6">R$ <?php echo number_format($total_vendas, 2, ',', '.'); ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="card card-dashboard bg-success text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-chart-line"></i> Lucro Total</h5>
                                <h2 class="display-6">R$ <?php echo number_format($lucro_total, 2, ',', '.'); ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4">
                        <div class="card card-dashboard bg-info text-white h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-shopping-bag"></i> Número de Vendas</h5>
                                <h2 class="display-6"><?php echo $num_vendas; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Status do sistema -->
                <div class="card mt-4 mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-server"></i> Status do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Status da Sessão</h6>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        ID do Usuário
                                        <span class="badge bg-primary"><?php echo $usuario_id; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Tipo de Usuário
                                        <span class="badge bg-info"><?php echo $usuario_tipo; ?></span>
                                    </li>
                                    <?php if ($vendedor_id > 0): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        ID do Vendedor
                                        <span class="badge bg-success"><?php echo $vendedor_id; ?></span>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Ferramentas de Diagnóstico</h6>
                                <div class="list-group">
                                    <a href="test.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-vial"></i> Teste do Sistema
                                    </a>
                                    <a href="fix_vendedores.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-user-check"></i> Verificar Vendedores
                                    </a>
                                    <a href="check_passwords.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-key"></i> Verificar Senhas
                                    </a>
                                    <a href="emergency_login.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-door-open"></i> Login Emergencial
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
