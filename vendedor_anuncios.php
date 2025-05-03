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

// Incluir arquivos necessários
require_once 'ml_config.php';

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

// Buscar token válido do Mercado Livre
$ml_token = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM mercadolivre_tokens 
        WHERE usuario_id = ? AND revogado = 0 AND data_expiracao > NOW()
        ORDER BY data_expiracao DESC 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $ml_token = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erro ao buscar token: " . $e->getMessage();
}

// Verificar se tem token válido
if (empty($ml_token) || empty($ml_token['access_token'])) {
    $sem_token = true;
    $error_message = "Você precisa se conectar ao Mercado Livre para acessar seus anúncios.";
} else {
    $sem_token = false;
    $access_token = $ml_token['access_token'];
    
    // Verificar qual usuário está usando o token
    $ch = curl_init('https://api.mercadolibre.com/users/me');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($status == 200) {
        $user_data = json_decode($response, true);
        $ml_user_id = $user_data['id'] ?? 'Desconhecido';
        $ml_nickname = $user_data['nickname'] ?? 'Desconhecido';
    }
}

// Verificar se o usuário deseja sincronizar os anúncios
$sincronizou = false;
$mensagem = '';
$tipo_mensagem = '';

if (isset($_GET['sincronizar']) && $_GET['sincronizar'] == 1 && !$sem_token) {
    try {
        $anuncios = sincronizarAnuncios($access_token, $usuario_id);
        $salvos = count($anuncios);
        
        $sincronizou = true;
        $mensagem = "Sincronização concluída! {$salvos} anúncios foram atualizados.";
        $tipo_mensagem = 'success';
    } catch (Exception $e) {
        $mensagem = "Erro ao sincronizar: " . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Processar vinculação de produto
if (isset($_POST['vincular_produto']) && !$sem_token) {
    $anuncio_id = $_POST['anuncio_id'] ?? 0;
    $produto_id = $_POST['produto_id'] ?? 0;
    
    if ($anuncio_id > 0 && $produto_id > 0) {
        try {
            $sql = "UPDATE anuncios_ml SET produto_id = ? WHERE id = ? AND usuario_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$produto_id, $anuncio_id, $usuario_id]);
            
            if ($stmt->rowCount() > 0) {
                $mensagem = "Anúncio vinculado ao produto com sucesso!";
                $tipo_mensagem = 'success';
            } else {
                $mensagem = "Não foi possível vincular o anúncio ao produto.";
                $tipo_mensagem = 'warning';
            }
        } catch (PDOException $e) {
            $mensagem = "Erro ao vincular: " . $e->getMessage();
            $tipo_mensagem = 'danger';
        }
    }
}

// Processar desvinculação de produto
if (isset($_GET['desvincular']) && is_numeric($_GET['desvincular']) && !$sem_token) {
    $anuncio_id = $_GET['desvincular'];
    
    try {
        // Verificar se o anúncio pertence ao usuário
        $sql = "SELECT id FROM anuncios_ml WHERE id = ? AND usuario_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$anuncio_id, $usuario_id]);
        
        if ($stmt->fetch()) {
            // Desvincular o produto
            $sql = "UPDATE anuncios_ml SET produto_id = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$anuncio_id]);
            
            $mensagem = "Anúncio desvinculado com sucesso!";
            $tipo_mensagem = 'success';
        } else {
            $mensagem = "Anúncio não encontrado ou não pertence ao usuário.";
            $tipo_mensagem = 'danger';
        }
    } catch (PDOException $e) {
        $mensagem = "Erro ao desvincular: " . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Buscar produtos para o modal de vinculação
$produtos = [];
try {
    $sql = "SELECT id, nome, sku, custo FROM produtos WHERE usuario_id = ? ORDER BY nome ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro
}

// Filtrar anúncios por produto
$filtro_produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : 0;
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_vinculados = isset($_GET['vinculados']) ? $_GET['vinculados'] : '';

// Construir consulta com filtros
$where = ['a.usuario_id = ?'];
$params = [$usuario_id];

if ($filtro_produto_id > 0) {
    $where[] = 'a.produto_id = ?';
    $params[] = $filtro_produto_id;
} elseif ($filtro_vinculados === 'sim') {
    $where[] = 'a.produto_id IS NOT NULL';
} elseif ($filtro_vinculados === 'nao') {
    $where[] = 'a.produto_id IS NULL';
}

if (!empty($filtro_status)) {
    $where[] = 'a.status = ?';
    $params[] = $filtro_status;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Função para sincronizar anúncios do Mercado Livre
// Função para sincronizar anúncios do Mercado Livre
function sincronizarAnuncios($access_token, $usuario_id) {
    global $pdo;
    
    // Primeiro, precisamos obter o ID do usuário no Mercado Livre
    $ch = curl_init('https://api.mercadolibre.com/users/me');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($status != 200) {
        throw new Exception("Erro ao obter informações do usuário: " . $response);
    }
    
    $user_data = json_decode($response, true);
    $ml_user_id = $user_data['id'];
    
    if (empty($ml_user_id)) {
        throw new Exception("Não foi possível identificar o ID do usuário no Mercado Livre");
    }
    
    // Agora buscar anúncios do usuário no Mercado Livre
    $anuncios = [];
    $offset = 0;
    $limit = 50;
    $total = 0;
    
    do {
        // Modificando a URL para usar o seller_id explicitamente
        $url = "https://api.mercadolibre.com/users/{$ml_user_id}/items/search?limit={$limit}&offset={$offset}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status != 200) {
            throw new Exception("Erro ao buscar anúncios: " . $response);
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['results']) || !is_array($data['results'])) {
            throw new Exception("Resposta inválida da API do Mercado Livre");
        }
        
        $item_ids = $data['results'];
        
        // Buscar detalhes de cada anúncio
        foreach ($item_ids as $item_id) {
            $url = "https://api.mercadolibre.com/items/{$item_id}";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token
            ]);
            
            $item_response = curl_exec($ch);
            $item_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($item_status == 200) {
                $item_data = json_decode($item_response, true);
                
                $anuncios[] = [
                    'ml_item_id' => $item_data['id'],
                    'titulo' => $item_data['title'],
                    'permalink' => $item_data['permalink'],
                    'preco' => $item_data['price'],
                    'categoria_id' => $item_data['category_id'],
                    'tipo_anuncio' => $item_data['listing_type_id'],
                    'status' => $item_data['status'],
                    'thumbnail' => $item_data['thumbnail']
                ];
            }
        }
        
        $offset += $limit;
        $total = $data['paging']['total'] ?? 0;
    } while ($offset < $total);
    
    // Salvar o ml_user_id no cadastro do vendedor para referência futura
    try {
        $stmt = $pdo->prepare("UPDATE vendedores SET ml_user_id = ? WHERE usuario_id = ?");
        $stmt->execute([$ml_user_id, $usuario_id]);
    } catch (PDOException $e) {
        // Ignorar erros de atualização do vendedor
    }
    
    // Salvar anúncios no banco de dados
    foreach ($anuncios as $anuncio) {
        // Verificar se o anúncio já existe
        $stmt = $pdo->prepare("SELECT id, produto_id FROM anuncios_ml WHERE ml_item_id = ? AND usuario_id = ?");
        $stmt->execute([$anuncio['ml_item_id'], $usuario_id]);
        $anuncio_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($anuncio_existente) {
            // Atualizar anúncio existente preservando a vinculação com o produto
            $sql = "UPDATE anuncios_ml SET 
                    titulo = ?, 
                    permalink = ?,
                    preco = ?,
                    categoria_id = ?,
                    tipo_anuncio = ?,
                    status = ?,
                    thumbnail = ?,
                    data_ultima_sincronizacao = NOW()
                    WHERE id = ?";
                    
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $anuncio['titulo'],
                $anuncio['permalink'],
                $anuncio['preco'],
                $anuncio['categoria_id'],
                $anuncio['tipo_anuncio'],
                $anuncio['status'],
                $anuncio['thumbnail'],
                $anuncio_existente['id']
            ]);
        } else {
            // Inserir novo anúncio
            $sql = "INSERT INTO anuncios_ml 
                    (usuario_id, ml_item_id, titulo, permalink, preco, categoria_id, tipo_anuncio, status, thumbnail, data_ultima_sincronizacao)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $usuario_id,
                $anuncio['ml_item_id'],
                $anuncio['titulo'],
                $anuncio['permalink'],
                $anuncio['preco'],
                $anuncio['categoria_id'],
                $anuncio['tipo_anuncio'],
                $anuncio['status'],
                $anuncio['thumbnail']
            ]);
        }
    }
    
    // Buscar e adicionar nomes de categorias
    $anuncios_salvos = [];
    try {
        // Mapear anúncios por categoria
        $categorias = [];
        foreach ($anuncios as $anuncio) {
            if (!empty($anuncio['categoria_id'])) {
                $categorias[$anuncio['categoria_id']] = true;
            }
        }
        
        // Buscar nomes das categorias
        foreach (array_keys($categorias) as $categoria_id) {
            // Verificar se a categoria já existe no banco
            $stmt = $pdo->prepare("SELECT id, nome FROM categorias_ml WHERE id = ?");
            $stmt->execute([$categoria_id]);
            $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$categoria) {
                // Buscar nome da categoria na API
                $url = "https://api.mercadolibre.com/categories/{$categoria_id}";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($status == 200) {
                    $categoria_data = json_decode($response, true);
                    
                    // Salvar categoria no banco
                    $stmt = $pdo->prepare("INSERT INTO categorias_ml (id, nome) VALUES (?, ?) ON DUPLICATE KEY UPDATE nome = ?");
                    $stmt->execute([$categoria_id, $categoria_data['name'], $categoria_data['name']]);
                    
                    // Atualizar anúncios com o nome da categoria
                    $stmt = $pdo->prepare("UPDATE anuncios_ml SET categoria_nome = ? WHERE categoria_id = ?");
                    $stmt->execute([$categoria_data['name'], $categoria_id]);
                }
            } else {
                // Atualizar anúncios com o nome da categoria do banco
                $stmt = $pdo->prepare("UPDATE anuncios_ml SET categoria_nome = ? WHERE categoria_id = ?");
                $stmt->execute([$categoria['nome'], $categoria_id]);
            }
        }
        
        // Buscar todos os anúncios atualizados
        $stmt = $pdo->prepare("SELECT * FROM anuncios_ml WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $anuncios_salvos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignorar erros ao atualizar categorias
    }
    
    return $anuncios_salvos;
}

