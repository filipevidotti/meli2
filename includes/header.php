<?php
// Verificar proteção da página
if (!defined('BASE_PATH')) {
    die('Acesso direto não permitido');
}

// Verificar se o usuário é administrador
if (!isAdmin()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Administração'; ?> - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
    <style>
        /* Estilos específicos para o admin */
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-custom {
            background-color: #343a40;
        }
        .navbar-custom .navbar-brand {
            color: #fff159;
            font-weight: bold;
        }
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #343a40;
            color: #fff;
            padding-top: 20px;
        }
        .sidebar .nav-link {
            color: #ced4da;
            padding: 10px 20px;
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255,255,255,0.1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #007bff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .content-wrapper {
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #ff9a00;
            border-color: #ff9a00;
        }
        .btn-primary:hover {
            background-color: #e08a00;
            border-color: #e08a00;
        }
    </style>
</head>
<body>
    <!-- Barra de Navegação Superior -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/admin/index.php">CalcMeli Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarAdmin" aria-controls="navbarAdmin" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarAdmin">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/perfil.php"><i class="fas fa-user"></i> Perfil</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/configuracoes.php"><i class="fas fa-cog"></i> Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar / Menu Lateral -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="list-group">
                    <a href="<?php echo BASE_URL; ?>/admin/index.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/vendedores.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'vendedores.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Vendedores
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/vendas.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'vendas.php' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i> Vendas
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/categorias.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'categorias.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tags"></i> Categorias ML
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/relatorios.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'relatorios.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i> Relatórios
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/configuracoes.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) === 'configuracoes.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cogs"></i> Configurações
                    </a>
                </div>
            </div>

            <!-- Conteúdo Principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-wrapper">
