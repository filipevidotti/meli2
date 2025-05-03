<?php
// Verificar se as variáveis necessárias estão definidas
if (!isset($base_url) || !isset($usuario_nome)) {
    require_once 'config.php';
}

// Definir o título da página se não estiver definido
$page_title = $page_title ?? 'CalcMeli';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (isset($include_datatables) && $include_datatables): ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <?php endif; ?>
   
   <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding-top: 56px;
            overflow-x: hidden;
        }
        
        /* Sidebar principal */
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
            transition: all 0.3s;
            overflow-y: auto;
        }
        
        /* Sidebar recolhida */
        .sidebar.collapsed {
            width: 60px;
            overflow-x: hidden;
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 56px);
            padding-top: .5rem;
        }
        
        .sidebar .nav-link {
            font-weight: 500;
            color: #ced4da;
            padding: .75rem 1rem;
            display: flex;
            align-items: center;
            white-space: nowrap;
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
        
        /* Quando o sidebar estiver recolhido */
        .sidebar.collapsed .nav-link i {
            margin-right: 30px;
        }
        
        .sidebar.collapsed .nav-link span,
        .sidebar.collapsed .nav-text,
        .sidebar.collapsed .submenu-arrow {
            display: none;
        }
        
        /* Submenu */
        .submenu {
            padding-left: 1rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .submenu-active {
            max-height: 1000px;
        }
        
        .submenu .nav-link {
            padding-left: 2rem;
            font-size: 0.9rem;
        }
        
        .submenu-arrow {
            margin-left: auto;
        }
        
        /* Conteúdo principal */
        .main-content {
            margin-left: 240px;
            padding: 1.5rem;
            transition: margin-left 0.3s;
        }
        
        .main-content.expanded {
            margin-left: 60px;
        }
        
        /* Botão de toggle do sidebar */
        .sidebar-toggle {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 101;
            background-color: #343a40;
            color: #ced4da;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        /* Cores personalizadas */
        .btn-warning, .bg-warning {
            background-color: #ff9a00 !important;
            border-color: #ff9a00;
        }
        
        /* Ícone de fechar para o menu mobile */
        .sidebar-close {
            display: none;
        }
        
        /* Responsividade */
        @media (max-width: 767.98px) {
            .sidebar {
                transform: translateX(-100%);
                width: 240px;
            }
            
            .sidebar.collapsed {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-content.expanded {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: none;
            }
            
            .sidebar-close {
                display: block;
                position: absolute;
                top: 10px;
                right: 10px;
                z-index: 101;
                background-color: transparent;
                color: #ced4da;
                border: none;
                font-size: 1.25rem;
            }
        }
        
        <?php if (isset($additional_css)): echo $additional_css; endif; ?>
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $base_url; ?>/dash_vendedor.php">CalcMeli</a>
            
            <!-- Botão para exibir o sidebar em telas pequenas -->
            <button class="navbar-toggler" type="button" id="sidebarToggleBtn">
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