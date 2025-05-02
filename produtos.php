<?php
// produtos.php
require_once('init.php');

// Proteger a página
protegerPagina();

// Processar exclusão se solicitada
$mensagem = '';
$tipo_mensagem = '';

if (isset($_POST['excluir']) && isset($_POST['produto_id'])) {
    $produto_id = (int)$_POST['produto_id'];
    
    try {
        // Verificar se o produto pertence ao usuário atual
        $sql = "SELECT id FROM produtos WHERE id = ? AND usuario_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$produto_id, $_SESSION['user_id']]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$produto) {
            $mensagem = "Produto não encontrado ou não pertence ao usuário atual.";
            $tipo_mensagem = "danger";
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
include(INCLUDES_DIR . '/header.php');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Meus Produtos</h2>
        <div>
            <a href="<?php echo BASE_URL; ?>/adicionar_produto.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Adicionar Produto
            </a>
            <a href="<?php echo BASE_URL; ?>/anuncios.php" class="btn btn-outline-primary ms-2">
                <i class="fas fa-store"></i> Gerenciar Anúncios
            </a>
        </div>
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
                            <th>Nome</th>
                            <th>SKU</th>
                            <th>Custo</th>
                            <th>Anúncios</th>
                            <th>Calculadora</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Buscar produtos do usuário
                        $sql = "SELECT p.*, COUNT(a.id) as anuncios_count 
                                FROM produtos p
                                LEFT JOIN anuncios_ml a ON a.produto_id = p.id
                                WHERE p.usuario_id = ?
                                GROUP BY p.id
                                ORDER BY p.nome ASC";
                        
                        $produtos = fetchAll($sql, [$_SESSION['user_id']]);
                        
                        if (count($produtos) == 0) {
                            echo '<tr><td colspan="6" class="text-center py-4">
                                    <div class="mb-3">
                                        <i class="fas fa-box fa-3x text-muted"></i>
                                    </div>
                                    <p>Nenhum produto cadastrado.</p>
                                    <a href="' . BASE_URL . '/adicionar_produto.php" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Adicionar Produto
                                    </a>
                                </td></tr>';
                        } else {
                            foreach ($produtos as $produto) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($produto['nome']) . '</td>';
                                echo '<td>' . htmlspecialchars($produto['sku'] ?? 'N/A') . '</td>';
                                echo '<td>' . formatCurrency($produto['custo']) . '</td>';
                                
                                // Anúncios vinculados
                                echo '<td>';
                                if ($produto['anuncios_count'] > 0) {
                                    echo '<a href="' . BASE_URL . '/anuncios.php?produto_id=' . $produto['id'] . '" class="badge bg-success">';
                                    echo $produto['anuncios_count'] . ' anúncio(s)';
                                    echo '</a>';
                                } else {
                                    echo '<span class="badge bg-secondary">Nenhum</span>';
                                }
                                echo '</td>';
                                
                                // Link para calculadora
                                echo '<td>
                                        <a href="' . BASE_URL . '/calculadora.php?produto_id=' . $produto['id'] . '" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-calculator"></i> Calcular
                                        </a>
                                      </td>';
                                
                                // Ações
                                echo '<td>
                                        <a href="' . BASE_URL . '/editar_produto.php?id=' . $produto['id'] . '" class="btn btn-sm btn-warning" title="Editar">
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
include(INCLUDES_DIR . '/footer.php');
?>
