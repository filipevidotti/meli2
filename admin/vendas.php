<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

// Incluir cabeçalho
$page_title = 'Gerenciar Vendas';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gerenciar Vendas</h2>
        <a href="<?php echo ADMIN_URL; ?>/adicionar_venda.php" class="btn btn-primary">Registrar Nova Venda</a>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filtrar Vendas</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : date('Y-m-01'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">Data Final</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="vendedor_id" class="form-label">Vendedor</label>
                        <select class="form-select" id="vendedor_id" name="vendedor_id">
                            <option value="">Todos os vendedores</option>
                            <?php
                            $sql = "SELECT v.id, u.nome FROM vendedores v JOIN usuarios u ON v.usuario_id = u.id ORDER BY u.nome";
                            $vendedores = fetchAll($sql);
                            foreach ($vendedores as $v) {
                                $selected = (isset($_GET['vendedor_id']) && $_GET['vendedor_id'] == $v['id']) ? 'selected' : '';
                                echo "<option value=\"{$v['id']}\" {$selected}>{$v['nome']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data</th>
                            <th>Vendedor</th>
                            <th>Produto</th>
                            <th>Valor</th>
                            <th>Custo</th>
                            <th>Taxa ML</th>
                            <th>Lucro</th>
                            <th>Margem</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // Construir a consulta SQL com filtros
                    $where = [];
                    $params = [];
                    
                    if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
                        $where[] = "v.data_venda >= ?";
                        $params[] = $_GET['start_date'];
                    }
                    
                    if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
                        $where[] = "v.data_venda <= ?";
                        $params[] = $_GET['end_date'];
                    }
                    
                    if (isset($_GET['vendedor_id']) && !empty($_GET['vendedor_id'])) {
                        $where[] = "v.vendedor_id = ?";
                        $params[] = $_GET['vendedor_id'];
                    }
                    
                    $whereClause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";
                    
                    // Consulta para buscar vendas com filtros
                    $sql = "SELECT v.*, u.nome as vendedor_nome 
                            FROM vendas v
                            LEFT JOIN vendedores ve ON v.vendedor_id = ve.id
                            LEFT JOIN usuarios u ON ve.usuario_id = u.id
                            $whereClause
                            ORDER BY v.data_venda DESC
                            LIMIT 100";
                    
                    $vendas = fetchAll($sql, $params);
                    
                    if (count($vendas) == 0) {
                        echo '<tr><td colspan="10" class="text-center">Nenhuma venda encontrada.</td></tr>';
                    } else {
                        foreach ($vendas as $venda) {
                            echo '<tr>';
                            echo '<td>' . $venda['id'] . '</td>';
                            echo '<td>' . formatDate($venda['data_venda']) . '</td>';
                            echo '<td>' . htmlspecialchars($venda['vendedor_nome'] ?? 'N/A') . '</td>';
                            echo '<td>' . htmlspecialchars($venda['produto']) . '</td>';
                            echo '<td>' . formatCurrency($venda['valor_venda']) . '</td>';
                            echo '<td>' . formatCurrency($venda['custo_produto']) . '</td>';
                            echo '<td>' . formatCurrency($venda['taxa_ml']) . '</td>';
                            echo '<td>' . formatCurrency($venda['lucro']) . '</td>';
                            echo '<td>' . formatPercentage($venda['margem_lucro']) . '</td>';
                            echo '<td>
                                    <a href="' . ADMIN_URL . '/visualizar_venda.php?id=' . $venda['id'] . '" class="btn btn-sm btn-info" title="Detalhes"><i class="fas fa-eye"></i></a>
                                    <a href="' . ADMIN_URL . '/editar_venda.php?id=' . $venda['id'] . '" class="btn btn-sm btn-primary" title="Editar"><i class="fas fa-edit"></i></a>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="' . $venda['id'] . '" title="Excluir"><i class="fas fa-trash"></i></button>
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
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Tem certeza que deseja excluir esta venda? Esta ação não pode ser desfeita.
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
                confirmDeleteLink.href = '<?php echo ADMIN_URL; ?>/excluir_venda.php?id=' + id;
            });
        }
    });
</script>

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer_admin.php');
?>
