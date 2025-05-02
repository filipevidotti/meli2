<?php
// Iniciar sessão
session_start();

// Verificar acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Location: login.php");
    exit;
}

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: vendedor_produtos.php");
    exit;
}

$produto_id = intval($_GET['id']);

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

// Verificar se o produto pertence ao vendedor
$sql = "SELECT p.* 
        FROM produtos p 
        JOIN vendedores v ON p.usuario_id = v.usuario_id 
        WHERE p.id = ? AND v.usuario_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$produto_id, $usuario_id]);
$produto = $stmt->fetch();

if (!$produto) {
    $_SESSION['mensagem'] = "Produto não encontrado ou você não tem permissão para editá-lo.";
    $_SESSION['tipo_mensagem'] = "danger";
    header("Location: " . $base_url . "/vendedor_produtos.php");
    exit;
}

// Buscar categorias do Mercado Livre
$categorias_ml = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM categorias_ml ORDER BY nome");
    while ($row = $stmt->fetch()) {
        $categorias_ml[$row['id']] = [
            'id' => $row['id'],
            'nome' => $row['nome']
        ];
    }
} catch (PDOException $e) {
    // Silenciar erro, usa array vazio
}

// Buscar anúncios vinculados
$anuncios_vinculados = [];
try {
    $stmt = $pdo->prepare("SELECT id, titulo, preco FROM anuncios_ml WHERE produto_id = ?");
    $stmt->execute([$produto_id]);
    $anuncios_vinculados = $stmt->fetchAll();
} catch (PDOException $e) {
    // Silenciar erro
}

// Buscar anúncios não vinculados
$anuncios_nao_vinculados = [];
try {
    $sql = "SELECT a.id, a.titulo, a.preco 
            FROM anuncios_ml a 
            JOIN vendedores v ON a.usuario_id = v.usuario_id 
            WHERE v.usuario_id = ? AND a.produto_id IS NULL
            ORDER BY a.titulo";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $anuncios_nao_vinculados = $stmt->fetchAll();
} catch (PDOException $e) {
    // Silenciar erro
}

// Processar o formulário quando enviado
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter dados do formulário
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $sku = isset($_POST['sku']) ? trim($_POST['sku']) : null;
    $custo = isset($_POST['custo']) ? floatval($_POST['custo']) : 0;
    $peso = isset($_POST['peso']) ? floatval($_POST['peso']) : null;
    $dimensoes = isset($_POST['dimensoes']) ? trim($_POST['dimensoes']) : null;
    $categoria_id = isset($_POST['categoria_id']) ? trim($_POST['categoria_id']) : null;
    $descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : null;
    
    // Validar dados
    if (empty($nome)) {
        $mensagem = "O nome do produto é obrigatório.";
        $tipo_mensagem = "danger";
    } elseif ($custo < 0) {
        $mensagem = "O custo do produto não pode ser negativo.";
        $tipo_mensagem = "danger";
    } else {
        try {
            // Verificar se o SKU já existe em outro produto
            if (!empty($sku)) {
                $sql = "SELECT p.id 
                        FROM produtos p
                        JOIN vendedores v ON p.usuario_id = v.usuario_id
                        WHERE p.sku = ? AND v.usuario_id = ? AND p.id != ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$sku, $usuario_id, $produto_id]);
                if ($stmt->fetch()) {
                    $mensagem = "Este SKU já está em uso em outro produto.";
                    $tipo_mensagem = "danger";
                }
            }
            
            if (empty($mensagem)) {
                // Atualizar o produto
                $sql = "UPDATE produtos 
                        SET nome = ?, sku = ?, custo = ?, peso = ?, 
                        dimensoes = ?, categoria_id = ?, descricao = ? 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nome,
                    $sku,
                    $custo,
                    $peso,
                    $dimensoes,
                    $categoria_id,
                    $descricao,
                    $produto_id
                ]);
                
                // Desvincular anúncios
                if (isset($_POST['desvincular_anuncios']) && is_array($_POST['desvincular_anuncios'])) {
                    foreach ($_POST['desvincular_anuncios'] as $anuncio_id) {
                        $sql = "UPDATE anuncios_ml SET produto_id = NULL 
                                WHERE id = ? AND produto_id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$anuncio_id, $produto_id]);
                    }
                }
                
                // Vincular novos anúncios
                if (isset($_POST['vincular_anuncios']) && is_array($_POST['vincular_anuncios'])) {
                    foreach ($_POST['vincular_anuncios'] as $anuncio_id) {
                        $sql = "UPDATE anuncios_ml SET produto_id = ? 
                                WHERE id = ? AND usuario_id = ? AND produto_id IS NULL";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$produto_id, $anuncio_id, $usuario_id]);
                    }
                }
                
                // Atualizar os dados do produto na variável
                $produto['nome'] = $nome;
                $produto['sku'] = $sku;
                $produto['custo'] = $custo;
                $produto['peso'] = $peso;
                $produto['dimensoes'] = $dimensoes;
                $produto['categoria_id'] = $categoria_id;
                $produto['descricao'] = $descricao;
                
                // Mostrar mensagem de sucesso
                $mensagem = "Produto atualizado com sucesso!";
                $tipo_mensagem = "success";
                
                // Atualizar lista de anúncios
                $stmt = $pdo->prepare("SELECT id, titulo, preco FROM anuncios_ml WHERE produto_id = ?");
                $stmt->execute([$produto_id]);
                $anuncios_vinculados = $stmt->fetchAll();
                
                $sql = "SELECT a.id, a.titulo, a.preco 
                        FROM anuncios_ml a 
                        JOIN vendedores v ON a.usuario_id = v.usuario_id 
                        WHERE v.usuario_id = ? AND a.produto_id IS NULL
                        ORDER BY a.titulo";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$usuario_id]);
                $anuncios_nao_vinculados = $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            $mensagem = "Erro ao atualizar produto: " . $e->getMessage();
            $tipo_mensagem = "danger";
        }
    }
}

