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

// Verificar o token do Mercado Livre
$ml_token = getMercadoLivreToken($pdo, $usuario_id);
$token_valido = !empty($ml_token);

// Buscar as configurações e preferências do vendedor
$taxas = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM vendedor_preferencias WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $preferencias = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Definir valores padrão se não existirem preferências
    $taxa_padrao = $preferencias['taxa_padrao'] ?? 13.0; // Taxa padrão do Mercado Livre (%)
    $taxa_cartao = $preferencias['taxa_cartao'] ?? 4.99; // Taxa do cartão de crédito (%)
    $frete_padrao = $preferencias['frete_padrao'] ?? 0.0; // Valor do frete padrão
} catch (PDOException $e) {
    // Silenciar erro, usar valores padrão
    $taxa_padrao = 13.0;
    $taxa_cartao = 4.99;
    $frete_padrao = 0.0;
}

// Preparar paginação
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$itens_por_pagina = 20;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Preparar filtros de busca
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$filtro_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filtro_rentabilidade = isset($_GET['rentabilidade']) ? trim($_GET['rentabilidade']) : '';
$filtro_categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
$ordenar_por = isset($_GET['ordenar']) ? trim($_GET['ordenar']) : 'titulo_asc';

// Construir a consulta SQL com os filtros
$sql_where = "WHERE a.usuario_id = ?";
$parametros = [$usuario_id];

if (!empty($busca)) {
    $sql_where .= " AND (a.titulo LIKE ? OR p.nome LIKE ? OR p.sku LIKE ?)";
    $parametros[] = "%{$busca}%";
    $parametros[] = "%{$busca}%";
    $parametros[] = "%{$busca}%";
}

if (!empty($filtro_status)) {
    $sql_where .= " AND a.status = ?";
    $parametros[] = $filtro_status;
}

if (!empty($filtro_categoria)) {
    $sql_where .= " AND a.categoria_id = ?";
    $parametros[] = $filtro_categoria;
}

// SQL para cálculo de rentabilidade
$sql_rentabilidade = "
    CASE 
        WHEN p.custo IS NULL OR p.custo = 0 THEN NULL
        ELSE (a.preco - (a.preco * $taxa_padrao / 100) - (a.preco * $taxa_cartao / 100) - COALESCE(p.custo, 0)) 
    END AS lucro,
    CASE 
        WHEN p.custo IS NULL OR p.custo = 0 THEN NULL
        ELSE ((a.preco - (a.preco * $taxa_padrao / 100) - (a.preco * $taxa_cartao / 100) - COALESCE(p.custo, 0)) / a.preco * 100)
    END AS margem_percentual
";

// Aplicar filtro de rentabilidade
if ($filtro_rentabilidade === 'lucro') {
    $sql_where .= " AND p.custo IS NOT NULL AND p.custo > 0 AND (a.preco - (a.preco * $taxa_padrao / 100) - (a.preco * $taxa_cartao / 100) - p.custo) > 0";
} elseif ($filtro_rentabilidade === 'prejuizo') {
    $sql_where .= " AND p.custo IS NOT NULL AND p.custo > 0 AND (a.preco - (a.preco * $taxa_padrao / 100) - (a.preco * $taxa_cartao / 100) - p.custo) <= 0";
} elseif ($filtro_rentabilidade === 'sem_custo') {
    $sql_where .= " AND (p.custo IS NULL OR p.custo = 0)";
}

// Definir ordenação
$sql_order = "ORDER BY ";
switch ($ordenar_por) {
    case 'titulo_asc':
        $sql_order .= "a.titulo ASC";
        break;
    case 'titulo_desc':
        $sql_order .= "a.titulo DESC";
        break;
    case 'preco_asc':
        $sql_order .= "a.preco ASC";
        break;
    case 'preco_desc':
        $sql_order .= "a.preco DESC";
        break;
    case 'rentabilidade_asc':
        $sql_order .= "lucro ASC";
        break;
    case 'rentabilidade_desc':
        $sql_order .= "lucro DESC";
        break;
    case 'data_asc':
        $sql_order .= "a.atualizado_em ASC";
        break;
    case 'data_desc':
        $sql_order .= "a.atualizado_em DESC";
        break;
    default:
        $sql_order .= "a.titulo ASC";
}

