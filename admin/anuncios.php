<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

// Incluir cabeçalho
$page_title = 'Anúncios do Mercado Livre';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Anúncios do Mercado Livre</h2>
        <a href="<?php echo BASE_URL; ?>/admin/sincronizar_anuncios.php" class="btn btn-primary">
            <i class="fas fa-sync-alt"></i> Sincronizar Anúncios
        </a>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filtrar Anúncios</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-3">
                        <label for="vendedor_id" class="form-label">Vendedor</label>
                        <select class="form-select" id="vendedor_id" name="vendedor_id">
                            <option value="">Todos os vendedores</option>
                            <?php
                            $sql = "SELECT v.id, u.nome FROM vendedores v JOIN usuarios u ON v.usuario_id = u.id ORDER BY u.nome";
                            $vendedores = fetchAll($sql);
                            foreach ($vendedores as $vendedor) {
                                echo '<option value="' . $vendedor['id'] . '">' . htmlspecialchars($vendedor['nome']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos</option>
                            <option value="active">Ativos</option>
                            <option value="paused">Pausados</option>
                            <option value="closed">Finalizados</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="vinculados" class="form-label">Vinculação</label>
                        <select class="form-select" id="vinculados" name="vinculados">
                            <option value="">Todos</option>
                            <option value="sim">Vinculados</option>
                            <option value="nao">Não vinculados</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
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
                            <th>Imagem</th>
                            <th>Título</th>
                            <th>Vendedor</th>
                            <th>Preço</th>
                            <th>Status</th>
                            <th>Produto Vinculado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT a.*, u.nome as vendedor_nome, p.nome as produto_nome 
                                FROM anuncios_ml a
                                LEFT JOIN vendedores v ON a.usuario_id = v.usuario_id
                                LEFT JOIN usuarios u ON v.usuario_id = u.id
                                LEFT JOIN produtos p ON a.produto_id = p.id
                                ORDER BY a.titulo ASC
                                LIMIT 50";
                                
                        $anuncios = fetchAll($sql);
                        
                        if (count($anuncios) == 0) {
                            echo '<tr><td colspan="7" class="text-center py-4">
                                    <div class="mb-3">
                                        <i class="fas fa-store fa-3x text-muted"></i>
                                    </div>
                                    <p>Nenhum anúncio encontrado.</p>
                                    <p>Os vendedores precisam sincronizar seus anúncios do Mercado Livre.</p>
                                </td></tr>';
                        } else {
                            foreach ($anuncios as $anuncio) {
                                $status_class = '';
                                $status_label = '';
                                
                                switch ($anuncio['status']) {
                                    case 'active':
                                        $status_class = 'bg-success';
                                        $status_label = 'Ativo';
                                        break;
                                    case 'paused':
                                        $status_class = 'bg-warning text-dark';
                                        $status_label = 'Pausado';
                                        break;
                                    case 'closed':
                                        $status_class = 'bg-secondary';
                                        $status_label = 'Finalizado';
                                        break;
                                    default:
                                        $status_class = 'bg-info';
                                        $status_label = $anuncio['status'];
                                }
                                
                                echo '<tr>';
                                echo '<td>';
                                if (!empty($anuncio['thumbnail'])) {
                                    echo '<img src="' . htmlspecialchars($anuncio['thumbnail']) . '" alt="Miniatura" class="img-thumbnail" style="max-width: 60px;">';
                                } else {
                                    echo '<div class="bg-light text-center p-2"><i class="fas fa-image text-muted"></i></div>';
                                }
                                echo '</td>';
                                echo '<td>';
                                
                                // Título com link para o anúncio
                                echo htmlspecialchars($anuncio['titulo']);
                                if (!empty($anuncio['permalink'])) {
                                    echo ' <a href="' . htmlspecialchars($anuncio['permalink']) . '" target="_blank"><i class="fas fa-external-link-alt fa-xs"></i></a>';
                                }
                                
                                echo '</td>';
                                echo '<td>' . htmlspecialchars($anuncio['vendedor_nome'] ?? 'N/A') . '</td>';
                                echo '<td>' . formatCurrency($anuncio['preco']) . '</td>';
                                echo '<td><span class="badge ' . $status_class . '">' . $status_label . '</span></td>';
                                
                                // Produto vinculado
                                echo '<td>';
                                if ($anuncio['produto_nome']) {
                                    echo htmlspecialchars($anuncio['produto_nome']);
                                } else {
                                    echo '<span class="text-muted">Não vinculado</span>';
                                }
                                echo '</td>';
                                
                                // Ações
                                echo '<td>';
                                if (!empty($anuncio['permalink'])) {
                                    echo '<a href="' . htmlspecialchars($anuncio['permalink']) . '" target="_blank" class="btn btn-sm btn-info" title="Ver no Mercado Livre">';
                                    echo '<i class="fas fa-external-link-alt"></i>';
                                    echo '</a> ';
                                }
                                echo '</td>';
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

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer_admin.php');
?>
