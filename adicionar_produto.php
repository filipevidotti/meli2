<?php
// Iniciar sessão
session_start();

// Incluir funções de autenticação
require_once 'functions/auth.php';

// Verificar acesso
protegerPagina('vendedor', 'emergency_login.php');

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
    // Silenciar erro, usar array vazio
}

// Buscar anúncios não vinculados
$anuncios_nao_vinculados = [];
try {
    $sql = "SELECT a.id, a.titulo, a.preco 
            FROM anuncios_ml a 
            LEFT JOIN produtos p ON a.produto_id = p.id
            WHERE a.usuario_id = ? AND a.produto_id IS NULL
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
            // Verificar se o SKU já existe
            if (!empty($sku)) {
                $sql = "SELECT p.id 
                        FROM produtos p
                        WHERE p.sku = ? AND p.usuario_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$sku, $usuario_id]);
                if ($stmt->fetch()) {
                    $mensagem = "Este SKU já está em uso em outro produto.";
                    $tipo_mensagem = "danger";
                }
            }
            
            if (empty($mensagem)) {
                // Verificar se a tabela existe
                try {
                    $check_table = "SHOW TABLES LIKE 'produtos'";
                    $check_stmt = $pdo->query($check_table);
                    $table_exists = $check_stmt->rowCount() > 0;
                    
                    if (!$table_exists) {
                        // Criar tabela produtos
                        $create_table = "CREATE TABLE produtos (
                            id INT(11) NOT NULL AUTO_INCREMENT,
                            usuario_id INT(11) NOT NULL,
                            nome VARCHAR(255) NOT NULL,
                            sku VARCHAR(50) NULL,
                            custo DECIMAL(10,2) NOT NULL DEFAULT 0,
                            peso DECIMAL(10,3) NULL,
                            dimensoes VARCHAR(50) NULL,
                            categoria_id VARCHAR(50) NULL,
                            descricao TEXT NULL,
                            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (id),
                            KEY idx_usuario_id (usuario_id),
                            KEY idx_sku (sku)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                        
                        $pdo->exec($create_table);
                    }
                } catch (PDOException $e) {
                    // Ignorar erro, tentamos inserir de qualquer forma
                }
                
                // Inserir o produto no banco de dados
                $sql = "INSERT INTO produtos (usuario_id, nome, sku, custo, peso, dimensoes, categoria_id, descricao) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $usuario_id,
                    $nome,
                    $sku,
                    $custo,
                    $peso,
                    $dimensoes,
                    $categoria_id,
                    $descricao
                ]);
                
                $produto_id = $pdo->lastInsertId();
                
                // Verificar se há anúncios para vincular
                if (isset($_POST['vincular_anuncios']) && is_array($_POST['vincular_anuncios'])) {
                    foreach ($_POST['vincular_anuncios'] as $anuncio_id) {
                        $sql = "UPDATE anuncios_ml SET produto_id = ? 
                                WHERE id = ? AND usuario_id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$produto_id, $anuncio_id, $usuario_id]);
                    }
                }
                
                // Redirecionar para a página de produtos com mensagem de sucesso
                $_SESSION['mensagem'] = "Produto adicionado com sucesso!";
                $_SESSION['tipo_mensagem'] = "success";
                header("Location: " . $base_url . "/vendedor_produtos.php");
                exit;
            }
        } catch (PDOException $e) {
            $mensagem = "Erro ao adicionar produto: " . $e->getMessage();
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
    <title>Cadastrar Produto - CalcMeli</title>
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
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/emergency_login.php?logout=1"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
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
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo $base_url; ?>/vendedor_produtos.php">
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
                <h1 class="h2">Cadastrar Novo Produto</h1>
                <a href="<?php echo $base_url; ?>/vendedor_produtos.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>

            <?php if (!empty($mensagem)): ?>
                <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensagem; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informações do Produto</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label for="nome" class="form-label">Nome do Produto *</label>
                                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="sku" class="form-label">SKU</label>
                                <input type="text" class="form-control" id="sku" name="sku" value="<?php echo isset($_POST['sku']) ? htmlspecialchars($_POST['sku']) : ''; ?>">
                                <div class="form-text">Código único do produto (opcional)</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="custo" class="form-label">Custo (R$) *</label>
                                <input type="number" class="form-control" id="custo" name="custo" step="0.01" min="0" value="<?php echo isset($_POST['custo']) ? htmlspecialchars($_POST['custo']) : '0.00'; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="peso" class="form-label">Peso (kg)</label>
                                <input type="number" class="form-control" id="peso" name="peso" step="0.001" min="0" value="<?php echo isset($_POST['peso']) ? htmlspecialchars($_POST['peso']) : ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="dimensoes" class="form-label">Dimensões</label>
                                <input type="text" class="form-control" id="dimensoes" name="dimensoes" placeholder="Ex: 20x15x10cm" value="<?php echo isset($_POST['dimensoes']) ? htmlspecialchars($_POST['dimensoes']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="categoria_id" class="form-label">Categoria no Mercado Livre</label>
                            <select class="form-select" id="categoria_id" name="categoria_id">
                                <option value="">Selecione uma categoria...</option>
                                <?php foreach ($categorias_ml as $categoria): ?>
                                    <option value="<?php echo htmlspecialchars($categoria['id']); ?>" <?php echo (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $categoria['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="4"><?php echo isset($_POST['descricao']) ? htmlspecialchars($_POST['descricao']) : ''; ?></textarea>
                        </div>
                        
                        <?php if (!empty($anuncios_nao_vinculados)): ?>
                        <div class="mb-4">
                            <label class="form-label">Vincular a Anúncios</label>
                            <div class="card">
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($anuncios_nao_vinculados as $anuncio): ?>
                                            <label class="list-group-item">
                                                <input class="form-check-input me-2" type="checkbox" name="vincular_anuncios[]" value="<?php echo $anuncio['id']; ?>">
                                                <?php echo htmlspecialchars($anuncio['titulo']); ?>
                                                <span class="text-muted ms-2"><?php echo formatCurrency($anuncio['preco']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save"></i> Salvar Produto
                            </button>
                            <a href="<?php echo $base_url; ?>/vendedor_produtos.php" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Formatar campo de custo como moeda
        document.addEventListener('DOMContentLoaded', function() {
            const custoInput = document.getElementById('custo');
            custoInput.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.value = '0.00';
                } else {
                    const value = parseFloat(this.value);
                    if (!isNaN(value)) {
                        this.value = value.toFixed(2);
                    }
                }
            });
        });
    </script>
</body>
</html>