// Função para formatar moeda
function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anúncios do Mercado Livre - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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
        .thumbnail-img {
            max-width: 60px;
            max-height: 60px;
            object-fit: contain;
        }
        .actions-column {
            width: 100px;
        }
        .status-active {
            background-color: #28a745;
        }
        .status-paused {
            background-color: #ffc107;
            color: #212529;
        }
        .status-closed {
            background-color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
 

    <!-- Sidebar -->
    <?php require_once 'barra.php'; ?>

    <!-- Conteúdo principal -->
    <main class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">Anúncios do Mercado Livre</h1>
                <div>
                    <?php if (!$sem_token): ?>
                    <a href="<?php echo $base_url; ?>/vendedor_anuncios.php?sincronizar=1" class="btn btn-warning">
                        <i class="fas fa-sync-alt"></i> Sincronizar Anúncios
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo $base_url; ?>/vendedor_produtos.php" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-box"></i> Produtos
                    </a>
                </div>
            </div>

            <?php if (!empty($ml_user_id) && !empty($ml_nickname)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-user-circle me-2"></i> Usuário autenticado no ML: <strong><?php echo htmlspecialchars($ml_nickname); ?></strong> (ID: <?php echo $ml_user_id; ?>)
                </div>
            <?php endif; ?>

            <?php if (!empty($mensagem)): ?>
                <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensagem; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($sem_token): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i> Conexão com o Mercado Livre necessária</h5>
                    <p>Para visualizar e gerenciar seus anúncios, você precisa conectar sua conta do Mercado Livre.</p>
                    <a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php?acao=autorizar" class="btn btn-primary">
                        <i class="fas fa-link"></i> Conectar ao Mercado Livre
                    </a>
                </div>
            <?php else: ?>
                <?php if ($sincronizou): ?>
                    <div class="alert alert-info">
                        <p><strong>Dica:</strong> Vincule seus anúncios aos produtos cadastrados para facilitar o cálculo de lucratividade.</p>
                    </div>
                <?php endif; ?>
                
                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filtrar Anúncios</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="produto_id" class="form-label">Produto</label>
                                    <select class="form-select" id="produto_id" name="produto_id">
                                        <option value="">Todos os produtos</option>
                                        <?php foreach ($produtos as $produto): ?>
                                            <option value="<?php echo $produto['id']; ?>" <?php echo $filtro_produto_id == $produto['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($produto['nome']); ?>
                                                <?php if (!empty($produto['sku'])): ?> (<?php echo htmlspecialchars($produto['sku']); ?>)<?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">Todos</option>
                                        <option value="active" <?php echo $filtro_status === 'active' ? 'selected' : ''; ?>>Ativos</option>
                                        <option value="paused" <?php echo $filtro_status === 'paused' ? 'selected' : ''; ?>>Pausados</option>
                                        <option value="closed" <?php echo $filtro_status === 'closed' ? 'selected' : ''; ?>>Finalizados</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label for="vinculados" class="form-label">Vinculação</label>
                                    <select class="form-select" id="vinculados" name="vinculados">
                                        <option value="">Todos</option>
                                        <option value="sim" <?php echo $filtro_vinculados === 'sim' ? 'selected' : ''; ?>>Vinculados</option>
                                        <option value="nao" <?php echo $filtro_vinculados === 'nao' ? 'selected' : ''; ?>>Não vinculados</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-2 d-flex align-items-end mb-3">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter"></i> Filtrar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="tabelaAnuncios">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">Imagem</th>
                                        <th>Título</th>
                                        <th>Preço</th>
                                        <th>Tipo</th>
                                        <th>Status</th>
                                        <th>Produto Vinculado</th>
                                        <th class="actions-column">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Buscar anúncios do usuário com filtros
                                    $sql = "SELECT a.*, p.nome as produto_nome, p.custo as produto_custo, c.nome as categoria_nome 
                                            FROM anuncios_ml a 
                                            LEFT JOIN produtos p ON a.produto_id = p.id 
                                            LEFT JOIN categorias_ml c ON a.categoria_id = c.id
                                            {$whereClause} 
                                            ORDER BY a.titulo ASC";
                                    
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute($params);
                                    $anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (count($anuncios) == 0): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <div class="mb-3">
                                                    <i class="fas fa-store fa-3x text-muted"></i>
                                                </div>
                                                <p>Nenhum anúncio encontrado.</p>
                                                
                                                <?php if (empty($filtro_produto_id) && empty($filtro_status) && empty($filtro_vinculados)): ?>
												
												                                              
                                                    <p>Clique no botão "Sincronizar Anúncios" para importar seus anúncios do Mercado Livre.</p>
                                                    <a href="<?php echo $base_url; ?>/vendedor_anuncios.php?sincronizar=1" class="btn btn-warning">
                                                        <i class="fas fa-sync-alt"></i> Sincronizar Anúncios
                                                    </a>
                                                <?php else: ?>
                                                    <p>Tente alterar os filtros para ver mais anúncios.</p>
                                                    <a href="<?php echo $base_url; ?>/vendedor_anuncios.php" class="btn btn-outline-secondary">
                                                        <i class="fas fa-times"></i> Limpar Filtros
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($anuncios as $anuncio): ?>
                                            <?php
                                            $status_class = '';
                                            $status_label = '';
                                            
                                            switch ($anuncio['status']) {
                                                case 'active':
                                                    $status_class = 'status-active';
                                                    $status_label = 'Ativo';
                                                    break;
                                                case 'paused':
                                                    $status_class = 'status-paused';
                                                    $status_label = 'Pausado';
                                                    break;
                                                case 'closed':
                                                    $status_class = 'status-closed';
                                                    $status_label = 'Finalizado';
                                                    break;
                                                default:
                                                    $status_class = 'bg-info';
                                                    $status_label = $anuncio['status'];
                                            }
                                            
                                            $tipo_anuncio = '';
                                            switch ($anuncio['tipo_anuncio']) {
                                                case 'gold_special':
                                                    $tipo_anuncio = 'Premium';
                                                    break;
                                                case 'gold_pro':
                                                    $tipo_anuncio = 'Premium Pro';
                                                    break;
                                                case 'gold':
                                                    $tipo_anuncio = 'Clássico';
                                                    break;
                                                default:
                                                    $tipo_anuncio = $anuncio['tipo_anuncio'];
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($anuncio['thumbnail'])): ?>
                                                        <img src="<?php echo htmlspecialchars($anuncio['thumbnail']); ?>" alt="Miniatura" class="thumbnail-img">
                                                    <?php else: ?>
                                                        <div class="bg-light text-center p-2"><i class="fas fa-image text-muted"></i></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <!-- Título com link para o anúncio -->
                                                    <?php if (!empty($anuncio['permalink'])): ?>
                                                        <a href="<?php echo htmlspecialchars($anuncio['permalink']); ?>" target="_blank" title="Ver no Mercado Livre">
                                                            <?php echo htmlspecialchars($anuncio['titulo']); ?>
                                                            <i class="fas fa-external-link-alt fa-xs ms-1"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($anuncio['titulo']); ?>
                                                    <?php endif; ?>
                                                    
                                                    <!-- ID do ML e categoria -->
                                                    <div>
                                                        <small class="text-muted">ID: <?php echo htmlspecialchars($anuncio['ml_item_id']); ?></small>
                                                        <?php if (!empty($anuncio['categoria_nome'])): ?>
                                                            <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($anuncio['categoria_nome']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                
                                                <td class="text-nowrap"><?php echo formatCurrency($anuncio['preco']); ?></td>
                                                <td><?php echo $tipo_anuncio; ?></td>
                                                <td><span class="badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span></td>
                                                
                                                <!-- Produto vinculado -->
                                                <td>
                                                    <?php if ($anuncio['produto_id']): ?>
                                                        <div class="d-flex align-items-center">
                                                            <a href="<?php echo $base_url; ?>/vendedor_editar_produto.php?id=<?php echo $anuncio['produto_id']; ?>">
                                                                <?php echo htmlspecialchars($anuncio['produto_nome']); ?>
                                                            </a>
                                                            
                                                            <!-- Mostrar custo e margem estimada -->
                                                            <?php if ($anuncio['produto_custo'] > 0): ?>
                                                                <?php
                                                                $taxa_ml = ($tipo_anuncio === 'Premium') ? 0.16 * $anuncio['preco'] : 0.12 * $anuncio['preco'];
                                                                $lucro_estimado = $anuncio['preco'] - $anuncio['produto_custo'] - $taxa_ml;
                                                                $classe_lucro = $lucro_estimado >= 0 ? 'text-success' : 'text-danger';
                                                                ?>
                                                                <span class="ms-2 <?php echo $classe_lucro; ?>" title="Lucro estimado (sem frete)">
                                                                    (<?php echo formatCurrency($lucro_estimado); ?>)
                                                                </span>
                                                            <?php endif; ?>
                                                            
                                                            <!-- Botão para desvincular -->
                                                            <a href="<?php echo $base_url; ?>/vendedor_anuncios.php?desvincular=<?php echo $anuncio['id']; ?>" 
                                                               class="btn btn-sm btn-link text-danger ms-2" 
                                                               onclick="return confirm('Tem certeza que deseja desvincular este anúncio do produto <?php echo htmlspecialchars(addslashes($anuncio['produto_nome'])); ?>?');"
                                                               title="Desvincular">
                                                                <i class="fas fa-unlink"></i>
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#vincularProdutoModal" 
                                                                data-anuncio-id="<?php echo $anuncio['id']; ?>" 
                                                                data-anuncio-titulo="<?php echo htmlspecialchars($anuncio['titulo']); ?>">
                                                            <i class="fas fa-link me-1"></i> Vincular Produto
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <!-- Ações -->
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="<?php echo $base_url; ?>/vendedor_calculadora.php?anuncio_id=<?php echo $anuncio['id']; ?>" 
                                                           class="btn btn-sm btn-outline-success" 
                                                           title="Calcular Lucro">
                                                            <i class="fas fa-calculator"></i>
                                                        </a>
                                                        
                                                        <?php if (!empty($anuncio['permalink'])): ?>
                                                            <a href="<?php echo htmlspecialchars($anuncio['permalink']); ?>" 
                                                               target="_blank" 
                                                               class="btn btn-sm btn-outline-secondary" 
                                                               title="Ver no Mercado Livre">
                                                                <i class="fas fa-external-link-alt"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if (count($anuncios) > 0): ?>
                    <div class="card-footer">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                Mostrando <?php echo count($anuncios); ?> anúncios
                            </div>
                            <div class="col-md-6 text-md-end">
                                <a href="<?php echo $base_url; ?>/vendedor_anuncios.php?sincronizar=1" class="btn btn-sm btn-primary">
                                    <i class="fas fa-sync-alt"></i> Atualizar Anúncios
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal para Vincular Produto -->
    <div class="modal fade" id="vincularProdutoModal" tabindex="-1" aria-labelledby="vincularProdutoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vincularProdutoModalLabel">Vincular Anúncio a Produto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="vincularForm" method="POST" action="">
                        <input type="hidden" name="anuncio_id" id="modalAnuncioId">
                        
                        <div class="mb-3">
                            <p>Selecione o produto para vincular ao anúncio:</p>
                            <p><strong>Anúncio:</strong> <span id="modalAnuncioTitulo"></span></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="produto_id" class="form-label">Produto</label>
                            <select class="form-select" id="modalProdutoId" name="produto_id" required>
                                <option value="">Selecione um produto...</option>
                                <?php foreach ($produtos as $produto): ?>
                                    <option value="<?php echo $produto['id']; ?>">
                                        <?php echo htmlspecialchars($produto['nome']); ?>
                                        <?php if (!empty($produto['sku'])): ?> (<?php echo htmlspecialchars($produto['sku']); ?>)<?php endif; ?>
                                        - Custo: <?php echo formatCurrency($produto['custo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (empty($produtos)): ?>
                        <div class="alert alert-warning">
                            <p>Você ainda não tem produtos cadastrados.</p>
                            <a href="<?php echo $base_url; ?>/vendedor_cadastrar_produto.php" class="btn btn-sm btn-primary">Adicionar Produto</a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo $base_url; ?>/vendedor_cadastrar_produto.php" class="btn btn-link">Criar Novo Produto</a>
                            <div>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" name="vincular_produto" class="btn btn-primary" <?php echo empty($produtos) ? 'disabled' : ''; ?>>Vincular</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar DataTable se houver anúncios
            if ($('#tabelaAnuncios tbody tr').length > 1) { // Mais de uma linha
                $('#tabelaAnuncios').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json',
                    },
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
                });
            }
            
            // Configurar modal de vinculação de produto
            const vincularProdutoModal = document.getElementById('vincularProdutoModal');
            if (vincularProdutoModal) {
                vincularProdutoModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const anuncioId = button.getAttribute('data-anuncio-id');
                    const anuncioTitulo = button.getAttribute('data-anuncio-titulo');
                    
                    document.getElementById('modalAnuncioId').value = anuncioId;
                    document.getElementById('modalAnuncioTitulo').textContent = anuncioTitulo;
                });
            }
        });
    </script>
</body>
</html>
