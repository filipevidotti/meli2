<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

// Incluir cabeçalho
$page_title = 'Gerenciar Categorias';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gerenciar Categorias do Mercado Livre</h2>
        <a href="<?php echo ADMIN_URL; ?>/adicionar_categoria.php" class="btn btn-primary">Adicionar Categoria</a>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome da Categoria</th>
                            <th>Taxa (%)</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // Verificar se existe o arquivo taxas.php
                    if (file_exists(BASE_PATH . '/taxas.php')) {
                        require_once(BASE_PATH . '/taxas.php');
                        
                        if (isset($categorias_ml) && is_array($categorias_ml)) {
                            foreach ($categorias_ml as $id => $info) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($id) . '</td>';
                                echo '<td>' . htmlspecialchars($info['nome']) . '</td>';
                                echo '<td>' . htmlspecialchars($info['taxa']) . '%</td>';
                                echo '<td>
                                        <a href="' . ADMIN_URL . '/editar_categoria.php?id=' . htmlspecialchars($id) . '" class="btn btn-sm btn-primary">Editar</a>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="' . htmlspecialchars($id) . '">Excluir</button>
                                      </td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="4" class="text-center">Nenhuma categoria definida no arquivo taxas.php</td></tr>';
                        }
                    } else {
                        echo '<tr><td colspan="4" class="text-center">O arquivo taxas.php não foi encontrado</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
            
            <div class="alert alert-info mt-3">
                <h5>Informações</h5>
                <p>As categorias e taxas do Mercado Livre são definidas no arquivo <code>taxas.php</code> na raiz do projeto.</p>
                <p>As taxas são utilizadas para calcular automaticamente os custos das vendas ao usar a calculadora.</p>
                <p>Mantenha estas informações atualizadas para garantir cálculos precisos.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir esta categoria?</p>
                <p class="text-danger">Atenção: A exclusão afetará diretamente os cálculos das vendas associadas a esta categoria.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">Excluir</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Script para ajustar o link de exclusão no modal
    document.addEventListener('DOMContentLoaded', function() {
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const confirmDeleteLink = document.getElementById('confirmDelete');
                confirmDeleteLink.href = '<?php echo ADMIN_URL; ?>/excluir_categoria.php?id=' + id;
            });
        }
    });
</script>

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer_admin.php');
?>