// Contar total de registros para paginação
$sql_count = "
    SELECT COUNT(*) 
    FROM anuncios_ml a
    LEFT JOIN produtos p ON a.produto_id = p.id
    $sql_where
";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($parametros);
$total_registros = $stmt->fetchColumn();

$total_paginas = ceil($total_registros / $itens_por_pagina);

// Buscar anúncios do vendedor
$sql = "
    SELECT a.*, 
           p.nome as produto_nome, 
           p.custo as produto_custo,
           p.sku as produto_sku,
           $sql_rentabilidade
    FROM anuncios_ml a
    LEFT JOIN produtos p ON a.produto_id = p.id
    $sql_where
    $sql_order
    LIMIT $itens_por_pagina OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Calcular totais
$total_anuncios = count($anuncios);
$total_com_lucro = 0;
$total_com_prejuizo = 0;
$total_sem_custo = 0;

foreach ($anuncios as $anuncio) {
    if ($anuncio['lucro'] === null) {
        $total_sem_custo++;
    } elseif ($anuncio['lucro'] > 0) {
        $total_com_lucro++;
    } else {
        $total_com_prejuizo++;
    }
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

function formatarPorcentagem($valor) {
    return number_format($valor, 2, ',', '.') . '%';
}

function formatarDataHora($data) {
    if (empty($data)) return 'N/A';
    
    $dt = new DateTime($data);
    return $dt->format('d/m/Y H:i');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Anúncios - CalcMeli</title>
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
        .anuncio-img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(255, 154, 0, 0.05);
        }
        .lucro-positivo {
            color: #28a745;
            font-weight: 500;
        }
        .lucro-negativo {
            color: #dc3545;
            font-weight: 500;
        }
        .sem-custo {
            color: #6c757d;
            font-style: italic;
        }
        .status-badge {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .status-active {
            background-color: #28a745;
        }
        .status-paused {
            background-color: #ffc107;
        }
        .status-closed {
            background-color: #dc3545;
        }
        .filtro-card {
            transition: all 0.3s ease;
        }
        .filtro-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1) !important;
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
                    <a class="nav-link active" href="<?php echo $base_url; ?>/vendedor_anuncios.php">
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
                <h1 class="h2">Meus Anúncios do Mercado Livre</h1>
                <div>
                    <a href="<?php echo $base_url; ?>/vendedor_mercadolivre_sincronizar.php" class="btn btn-warning">
                        <i class="fas fa-sync"></i> Sincronizar Anúncios
                    </a>
                </div>
            </div>

            <?php if (isset($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensagem); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Resumo / Estatísticas -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100 filtro-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-muted mb-0">Total de Anúncios</h6>
                                <span class="badge bg-primary rounded-pill"><?php echo $total_registros; ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-end">
                                <h3 class="mb-0"><?php echo number_format($total_registros); ?></h3>
                                <a href="<?php echo $base_url; ?>/vendedor_anuncios.php" class="text-decoration-none">
                                    <small>Ver todos</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100 filtro-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-muted mb-0">Com Lucro</h6>
                                <a href="?rentabilidade=lucro" class="badge bg-success rounded-pill text-decoration-none">Ver todos</a>
                            </div>
                            <div class="d-flex justify-content-between align-items-end">
                                <h3 class="mb-0 text-success"><?php echo $total_com_lucro; ?></h3>
                                <small class="text-success">
                                    <?php 
                                    if ($total_anuncios > 0) {
                                        echo formatarPorcentagem(($total_com_lucro / $total_anuncios) * 100);
                                    } else {
                                        echo '0%';
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100 filtro-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-muted mb-0">Com Prejuízo</h6>
                                <a href="?rentabilidade=prejuizo" class="badge bg-danger rounded-pill text-decoration-none">Ver todos</a>
                            </div>
                            <div class="d-flex justify-content-between align-items-end">
                                <h3 class="mb-0 text-danger"><?php echo $total_com_prejuizo; ?></h3>
                                <small class="text-danger">
                                    <?php 
                                    if ($total_anuncios > 0) {
                                        echo formatarPorcentagem(($total_com_prejuizo / $total_anuncios) * 100);
                                    } else {
                                        echo '0%';
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100 filtro-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-muted mb-0">Sem Custo Definido</h6>
                                <a href="?rentabilidade=sem_custo" class="badge bg-secondary rounded-pill text-decoration-none">Ver todos</a>
                            </div>
                            <div class="d-flex justify-content-between align-items-end">
                                <h3 class="mb-0 text-secondary"><?php echo $total_sem_custo; ?></h3>
                                <small class="text-secondary">
                                    <?php 
                                    if ($total_anuncios > 0) {
                                        echo formatarPorcentagem(($total_sem_custo / $total_anuncios) * 100);
                                    } else {
                                        echo '0%';
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Filtros</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="" class="row g-3">
                        <div class="col-lg-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" name="busca" placeholder="Buscar por título, produto ou SKU..." value="<?php echo htmlspecialchars($busca); ?>">
                            </div>
                        </div>
                        
                        <div class="col-lg-2">
                            <select class="form-select" name="status">
                                <option value="">Status</option>
                                <option value="active" <?php echo $filtro_status === 'active' ? 'selected' : ''; ?>>Ativos</option>
                                <option value="paused" <?php echo $filtro_status === 'paused' ? 'selected' : ''; ?>>Pausados</option>
                                <option value="closed" <?php echo $filtro_status === 'closed' ? 'selected' : ''; ?>>Fechados</option>
                            </select>
                        </div>
                        
                        <div class="col-lg-2">
                            <select class="form-select" name="rentabilidade">
                                <option value="">Rentabilidade</option>
                                <option value="lucro" <?php echo $filtro_rentabilidade === 'lucro' ? 'selected' : ''; ?>>Com Lucro</option>
                                <option value="prejuizo" <?php echo $filtro_rentabilidade === 'prejuizo' ? 'selected' : ''; ?>>Com Prejuízo</option>
                                <option value="sem_custo" <?php echo $filtro_rentabilidade === 'sem_custo' ? 'selected' : ''; ?>>Sem Custo Definido</option>
                            </select>
                        </div>
                        
                        <div class="col-lg-2">
                            <select class="form-select" name="categoria">
                                <option value="">Categoria</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['categoria_id']; ?>" <?php echo $filtro_categoria === $categoria['categoria_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['nome'] ?? $categoria['categoria_id']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-lg-2">
                            <select class="form-select" name="ordenar">
                                <option value="titulo_asc" <?php echo $ordenar_por === 'titulo_asc' ? 'selected' : ''; ?>>Título (A-Z)</option>
                                <option value="titulo_desc" <?php echo $ordenar_por === 'titulo_desc' ? 'selected' : ''; ?>>Título (Z-A)</option>
                                <option value="preco_asc" <?php echo $ordenar_por === 'preco_asc' ? 'selected' : ''; ?>>Menor Preço</option>
                                <option value="preco_desc" <?php echo $ordenar_por === 'preco_desc' ? 'selected' : ''; ?>>Maior Preço</option>
                                <option value="rentabilidade_asc" <?php echo $ordenar_por === 'rentabilidade_asc' ? 'selected' : ''; ?>>Menor Lucro</option>
                                <option value="rentabilidade_desc" <?php echo $ordenar_por === 'rentabilidade_desc' ? 'selected' : ''; ?>>Maior Lucro</option>
                                <option value="data_desc" <?php echo $ordenar_por === 'data_desc' ? 'selected' : ''; ?>>Mais Recentes</option>
                                <option value="data_asc" <?php echo $ordenar_por === 'data_asc' ? 'selected' : ''; ?>>Mais Antigos</option>
                            </select>
                        </div>
                        
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                            <a href="<?php echo $base_url; ?>/vendedor_anuncios.php" class="btn btn-outline-secondary">
                                <i class="fas fa-undo"></i> Limpar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Lista de Anúncios -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($anuncios)): ?>
                        <div class="p-4 text-center">
                            <p class="text-muted mb-3">Nenhum anúncio encontrado com os filtros atuais.</p>
                            <?php if (empty($total_registros)): ?>
                                <a href="<?php echo $base_url; ?>/vendedor_mercadolivre_sincronizar.php" class="btn btn-warning">
                                    <i class="fas fa-sync"></i> Sincronizar Anúncios do Mercado Livre
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px">Imagem</th>
                                        <th>Anúncio</th>
                                        <th>Status</th>
                                        <th>Produto Vinculado</th>
                                        <th>Preço</th>
                                        <th>Custo</th>
                                        <th>Taxas</th>
                                        <th>Lucro</th>
                                        <th>Margem</th>
                                        <th style="width: 110px">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($anuncios as $anuncio): ?>
                                        <?php
                                        // Cálculos de taxas e rentabilidade
                                        $preco = $anuncio['preco'];
                                        $custo = $anuncio['produto_custo'] ?? 0;
                                        $taxa_ml = $preco * ($taxa_padrao / 100);
                                        $taxa_cartao_valor = $preco * ($taxa_cartao / 100);
                                        $total_taxas = $taxa_ml + $taxa_cartao_valor;
                                        $lucro = $anuncio['lucro'];
                                        $margem = $anuncio['margem_percentual'];
                                        
                                        // Classes de estilo para lucro/prejuízo
                                        $lucro_class = '';
                                        if ($custo == 0 || $custo === null) {
                                            $lucro_class = 'sem-custo';
                                        } elseif ($lucro > 0) {
                                            $lucro_class = 'lucro-positivo';
                                        } else {
                                            $lucro_class = 'lucro-negativo';
                                        }
										
										                                        // Classes para o status
                                        $status_class = '';
                                        $status_text = $anuncio['status'];
                                        
                                        switch (strtolower($anuncio['status'])) {
                                            case 'active':
                                                $status_class = 'status-active';
                                                $status_text = 'Ativo';
                                                break;
                                            case 'paused':
                                                $status_class = 'status-paused';
                                                $status_text = 'Pausado';
                                                break;
                                            case 'closed':
                                                $status_class = 'status-closed';
                                                $status_text = 'Fechado';
                                                break;
                                            default:
                                                $status_class = '';
                                        }
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
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($anuncio['ml_item_id']); ?></small>
                                            </td>
                                            <td>
                                                <span class="d-flex align-items-center">
                                                    <span class="status-badge <?php echo $status_class; ?>"></span>
                                                    <?php echo htmlspecialchars(ucfirst($status_text)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($anuncio['produto_nome'])): ?>
                                                    <div><?php echo htmlspecialchars($anuncio['produto_nome']); ?></div>
                                                    <?php if (!empty($anuncio['produto_sku'])): ?>
                                                        <small class="text-muted">SKU: <?php echo htmlspecialchars($anuncio['produto_sku']); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Não vinculado</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatarPreco($preco); ?></td>
                                            <td>
                                                <?php if ($custo > 0): ?>
                                                    <?php echo formatarPreco($custo); ?>
                                                <?php else: ?>
                                                    <span class="sem-custo">Não definido</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div title="Taxa ML: <?php echo formatarPreco($taxa_ml); ?> + Taxa Cartão: <?php echo formatarPreco($taxa_cartao_valor); ?>">
                                                    <?php echo formatarPreco($total_taxas); ?>
                                                </div>
                                                <small class="text-muted">
                                                    (<?php echo formatarPorcentagem($taxa_padrao + $taxa_cartao); ?>)
                                                </small>
                                            </td>
                                            <td class="<?php echo $lucro_class; ?>">
                                                <?php if ($custo > 0 || $custo !== null): ?>
                                                    <?php echo formatarPreco($lucro); ?>
                                                <?php else: ?>
                                                    <span class="sem-custo">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="<?php echo $lucro_class; ?>">
                                                <?php if ($custo > 0 || $custo !== null): ?>
                                                    <?php echo formatarPorcentagem($margem); ?>
                                                <?php else: ?>
                                                    <span class="sem-custo">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="<?php echo $base_url; ?>/vendedor_anuncio_detalhes.php?id=<?php echo $anuncio['id']; ?>" class="btn btn-sm btn-outline-primary" title="Detalhes">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="<?php echo $base_url; ?>/vendedor_mercadolivre_sincronizar.php?vincular=<?php echo $anuncio['id']; ?>" class="btn btn-sm btn-outline-warning" title="Vincular Produto">
                                                        <i class="fas fa-link"></i>
                                                    </a>
                                                    <a href="<?php echo htmlspecialchars($anuncio['permalink']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver no Mercado Livre">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginação -->
                        <?php if ($total_paginas > 1): ?>
                            <div class="d-flex justify-content-center p-3">
                                <nav aria-label="Navegação de página">
                                    <ul class="pagination">
                                        <li class="page-item <?php echo ($pagina_atual == 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?pagina=1<?php echo !empty($busca) ? '&busca=' . urlencode($busca) : ''; ?><?php echo !empty($filtro_status) ? '&status=' . urlencode($filtro_status) : ''; ?><?php echo !empty($filtro_rentabilidade) ? '&rentabilidade=' . urlencode($filtro_rentabilidade) : ''; ?><?php echo !empty($filtro_categoria) ? '&categoria=' . urlencode($filtro_categoria) : ''; ?><?php echo !empty($ordenar_por) ? '&ordenar=' . urlencode($ordenar_por) : ''; ?>" aria-label="Primeira">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                        
                                        <?php
                                        $inicio = max(1, $pagina_atual - 2);
                                        $fim = min($total_paginas, $pagina_atual + 2);
                                        
                                        if ($inicio > 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        
                                        for ($i = $inicio; $i <= $fim; $i++) {
                                            echo '<li class="page-item ' . (($i == $pagina_atual) ? 'active' : '') . '">';
                                            echo '<a class="page-link" href="?pagina=' . $i . (!empty($busca) ? '&busca=' . urlencode($busca) : '') . (!empty($filtro_status) ? '&status=' . urlencode($filtro_status) : '') . (!empty($filtro_rentabilidade) ? '&rentabilidade=' . urlencode($filtro_rentabilidade) : '') . (!empty($filtro_categoria) ? '&categoria=' . urlencode($filtro_categoria) : '') . (!empty($ordenar_por) ? '&ordenar=' . urlencode($ordenar_por) : '') . '">' . $i . '</a>';
                                            echo '</li>';
                                        }
                                        
                                        if ($fim < $total_paginas) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        ?>
                                        
                                        <li class="page-item <?php echo ($pagina_atual == $total_paginas) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $total_paginas; ?><?php echo !empty($busca) ? '&busca=' . urlencode($busca) : ''; ?><?php echo !empty($filtro_status) ? '&status=' . urlencode($filtro_status) : ''; ?><?php echo !empty($filtro_rentabilidade) ? '&rentabilidade=' . urlencode($filtro_rentabilidade) : ''; ?><?php echo !empty($filtro_categoria) ? '&categoria=' . urlencode($filtro_categoria) : ''; ?><?php echo !empty($ordenar_por) ? '&ordenar=' . urlencode($ordenar_por) : ''; ?>" aria-label="Última">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                        
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Legenda -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Informações Adicionais</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">Cálculo de Rentabilidade</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i> <strong>Lucro</strong> = Preço - Taxas - Custo</li>
                                <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i> <strong>Taxas</strong> = Taxa ML (<?php echo formatarPorcentagem($taxa_padrao); ?>) + Taxa Cartão (<?php echo formatarPorcentagem($taxa_cartao); ?>)</li>
                                <li class="mb-2"><i class="fas fa-info-circle text-primary me-2"></i> <strong>Margem</strong> = (Lucro / Preço) × 100</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">Legenda</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2"><span class="status-badge status-active"></span> <strong>Ativo</strong> - Anúncio visível e disponível para venda</li>
                                <li class="mb-2"><span class="status-badge status-paused"></span> <strong>Pausado</strong> - Anúncio temporariamente indisponível</li>
                                <li class="mb-2"><span class="status-badge status-closed"></span> <strong>Fechado</strong> - Anúncio encerrado ou indisponível</li>
                                <li class="mb-2"><span class="sem-custo">—</span> <strong>Sem Custo</strong> - Produto não possui custo definido</li>
                            </ul>
                        </div>
                    </div>
                    <div class="alert alert-info mb-0">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-lightbulb fa-2x text-info"></i>
                            </div>
                            <div>
                                <h5 class="alert-heading">Dica</h5>
                                <p class="mb-0">Para uma análise mais precisa da rentabilidade, certifique-se de que todos os seus produtos tenham o custo cadastrado. Você pode definir os custos dos produtos na página <a href="<?php echo $base_url; ?>/vendedor_produtos.php" class="alert-link">Meus Produtos</a>.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
