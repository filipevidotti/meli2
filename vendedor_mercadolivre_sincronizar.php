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

// Verificar se o vendedor tem um token válido do Mercado Livre
$ml_token = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM mercadolivre_tokens 
        WHERE usuario_id = ? AND revogado = 0
        ORDER BY data_expiracao DESC 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $ml_token = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro
}

// Verificar se o token ainda é válido
$token_valido = false;
if (!empty($ml_token) && !empty($ml_token['data_expiracao'])) {
    $data_expiracao = new DateTime($ml_token['data_expiracao']);
    $agora = new DateTime();
    $token_valido = $agora < $data_expiracao;
}

if (!$token_valido) {
    // Redirecionar para a página de integração com mensagem de erro
    $_SESSION['mensagem'] = "Você precisa autorizar o acesso ao Mercado Livre antes de sincronizar anúncios.";
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

// Inicializar variáveis
$anuncios = [];
$total_anuncios = 0;
$offset = 0;
$limit = 50;
$sincronizacao_concluida = false;
$erro_api = false;
$mensagem_erro = '';

// Verificar se é uma solicitação de sincronização
if (isset($_POST['sincronizar']) && $token_valido && !empty($ml_usuario['ml_user_id'])) {
    $access_token = $ml_token['access_token'];
    $ml_user_id = $ml_usuario['ml_user_id'];
    
    // Buscar todos os anúncios ativos do vendedor no Mercado Livre
    $todos_anuncios = [];
    $offset = 0;
    $limit = 50;  // Máximo permitido pela API
    $total_sincronizados = 0;
    
    do {
        // Iniciar o cURL para obter os anúncios
        $ch = curl_init("https://api.mercadolibre.com/users/{$ml_user_id}/items/search?status=active&limit={$limit}&offset={$offset}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        
        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status_code == 200) {
            $search_data = json_decode($response, true);
            
            if (isset($search_data['results']) && is_array($search_data['results'])) {
                // Processar cada ID de anúncio para obter detalhes
                foreach ($search_data['results'] as $item_id) {
                    // Buscar detalhes do anúncio
                    $ch = curl_init("https://api.mercadolibre.com/items/{$item_id}");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $access_token
                    ]);
                    
                    $item_response = curl_exec($ch);
                    $item_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($item_status_code == 200) {
                        $item_data = json_decode($item_response, true);
                        
                        // Verificar se o item já existe no banco de dados
                        $stmt = $pdo->prepare("
                            SELECT id FROM anuncios_ml 
                            WHERE ml_item_id = ? AND usuario_id = ?
                        ");
                        $stmt->execute([$item_id, $usuario_id]);
                        $anuncio_existente = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Preparar dados para inserção/atualização
                        $titulo = $item_data['title'] ?? '';
                        $preco = $item_data['price'] ?? 0;
                        $status = $item_data['status'] ?? '';
                        $quantidade = $item_data['available_quantity'] ?? 0;
                        $permalink = $item_data['permalink'] ?? '';
                        $thumbnail = $item_data['thumbnail'] ?? '';
                        $categoria_id = $item_data['category_id'] ?? '';
                        $ml_listing_type = $item_data['listing_type_id'] ?? '';
                        $ml_start_time = $item_data['start_time'] ?? null;
                        $ml_stop_time = $item_data['stop_time'] ?? null;
                        $ml_item_data = json_encode($item_data);
                        
                        if ($anuncio_existente) {
                            // Atualizar anúncio existente
                            $sql = "
                                UPDATE anuncios_ml 
                                SET titulo = ?, preco = ?, status = ?, quantidade = ?, permalink = ?,
                                    thumbnail = ?, categoria_id = ?, ml_listing_type = ?,
                                    ml_start_time = ?, ml_stop_time = ?, ml_item_data = ?,
                                    atualizado_em = NOW()
                                WHERE id = ?
                            ";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                $titulo, $preco, $status, $quantidade, $permalink,
                                $thumbnail, $categoria_id, $ml_listing_type,
                                $ml_start_time, $ml_stop_time, $ml_item_data,
                                $anuncio_existente['id']
                            ]);
                        } else {
                            // Inserir novo anúncio
                            $sql = "
                                INSERT INTO anuncios_ml 
                                (usuario_id, ml_item_id, titulo, preco, status, quantidade, permalink,
                                 thumbnail, categoria_id, ml_listing_type, ml_start_time, ml_stop_time,
                                 ml_item_data, criado_em, atualizado_em)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                            ";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                $usuario_id, $item_id, $titulo, $preco, $status, $quantidade, $permalink,
                                $thumbnail, $categoria_id, $ml_listing_type, $ml_start_time, $ml_stop_time,
                                $ml_item_data
                            ]);
                        }
                        
                        $total_sincronizados++;
                    }
                }
                
                // Verificar se há mais resultados
                $offset += count($search_data['results']);
                $has_more = isset($search_data['paging']) && 
                           isset($search_data['paging']['total']) && 
                           $offset < $search_data['paging']['total'];
                
            } else {
                $has_more = false;
            }
        } else {
            // Erro na API
            $erro_api = true;
            $error_data = json_decode($response, true);
            $mensagem_erro = "Erro ao buscar anúncios: " . ($error_data['message'] ?? 'Erro desconhecido');
            $has_more = false;
        }
    } while ($has_more);
    
    if (!$erro_api) {
        $sincronizacao_concluida = true;
        $mensagem = "Sincronização concluída com sucesso! {$total_sincronizados} anúncios sincronizados.";
        $tipo_mensagem = "success";
    } else {
        $mensagem = $mensagem_erro;
        $tipo_mensagem = "danger";
    }
}

