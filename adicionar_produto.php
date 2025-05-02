<?php
// adicionar_produto.php
require_once('init.php');

// Proteger a página
protegerPagina();

// Inicializar variáveis
$mensagem = '';
$tipo_mensagem = '';
$erro = '';

// Processar o formulário quando enviado
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
        $erro = "O nome do produto é obrigatório.";
    } elseif ($custo < 0) {
        $erro = "O custo do produto não pode ser negativo.";
    } else {
        try {
            // Verificar se o SKU já existe para este usuário
            if (!empty($sku)) {
                $sql = "SELECT id FROM produtos WHERE usuario_id = ? AND sku = ? AND id != ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_SESSION['user_id'], $sku, 0]); // 0 para adicionar novo
                if ($stmt->fetch()) {
                    $erro = "Este SKU já está em uso em outro produto.";
                }
            }
            
            if (empty($erro)) {
                // Inserir o produto no banco de dados
                $sql = "INSERT INTO produtos (usuario_id, nome, sku, custo, peso, dimensoes, categoria_id, descricao) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_SESSION['user_id'],
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
                        $sql = "UPDATE anuncios_ml SET produto_id = ? WHERE id = ? AND usuario_id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$produto_id, $anuncio_id, $_SESSION['user_id']]);
                    }
                }
                
                // Redirecionar para a página de produtos com mensagem de sucesso
                $_SESSION['mensagem'] = "Produto adicionado com sucesso!";
                $_SESSION['tipo_mensagem'] = "success";
                header("Location: " . BASE_URL . "/produtos.php");
                exit;
            }
        } catch (PDOException $e) {
            $erro = "Erro ao adicionar produto: " . $e->getMessage();
        }
    }
    
    // Se chegou aqui, houve erro
    $mensagem = $erro;
    $tipo_mensagem = "danger";
}

// Buscar anúncios não vinculados para sugerir vinculação
$anuncios_nao_vinculados = [];
try {
    $sql = "SELECT id, titulo, preco FROM anuncios_ml WHERE usuario_id = ? AND produto_id IS NULL ORDER BY titulo";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
    $anuncios_nao_vinculados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro
}

// Incluir cabeçalho
$page_title = 'Adicionar Produto';
include(INCLUDES_DIR . '/header.php');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Adicionar Produto</h2>
        <a href="<?php echo BASE_URL; ?>/produtos.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para Lista
        </a>
    </div>
    
    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensagem; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome do Produto <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="sku" class="form-label">SKU</label>
                        <input type="text" class="form-control" id="sku" name="sku" value="<?php echo isset($_POST['sku']) ? htmlspecialchars($_POST['sku']) : ''; ?>">
                        <div class="form-text">Código único para identificar seu produto</div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="custo" class="form-label">Custo (R$) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control" id="custo" name="custo" value="<?php echo isset($_POST['custo']) ? htmlspecialchars($_POST['custo']) : ''; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="peso" class="form-label">Peso (kg)</label>
                        <input type="number" step="0.001" min="0" class="form-control" id="peso" name="peso" value="<?php echo isset($_POST['peso']) ? htmlspecialchars($_POST['peso']) : ''; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="dimensoes" class="form-label">Dimensões (cm)</label>
                        <input type="text" class="form-control" id="dimensoes" name="dimensoes" placeholder="Ex: 10x20x30" value="<?php echo isset($_POST['dimensoes']) ? htmlspecialchars($_POST['dimensoes']) : ''; ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="categoria_id" class="form-label">Categoria</label>
                    <input type="text" class="form-control" id="categoria_id" name="categoria_id" value="<?php echo isset($_POST['categoria_id']) ? htmlspecialchars($_POST['categoria_id']) : ''; ?>">
                    <div class="form-text">Categoria do produto no Mercado Livre</div>
                </div>
                
                <div class="mb-3">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php echo isset($_POST['descricao']) ? htmlspecialchars($_POST['descricao']) : ''; ?></textarea>
                </div>
                
                <?php if (!empty($anuncios_nao_vinculados)): ?>
                <div class="mb-3">
                    <label class="form-label">Vincular a Anúncios</label>
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted">Selecione os anúncios que correspondem a este produto:</p>
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
                    <button type="submit" class="btn btn-primary">Adicionar Produto</button>
                    <a href="<?php echo BASE_URL; ?>/produtos.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Incluir rodapé
include(INCLUDES_DIR . '/footer.php');
?>
