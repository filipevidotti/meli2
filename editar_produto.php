<?php
include_once 'includes/header.php';
include_once 'includes/conexao.php';
include_once 'includes/taxas.php';

// Verificar se ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: produtos_salvos.php');
    exit;
}

$produto_id = intval($_GET['id']);

// Buscar dados do produto
$sql = "SELECT * FROM produtos WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':id', $produto_id);
$stmt->execute();
$produto = $stmt->fetch();

// Se produto não existir, redirecionar
if (!$produto) {
    header('Location: produtos_salvos.php');
    exit;
}
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h2>Editar Produto</h2>
            <a href="produtos_salvos.php" class="btn btn-light">Voltar</a>
        </div>
        <div class="card-body">
            <form id="form-edit-product" action="atualizar_produto.php" method="post">
                <input type="hidden" name="id" value="<?= $produto['id'] ?>">
                
                <!-- Dados do Produto -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h4>Dados do Produto</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nome_produto">Nome do Produto:</label>
                                    <input type="text" class="form-control" name="nome_produto" value="<?= htmlspecialchars($produto['nome']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="sku">SKU/Código:</label>
                                    <input type="text" class="form-control" name="sku" value="<?= htmlspecialchars($produto['sku']) ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="categoria">Categoria no Mercado Livre:</label>
                                    <select class="form-control" name="categoria" required>
                                        <option value="">Selecione uma categoria</option>
                                        <?php foreach($categorias_ml as $cat_id => $cat_data): ?>
                                            <option value="<?= $cat_id ?>" data-taxa="<?= $cat_data['taxa'] ?>" <?= ($produto['categoria_id'] == $cat_id) ? 'selected' : '' ?>>
                                                <?= $cat_data['nome'] ?> (<?= $cat_data['taxa'] ?>%)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tipo_anuncio">Tipo de Anúncio:</label>
                                    <select class="form-control" name="tipo_anuncio" required>
                                        <option value="classico" <?= ($produto['tipo_anuncio'] == 'classico') ? 'selected' : '' ?>>Clássico (Grátis)</option>
                                        <option value="premium" <?= ($produto['tipo_anuncio'] == 'premium') ? 'selected' : '' ?>>Premium (R$ 5,00)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_supermercado" name="is_supermercado" <?= $produto['is_supermercado'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_supermercado">
                                        <strong>Categoria Supermercado</strong>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_categoria_especial" name="is_categoria_especial" <?= $produto['is_categoria_especial'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_categoria_especial">
                                        <strong>Categoria Especial</strong>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_full" name="is_full" <?= $produto['is_full'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_full">
                                        <strong>Mercado Livre Full</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Custos e Precificação -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h4>Custos e Precificação</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="preco_venda">Preço de Venda (R$):</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="preco_venda" value="<?= $produto['preco_venda'] ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="preco_custo">Custo (R$):</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="preco_custo" value="<?= $produto['custo'] ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Envio -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h4>Dados de Envio</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="peso">Peso do Produto (kg):</label>
                                    <input type="number" step="0.1" min="0" class="form-control" name="peso" value="<?= $produto['peso'] ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="regiao_origem">Região de Origem:</label>
                                    <select class="form-control" name="regiao_origem">
                                        <option value="sul_sudeste" <?= ($produto['regiao_origem'] == 'sul_sudeste') ? 'selected' : '' ?>>Sul/Sudeste/Centro-Oeste</option>
                                        <option value="nordeste" <?= ($produto['regiao_origem'] == 'nordeste') ? 'selected' : '' ?>>Norte/Nordeste</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="estado_produto">Estado do Produto:</label>
                                    <select class="form-control" name="estado_produto">
                                        <option value="novo" <?= ($produto['estado_produto'] == 'novo') ? 'selected' : '' ?>>Novo</option>
                                        <option value="usado" <?= ($produto['estado_produto'] == 'usado') ? 'selected' : '' ?>>Usado</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notas -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h4>Notas Adicionais</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <textarea class="form-control" name="notas" rows="3"><?= htmlspecialchars($produto['notas']) ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Botões de ação -->
                <div class="text-center mb-4">
                    <button type="submit" class="btn btn-success btn-lg">Salvar Alterações</button>
                    <a href="produtos_salvos.php" class="btn btn-secondary btn-lg ms-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Submit do formulário via AJAX
    $('#form-edit-product').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'atualizar_produto.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Produto atualizado com sucesso!');
                    window.location.href = 'produtos_salvos.php';
                } else {
                    alert('Erro ao atualizar produto: ' + response.error);
                }
            },
            error: function() {
                alert('Erro ao processar a solicitação.');
            }
        });
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