// Buscar produtos do vendedor para vincular aos anúncios
$produtos = [];
try {
    $sql = "SELECT id, nome, sku FROM produtos WHERE usuario_id = ? ORDER BY nome";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro
}

// Buscar anúncios do Mercado Livre salvos no banco
$anuncios = [];
$total_anuncios = 0;
try {
    // Preparar paginação
    $pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
    $itens_por_pagina = 20;
    $offset = ($pagina_atual - 1) * $itens_por_pagina;
    
    // Buscar total de anúncios
    $sql_count = "SELECT COUNT(*) FROM anuncios_ml WHERE usuario_id = ?";
    $stmt = $pdo->prepare($sql_count);
    $stmt->execute([$usuario_id]);
    $total_anuncios = $stmt->fetchColumn();
    
    // Buscar anúncios paginados
    $sql = "
        SELECT a.*, p.nome as produto_nome
        FROM anuncios_ml a
        LEFT JOIN produtos p ON a.produto_id = p.id
        WHERE a.usuario_id = ?
        ORDER BY a.atualizado_em DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id, $itens_por_pagina, $offset]);
    $anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $mensagem = "Erro ao buscar anúncios: " . $e->getMessage();
    $tipo_mensagem = "danger";
}

// Processar vinculação de anúncio a produto
if (isset($_POST['vincular_produto']) && isset($_POST['anuncio_id']) && isset($_POST['produto_id'])) {
    $anuncio_id = (int)$_POST['anuncio_id'];
    $produto_id = empty($_POST['produto_id']) ? null : (int)$_POST['produto_id'];
    
    try {
        // Verificar se o anúncio pertence ao vendedor
        $stmt = $pdo->prepare("SELECT id FROM anuncios_ml WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$anuncio_id, $usuario_id]);
        
        if ($stmt->rowCount() > 0) {
            // Se produto_id for null, desvincular o produto
            if ($produto_id === null) {
                $stmt = $pdo->prepare("UPDATE anuncios_ml SET produto_id = NULL WHERE id = ?");
                $stmt->execute([$anuncio_id]);
                $mensagem = "Anúncio desvinculado com sucesso!";
            } else {
                // Verificar se o produto pertence ao vendedor
                $stmt = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND usuario_id = ?");
                $stmt->execute([$produto_id, $usuario_id]);
                
                if ($stmt->rowCount() > 0) {
                    // Vincular o anúncio ao produto
                    $stmt = $pdo->prepare("UPDATE anuncios_ml SET produto_id = ? WHERE id = ?");
                    $stmt->execute([$produto_id, $anuncio_id]);
                    $mensagem = "Anúncio vinculado ao produto com sucesso!";
                } else {
                    $mensagem = "Produto não encontrado ou não pertence ao vendedor.";
                    $tipo_mensagem = "danger";
                }
            }
            
            $tipo_mensagem = "success";
            
            // Recarregar os anúncios para mostrar as mudanças
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario_id, $itens_por_pagina, $offset]);
            $anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } else {
            $mensagem = "Anúncio não encontrado ou não pertence ao vendedor.";
            $tipo_mensagem = "danger";
        }
    } catch (PDOException $e) {
        $mensagem = "Erro ao vincular anúncio: " . $e->getMessage();
        $tipo_mensagem = "danger";
    }
}

// Calcular total de páginas para paginação
$total_paginas = ceil($total_anuncios / $itens_por_pagina);

// Exibir mensagem de sessão, se existir
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'] ?? 'info';
    
    // Limpar a mensagem da sessão
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}

// Função para formatar preço
function formatarPreco($preco) {
    return 'R$ ' . number_format($preco, 2, ',', '.');
}

// Função para formatar data/hora
function formatarDataHora($data) {
    if (empty($data)) return 'N/A';
    
    $dt = new DateTime($data);
    return $dt->format('d/m/Y H:i:s');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronizar Anúncios - CalcMeli</title>
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
                <div>
                    <h1 class="h2">Sincronizar Anúncios do Mercado Livre</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/vendedor_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php">Mercado Livre</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Sincronizar Anúncios</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <form method="post" class="d-inline">
                        <button type="submit" name="sincronizar" value="1" class="btn btn-warning">
                            <i class="fas fa-sync"></i> Sincronizar Anúncios
                        </button>
                    </form>
                </div>
            </div>

            <?php if (isset($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensagem); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <?php if ($sincronizacao_concluida): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Sincronização concluída!</strong> Agora você pode vincular seus anúncios aos produtos.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Lista de Anúncios -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Anúncios Sincronizados</h5>
                    <span class="badge bg-primary"><?php echo $total_anuncios; ?> anúncios</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($anuncios)): ?>
                        <div class="p-4 text-center">
                            <p class="text-muted mb-3">Nenhum anúncio sincronizado ainda.</p>
                            <form method="post">
                                <button type="submit" name="sincronizar" value="1" class="btn btn-warning">
                                    <i class="fas fa-sync"></i> Sincronizar Anúncios
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 80px">Imagem</th>
                                        <th>Título</th>
                                        <th>Preço</th>
                                        <th>Status</th>
                                        <th>Produto Vinculado</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($anuncios as $anuncio): ?>
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
                                                <small class="text-muted"><?php echo htmlspecialchars($anuncio['ml_item_id']); ?></small>