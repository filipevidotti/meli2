<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora Mercado Livre</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Hamburger Menu Styles */
        .sidebar {
            height: 100%;
            width: 0;
            position: fixed;
            z-index: 1;
            top: 0;
            left: 0;
            background-color: #fff;
            overflow-x: hidden;
            transition: 0.5s;
            padding-top: 60px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .sidebar a {
            padding: 8px 8px 8px 32px;
            text-decoration: none;
            font-size: 18px;
            color: #333;
            display: block;
            transition: 0.3s;
        }

        .sidebar a:hover {
            color: #FFE600;
            background-color: #f8f9fa;
        }

        .sidebar .closebtn {
            position: absolute;
            top: 0;
            right: 25px;
            font-size: 36px;
            margin-left: 50px;
        }

        .openbtn {
            font-size: 20px;
            cursor: pointer;
            background-color: #FFE600;
            color: #333;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
        }

        .openbtn:hover {
            background-color: #e6cf00;
        }

        #main {
            transition: margin-left .5s;
            padding: 16px;
        }

        .navbar-mercadolivre {
            background-color: #FFE600;
        }

        @media screen and (max-height: 450px) {
            .sidebar {padding-top: 15px;}
            .sidebar a {font-size: 18px;}
        }

        /* Custom styles */
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background-color: #FFE600;
            color: #333;
        }
        
        .btn-primary {
            background-color: #3483FA;
            border-color: #3483FA;
        }
        
        .btn-primary:hover {
            background-color: #2968c8;
            border-color: #2968c8;
        }
        
        .btn-success {
            background-color: #00a650;
            border-color: #00a650;
        }
        
        .btn-success:hover {
            background-color: #008f44;
            border-color: #008f44;
        }
        
        .dashboard-card {
            border-left: 5px solid #3483FA;
        }
        
        .dashboard-card.profit {
            border-left-color: #00a650;
        }
        
        .dashboard-card.expense {
            border-left-color: #f73;
        }
        
        .dashboard-card h3 {
            color: #333;
        }
        
        footer {
            background-color: #333;
            color: #fff;
            padding: 20px 0;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <a href="calculadora.php"><i class="fas fa-calculator"></i> Calculadora</a>
        <a href="produtos_salvos.php"><i class="fas fa-save"></i> Produtos Salvos</a>
        <a href="configuracoes.php"><i class="fas fa-cog"></i> Configurações</a>
        <a href="vendas.php"><i class="fas fa-shopping-cart"></i> Vendas</a>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-mercadolivre">
        <div class="container">
            <div id="main">
                <button class="openbtn" onclick="openNav()">&#9776;</button>
            </div>
            <a class="navbar-brand" href="index.php">
                <img src="https://http2.mlstatic.com/frontend-assets/ui-navigation/5.18.9/mercadolibre/logo__large_plus.png" alt="MercadoLivre Logo" height="34">
            </a>
            <div class="d-flex">
                <a href="calculadora.php" class="btn btn-outline-dark me-2">Calculadora</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">