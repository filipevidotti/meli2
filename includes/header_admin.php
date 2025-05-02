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
    <style>
        /* Estilos gerais */
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Barra de navegação */
        .navbar-custom {
            background-color: #343a40;
        }
        .navbar-custom .navbar-brand {
            color: #fff159;
            font-weight: bold;
        }
        
        /* Menu lateral */
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #343a40;
            color: #fff;
            padding-top: 15px;
            width: 250px;
            position: fixed;
            left: 0;
            top: 56px;
            bottom: 0;
            z-index: 100;
            overflow-y: auto;
        }
        
        /* Conteúdo principal */
        .content-wrapper {
            margin-left: 250px;
            padding: 20px;
        }
        
        /* Itens do menu */
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            position: relative;
        }
        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: #ced4da;
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar-menu a:hover {
            color: #fff;
            background-color: rgba(255,255,255,0.1);
        }
        .sidebar-menu a.active {
            color: #fff;
            background-color: #007bff;
        }
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Submenus */
        .sidebar-submenu {
            list-style: none;
            padding-left: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .sidebar-submenu.open {
            max-height: 500px;
            transition: max-height 0.5s ease-in;
        }
        .sidebar-submenu a {
            padding-left: 50px;
            font-size: 0.9em;
        }
        .menu-arrow {
            float: right;
            transition: all 0.3s;
        }
        .menu-arrow.rotated {
            transform: rotate(90deg);
        }
        
        /* Componentes UI */
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
        
        /* Responsivo */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
                position: fixed;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .content-wrapper {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Barra de Navegação Superior -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/admin/index.php">CalcMeli Admin</a>
            <button class="navbar-toggler" type="button" id="sidebarToggler">
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

    <div class="container-fluid" style="margin-top: 56px;">
        <div class="row">
            <!-- Sidebar / Menu Lateral -->
            <div class="sidebar" id="sidebar">
                <ul class="sidebar-menu">
                    <!-- Dashboard -->
                    <li>
                        <a href="<?php echo BASE_URL; ?>/admin/index.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    
                    <!-- Vendedores -->
                    <li class="has-submenu">
                        <a href="#" class="submenu-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['vendedores.php', 'adicionar_vendedor.php', 'editar_vendedor.php']) ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i> Vendedores
                            <i class="fas fa-chevron-right menu-arrow <?php echo in_array(basename($_SERVER['PHP_SELF']), ['vendedores.php', 'adicionar_vendedor.php', 'editar_vendedor.php']) ? 'rotated' : ''; ?>"></i>
                        </a>
                        <ul class="sidebar-submenu <?php echo in_array(basename($_SERVER['PHP_SELF']), ['vendedores.php', 'adicionar_vendedor.php', 'editar_vendedor.php']) ? 'open' : ''; ?>">
                            <li><a href="<?php echo BASE_URL; ?>/admin/vendedores.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'vendedores.php' ? 'active' : ''; ?>">
                                <i class="fas fa-list"></i> Listar Todos
                            </a></li>
                            <li><a href="<?php echo BASE_URL; ?>/admin/adicionar_vendedor.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'adicionar_vendedor.php' ? 'active' : ''; ?>">
                                <i class="fas fa-user-plus"></i> Adicionar Novo
                            </a></li>
                        </ul>
                    </li>
                    
                    <!-- Vendas -->
                    <li class="has-submenu">
                        <a href="#" class="submenu-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['vendas.php', 'registrar_venda.php', 'editar_venda.php']) ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-cart"></i> Vendas
                            <i class="fas fa-chevron-right menu-arrow <?php echo in_array(basename($_SERVER['PHP_SELF']), ['vendas.php', 'registrar_venda.php', 'editar_venda.php']) ? 'rotated' : ''; ?>"></i>
                        </a>
                        <ul class="sidebar-submenu <?php echo in_array(basename($_SERVER['PHP_SELF']), ['vendas.php', 'registrar_venda.php', 'editar_venda.php']) ? 'open' : ''; ?>">
                            <li><a href="<?php echo BASE_URL; ?>/admin/vendas.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'vendas.php' ? 'active' : ''; ?>">
                                <i class="fas fa-list"></i> Listar Todas
                            </a></li>
                            <li><a href="<?php echo BASE_URL; ?>/admin/registrar_venda.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'registrar_venda.php' ? 'active' : ''; ?>">
                                <i class="fas fa-plus-circle"></i> Registrar Nova
                            </a></li>
                        </ul>
                    </li>
                    
                    <!-- Produtos -->
                    <li class="has-submenu">
                        <a href="#" class="submenu-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['produtos.php', 'adicionar_produto.php', 'editar_produto.php']) ? 'active' : ''; ?>">
                            <i class="fas fa-box"></i> Produtos
                            <i class="fas fa-chevron-right menu-arrow <?php echo in_array(basename($_SERVER['PHP_SELF']), ['produtos.php', 'adicionar_produto.php', 'editar_produto.php']) ? 'rotated' : ''; ?>"></i>
                        </a>
                        <ul class="sidebar-submenu <?php echo in_array(basename($_SERVER['PHP_SELF']), ['produtos.php', 'adicionar_produto.php', 'editar_produto.php']) ? 'open' : ''; ?>">
                            <li><a href="<?php echo BASE_URL; ?>/admin/produtos.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'produtos.php' ? 'active' : ''; ?>">
                                <i class="fas fa-list"></i> Listar Todos
                            </a></li>
                            <li><a href="<?php echo BASE_URL; ?>/admin/adicionar_produto.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'adicionar_produto.php' ? 'active' : ''; ?>">
                                <i class="fas fa-plus-circle"></i> Adicionar Novo
                            </a></li>
                        </ul>
                    </li>
                    
                    <!-- Anúncios -->
                    <li>
                        <a href="<?php echo BASE_URL; ?>/admin/anuncios.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'anuncios.php' ? 'active' : ''; ?>">
                            <i class="fas fa-store"></i> Anúncios ML
                        </a>
                    </li>
                    
                    <!-- Categorias -->
                    <li>
                        <a href="<?php echo BASE_URL; ?>/admin/categorias.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'categorias.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tags"></i> Categorias ML
                        </a>
                    </li>
                    
                    <!-- Relatórios -->
                    <li class="has-submenu">
                        <a href="#" class="submenu-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['relatorios.php', 'relatorio_vendas.php', 'relatorio_lucros.php']) ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> Relatórios
                            <i class="fas fa-chevron-right menu-arrow <?php echo in_array(basename($_SERVER['PHP_SELF']), ['relatorios.php', 'relatorio_vendas.php', 'relatorio_lucros.php']) ? 'rotated' : ''; ?>"></i>
                        </a>
                        <ul class="sidebar-submenu <?php echo in_array(basename($_SERVER['PHP_SELF']), ['relatorios.php', 'relatorio_vendas.php', 'relatorio_lucros.php']) ? 'open' : ''; ?>">
                            <li><a href="<?php echo BASE_URL; ?>/admin/relatorios.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'relatorios.php' ? 'active' : ''; ?>">
                                <i class="fas fa-chart-line"></i> Resumo Geral
                            </a></li>
                            <li><a href="<?php echo BASE_URL; ?>/admin/relatorio_vendas.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'relatorio_vendas.php' ? 'active' : ''; ?>">
                                <i class="fas fa-shopping-bag"></i> Vendas
                            </a></li>
                            <li><a href="<?php echo BASE_URL; ?>/admin/relatorio_lucros.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'relatorio_lucros.php' ? 'active' : ''; ?>">
                                <i class="fas fa-dollar-sign"></i> Lucros
                            </a></li>
                        </ul>
                    </li>
                    
                    <!-- Configurações -->
                    <li>
                        <a href="<?php echo BASE_URL; ?>/admin/configuracoes.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'configuracoes.php' ? 'active' : ''; ?>">
                            <i class="fas fa-cogs"></i> Configurações
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Conteúdo Principal -->
            <div class="content-wrapper">