function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Produto - CalcMeli</title>
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
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo $base_url; ?>/vendedor_produtos.php">
                        <i class="fas fa-box"></i>
                        Produtos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_vendas.php">
                        <i class="fas fa-shopping-cart"></i>
                        Vendas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_anuncios.php">
                        <i class="fas fa-ad"></i>
                        Anúncios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_relatorios.php">
                        <i class="fas fa-chart-bar"></i>
                        Relatórios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_config.php">
                        <i class="fas fa-cog"></i>
                        Configurações
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Editar Produto</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="<?php echo $base_url; ?>/vendedor_produtos.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
        
        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nome" class="form-label">Nome do Produto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($produto['nome']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="sku" class="form-label">SKU</label>
                            <input type="text" class="form-control" id="sku" name="sku" value="<?php echo htmlspecialchars($produto['sku'] ?? ''); ?>">
                            <div class="form-text">Código único para identificar seu produto</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="custo" class="form-label">Custo (R$) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="custo" name="custo" value="<?php echo htmlspecialchars($produto['custo']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="peso" class="form-label">Peso (kg)</label>
                            <input type="number" step="0.001" min="0" class="form-control" id="peso" name="peso" value="<?php echo htmlspecialchars($produto['peso'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="dimensoes" class="form-label">Dimensões (cm)</label>
                            <input type="text" class="form-control" id="dimensoes" name="dimensoes" placeholder="Ex: 10x20x30" value="<?php echo htmlspecialchars($produto['dimensoes'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="categoria_id" class="form-label">Categoria</label>
                        <select class="form-select" id="categoria_id" name="categoria_id">
                            <option value="">Selecione uma categoria...</option>
                            <?php foreach ($categorias_ml as $key => $categoria): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($produto['categoria_id'] == $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Categoria do produto no Mercado Livre</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php echo htmlspecialchars($produto['descricao'] ?? ''); ?></textarea>
                    </div>
                    
                    <?php if (!empty($anuncios_vinculados)): ?>
                    <div class="mb-3">
                        <label class="form-label">Anúncios Vinculados</label>
                        <div class="card">
                            <div class="card-body">
                                <p class="text-muted">Os seguintes anúncios estão vinculados a este produto:</p>
                                <?php foreach ($anuncios_vinculados as $anuncio): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="desvincular_anuncios[]" value="<?php echo $anuncio['id']; ?>" id="desvincular_<?php echo $anuncio['id']; ?>">
                                    <label class="form-check-label" for="desvincular_<?php echo $anuncio['id']; ?>">
                                        <?php echo htmlspecialchars($anuncio['titulo']); ?> (<?php echo formatCurrency($anuncio['preco']); ?>)
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                <div class="form-text">Marque os anúncios que deseja desvincular deste produto</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($anuncios_nao_vinculados)): ?>
                    <div class="mb-3">
                        <label class="form-label">Vincular a Anúncios</label>
                        <div class="card">
                            <div class="card-body">
                                <p class="text-muted">Selecione os anúncios que deseja vincular a este produto:</p>
                                <?php foreach ($anuncios_nao_vinculados as $anuncio): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="vincular_anuncios[]" value="<?php echo $anuncio['id']; ?>" id="anuncio_<?php echo $anuncio['id']; ?>">
                                    <label class="form-check-label" for="anuncio_<?php echo $anuncio['id']; ?>">
                                        <?php echo htmlspecialchars($anuncio['titulo']); ?> (<?php echo formatCurrency($anuncio['preco']); ?>)
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        <a href="<?php echo $base_url; ?>/vendedor_produtos.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
