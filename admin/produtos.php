<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

// Processar exclusão se solicitada
$mensagem = '';
$tipo_mensagem = '';

if (isset($_POST['excluir']) && isset($_POST['produto_id'])) {
    $produto_id = (int)$_POST['produto_id'];
    
    try {
        // Verificar se o produto está sendo usado em anúncios
        $sql = "SELECT COUNT(*) FROM anuncios_ml WHERE produto_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$produto_id]);
        $usado_anuncios = $stmt->fetchColumn();
        
        if ($usado_anuncios > 0) {
            $mensagem = "Não é possível excluir este produto pois está vinculado a {$usado_anuncios} anúncios.";
            $tipo_mensagem = "warning";
        } else {
            // Excluir o produto
            $sql = "DELETE FROM produtos WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$produto_id]);
            
            $mensagem = "Produto excluído com sucesso!";
            $tipo_mensagem = "success";
        }
    } catch (PDOException $e) {
        $mensagem = "Erro ao excluir produto: " . $e->getMessage();
        $tipo_mensagem = "danger";
    }
}

// Incluir cabeçalho
$page_title = 'Gerenciar Produtos';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gerenciar Produtos</h2>
        <a href="<?php echo BASE_URL; ?>/admin/adicionar_produto.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Adicionar Produto
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
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>SKU</th>
                            <th>Custo</th>
                            <th>Anúncios</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // Buscar produtos
                            $sql = "SELECT p.*, COUNT(a.id) as anuncios_count 
                                    FROM produtos p
                                    LEFT JOIN anuncios_ml a ON a.produto_id = p.id
                                    GROUP BY p.id
                                    ORDER BY p.nome ASC";
                            
                            $produtos = fetchAll($sql);
                            
                            if (count($produtos) == 0) {
                                echo '<tr><td colspan="6" class="text-center py-4">
                                        <div class="mb-3">
                                            <i class="fas fa-box fa-3x text-muted"></i>
                                        </div>
                                        <p>Nenhum produto cadastrado.</p>
                                        <a href="' . BASE_URL . '/admin/adicionar_produto.php" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Adicionar Produto
                                        </a>
                                    </td></tr>';
                            } else {
                                foreach ($produtos as $produto) {
                                    echo '<tr>';
                                    echo '<td>' . $produto['id'] . '</td>';
                                    echo '<td>' . htmlspecialchars($produto['nome']) . '</td>';
                                    echo '<td>' . htmlspecialchars($produto['sku'] ?? 'N/A') . '</td>';
                                    echo '<td>' . formatCurrency($produto['custo']) . '</td>';
                                    
                                    // Anúncios vinculados
                                    echo '<td>';
                                    if ($produto['anuncios_count'] > 0) {
                                        echo '<span class="badge bg-success">';
                                        echo $produto['anuncios_count'] . ' anúncio(s)';
                                        echo '</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">Nenhum</span>';
                                    }
                                    echo '</td>';
                                    
                                    // Ações
                                    echo '<td>
                                            <a href="' . BASE_URL . '/admin/editar_produto.php?id=' . $produto['id'] . '" class="btn btn-sm btn-warning" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" title="Excluir" 
                                                    onclick="confirmarExclusao(' . $produto['id'] . ', \'' . htmlspecialchars(addslashes($produto['nome'])) . '\')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                          </td>';
                                    echo '</tr>';
                                }
                            }
                        } catch (PDOException $e) {
                            echo '<tr><td colspan="6" class="text-center">Erro ao carregar produtos: ' . $e->getMessage() . '</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o produto <strong id="productName"></strong>?</p>
                <p class="text-danger">Esta ação não poderá ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="">
                    <input type="hidden" id="deleteProductId" name="produto_id">
                    <input type="hidden" name="excluir" value="1">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarExclusao(produtoId, produtoNome) {
    document.getElementById('productName').textContent = produtoNome;
    document.getElementById('deleteProductId').value = produtoId;
    
    var modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    modal.show();
}
</script>

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer_admin.php');
?>
