<?php
// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Location: emergency_login.php");
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
    $sku = isset($_POST['sku']) ? trim($_POST['sku']) : '';
    $custo = isset($_POST['custo']) ? floatval($_POST['custo']) : 0;
    $peso = isset($_POST['peso']) ? floatval($_POST['peso']) : null;
    $dimensoes = isset($_POST['dimensoes']) ? trim($_POST['dimensoes']) : null;
    $categoria_id = isset($_POST['categoria_id']) ? trim($_POST['categoria_id']) : null;
    $descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : null;
    
    // Extrair dimensões (opcional)
    $largura = null;
    $altura = null;
    $comprimento = null;
    
    if (!empty($dimensoes) && preg_match('/(\d+)[x\*](\d+)[x\*](\d+)/i', $dimensoes, $matches)) {
        $comprimento = isset($matches[1]) ? floatval($matches[1]) : null;
        $largura = isset($matches[2]) ? floatval($matches[2]) : null;
        $altura = isset($matches[3]) ? floatval($matches[3]) : null;
    }
    
    // Validar dados
    if (empty($nome)) {
        $mensagem = "O nome do produto é obrigatório.";
        $tipo_mensagem = "danger";
    } elseif ($custo < 0) {
        $mensagem = "O custo do produto não pode ser negativo.";
        $tipo_mensagem = "danger";
    } elseif (empty($sku)) {
        $mensagem = "O SKU é obrigatório.";
        $tipo_mensagem = "danger";
    } else {
        try {
            // Verificar se o SKU já existe
            $sql = "SELECT p.id 
                    FROM produtos p
                    WHERE p.sku = ? AND p.usuario_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$sku, $usuario_id]);
            if ($stmt->fetch()) {
                $mensagem = "Este SKU já está em uso em outro produto.";
                $tipo_mensagem = "danger";
            }
            
            if (empty($mensagem)) {
                // Inserir o produto no banco de dados
                // Note: Estou usando os campos conforme a estrutura atual do banco
                $sql = "INSERT INTO produtos 
                        (usuario_id, sku, nome, preco_custo, peso, dimensoes, 
                        categoria_id, descricao, largura, altura, comprimento, custo) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $usuario_id,
                    $sku,
                    $nome,
                    0.00, // preco_custo definido como 0, usando campo custo em vez disso
                    $peso,
                    $dimensoes,
                    $categoria_id,
                    $descricao,
                    $largura,
                    $altura,
                    $comprimento,
                    $custo  // custo real do produto
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

<?php require_once 'sidebar.php'; ?>
    

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
                                <label for="sku" class="form-label">SKU *</label>
                                <input type="text" class="form-control" id="sku" name="sku" value="<?php echo isset($_POST['sku']) ? htmlspecialchars($_POST['sku']) : ''; ?>" required>
                                <div class="form-text">Código único do produto</div>
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
                                <div class="form-text">Formato: COMPRIMENTOxLARGURAxALTURA</div>
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
