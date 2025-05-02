<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

// Processar exclusão se solicitado
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    // Verificar se não é o próprio usuário admin tentando se excluir
    if ($id != $_SESSION['user_id']) {
        $sql = "DELETE FROM vendedores WHERE id = ?";
        executeQuery($sql, [$id]);
        // Redirecionar para evitar reenvio do formulário
        header('Location: ' . ADMIN_URL . '/vendedores.php?success=1');
        exit;
    }
}

// Incluir cabeçalho
$page_title = 'Gerenciar Vendedores';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gerenciar Vendedores</h2>
        <a href="<?php echo ADMIN_URL; ?>/adicionar_vendedor.php" class="btn btn-primary">Adicionar Novo Vendedor</a>
    </div>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Operação realizada com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Data de Cadastro</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Buscar todos os vendedores com seus dados de usuário
                    $sql = "SELECT v.id, u.nome, u.email, u.data_criacao, u.status 
                            FROM vendedores v 
                            JOIN usuarios u ON v.usuario_id = u.id 
                            ORDER BY u.nome ASC";
                    
                    $vendedores = fetchAll($sql);
                    
                    if (count($vendedores) == 0) {
                        echo '<tr><td colspan="6" class="text-center">Nenhum vendedor cadastrado.</td></tr>';
                    } else {
                        foreach ($vendedores as $vendedor) {
                            echo '<tr>';
                            echo '<td>' . $vendedor['id'] . '</td>';
                            echo '<td>' . htmlspecialchars($vendedor['nome']) . '</td>';
                            echo '<td>' . htmlspecialchars($vendedor['email']) . '</td>';
                            echo '<td>' . formatDate($vendedor['data_criacao']) . '</td>';
                            echo '<td>' . ($vendedor['status'] === 'ativo' ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>') . '</td>';
                            echo '<td>
                                    <a href="' . ADMIN_URL . '/editar_vendedor.php?id=' . $vendedor['id'] . '" class="btn btn-sm btn-primary">Editar</a>
                                    <a href="' . ADMIN_URL . '/visualizar_vendedor.php?id=' . $vendedor['id'] . '" class="btn btn-sm btn-info">Detalhes</a>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="' . $vendedor['id'] . '">Excluir</button>
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

<!-- Modal de Confirmação de Exclusão -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Tem certeza que deseja excluir este vendedor? Esta ação não pode ser desfeita.
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
                confirmDeleteLink.href = '<?php echo ADMIN_URL; ?>/vendedores.php?delete=' + id;
            });
        }
    });
</script>

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer_admin.php');
?>
