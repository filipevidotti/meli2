<?php
// anuncios.php
require_once('init.php');
require_once('api_mercadolivre.php');

// Proteger a página
protegerPagina();

// Verificar se o usuário deseja sincronizar os anúncios
$sincronizou = false;
$mensagem = '';
$tipo_mensagem = '';

if (isset($_GET['sincronizar']) && $_GET['sincronizar'] == 1) {
    try {
        $resultado = obterAnunciosML();
        $salvos = salvarAnunciosML($resultado['anuncios']);
        
        $sincronizou = true;
        $mensagem = "Sincronização concluída! {$salvos} anúncios foram atualizados.";
        $tipo_mensagem = 'success';
    } catch (Exception $e) {
        $mensagem = "Erro ao sincronizar: " . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Processar vinculação de produto
if (isset($_POST['vincular_produto'])) {
    $anuncio_id = $_POST['anuncio_id'] ?? 0;
    $produto_id = $_POST['produto_id'] ?? 0;
    
    if ($anuncio_id > 0 && $produto_id > 0) {
        try {
            vincularAnuncioProduto($anuncio_id, $produto_id);
            $mensagem = "Anúncio vinculado ao produto com sucesso!";
            $tipo_mensagem = 'success';
        } catch (Exception $e) {
            $mensagem = "Erro ao vincular: " . $e->getMessage();
            $tipo_mensagem = 'danger';
        }
    }
}

// Processar desvinculação de produto
if (isset($_GET['desvincular']) && is_numeric($_GET['desvincular'])) {
    $anuncio_id = $_GET['desvincular'];
    
    try {
        // Verificar se o anúncio pertence ao usuário
        $sql = "SELECT id FROM anuncios_ml WHERE id = ? AND usuario_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$anuncio_id, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            // Desvincular o produto
            $sql = "UPDATE anuncios_ml SET produto_id = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$anuncio_id]);
            
            $mensagem = "Anúncio desvinculado com sucesso!";
            $tipo_mensagem = 'success';
        } else {
            $mensagem = "Anúncio não encontrado ou não pertence ao usuário.";
            $tipo_mensagem = 'danger';
        }
    } catch (Exception $e) {
        $mensagem = "Erro ao desvincular: " . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Buscar produtos para o modal de vinculação
$produtos = [];
try {
    $sql = "SELECT id, nome, sku, custo FROM produtos WHERE usuario_id = ? ORDER BY nome ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silenciar erro
}

// Filtrar anúncios por produto
$filtro_produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : 0;
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_vinculados = isset($_GET['vinculados']) ? $_GET['vinculados'] : '';

// Construir consulta com filtros
$where = ['a.usuario_id = ?'];
$params = [$_SESSION['user_id']];

if ($filtro_produto_id > 0) {
    $where[] = 'a.produto_id = ?';
    $params[] = $filtro_produto_id;
} elseif ($filtro_vinculados === 'sim') {
    $where[] = 'a.produto_id IS NOT NULL';
} elseif ($filtro_vinculados === 'nao') {
    $where[] = 'a.produto_id IS NULL';
}

if (!empty($filtro_status)) {
    $where[] = 'a.status = ?';
    $params[] = $filtro_status;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Incluir cabeçalho
$page_title = 'Gerenciar Anúncios';
include(INCLUDES_DIR . '/header.php');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Anúncios do Mercado Livre</h2>
        <div>
            <a href="<?php echo BASE_URL; ?>/anuncios.php?sincronizar=1" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> Sincronizar Anúncios
            </a>
            <a href="<?php echo BASE_URL; ?>/produtos.php" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-box"></i> Produtos
            </a>
            <a href="<?php echo BASE_URL; ?>/calculadora.php" class="btn btn-outline-primary ms-2">
                <i class="fas fa-calculator"></i> Calculadora
            </a>
        </div>
    </div>
    
    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensagem; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($sincronizou): ?>
        <div class="alert alert-info">
            <p><strong>Dica:</strong> Vincule seus anúncios aos produtos cadastrados para facilitar o cálculo de lucratividade.</p>
        </div>
    <?php endif; ?>
    
    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filtrar Anúncios</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="produto_id" class="form-label">Produto</label>
                        <select class="form-select" id="produto_id" name="produto_id">
                            <option value="">Todos os produtos</option>
                            <?php foreach ($produtos as $produto): ?>
                                <option value="<?php echo $produto['id']; ?>" <?php echo $filtro_produto_id == $produto['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($produto['nome']); ?>
                                    <?php if (!empty($produto['sku'])): ?> (<?php echo htmlspecialchars($produto['sku']); ?>)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos</option>
                            <option value="active" <?php echo $filtro_status === 'active' ? 'selected' : ''; ?>>Ativos</option>
                            <option value="paused" <?php echo $filtro_status === 'paused' ? 'selected' : ''; ?>>Pausados</option>
                            <option value="closed" <?php echo $filtro_status === 'closed' ? 'selected' : ''; ?>>Finalizados</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="vinculados" class="form-label">Vinculação</label>
                        <select class="form-select" id="vinculados" name="vinculados">
                            <option value="">Todos</option>
                            <option value="sim" <?php echo $filtro_vinculados === 'sim' ? 'selected' : ''; ?>>Vinculados</option>
                            <option value="nao" <?php echo $filtro_vinculados === 'nao' ? 'selected' : ''; ?>>Não vinculados</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end mb-3">
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
                            <th style="width: 80px;">Imagem</th>
                            <th>Título</th>
                            <th>Preço</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Produto Vinculado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Buscar anúncios do usuário com filtros
                        $sql = "SELECT a.*, p.nome as produto_nome, p.custo as produto_custo 
                                FROM anuncios_ml a 
                                LEFT JOIN produtos p ON a.produto_id = p.id 
                                {$whereClause} 
                                ORDER BY a.titulo ASC";
                        
                        $anuncios = fetchAll($sql, $params);
                        
                        if (count($anuncios) == 0) {
                            echo '<tr><td colspan="7" class="text-center py-4">
                                    <div class="mb-3">
                                        <i class="fas fa-store fa-3x text-muted"></i>
                                    </div>
                                    <p>Nenhum anúncio encontrado.</p>';
                            
                            if (empty($filtro_produto_id) && empty($filtro_status) && empty($filtro_vinculados)) {
                                echo '<p>Clique no botão "Sincronizar Anúncios" para importar seus anúncios do Mercado Livre.</p>';
                            } else {
                                echo '<p>Tente alterar os filtros para ver mais anúncios.</p>';
                                echo '<a href="' . BASE_URL . '/anuncios.php" class="btn btn-outline-secondary">Limpar Filtros</a>';
                            }
                            
                            echo '</td></tr>';
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
                                
                                $tipo_anuncio = '';
                                switch ($anuncio['tipo_anuncio']) {
                                    case 'gold_special':
                                        $tipo_anuncio = 'Premium';
                                        break;
                                    case 'gold_pro':
                                        $tipo_anuncio = 'Premium Pro';
                                        break;
                                    case 'gold':
                                        $tipo_anuncio = 'Clássico';
                                        break;
                                    default:
                                        $tipo_anuncio = $anuncio['tipo_anuncio'];
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
                                if (!empty($anuncio['permalink'])) {
                                    echo '<a href="' . htmlspecialchars($anuncio['permalink']) . '" target="_blank">' . 
                                         htmlspecialchars($anuncio['titulo']) . ' <i class="fas fa-external-link-alt fa-xs"></i></a>';
                                } else {
                                    echo htmlspecialchars($anuncio['titulo']);
                                }
                                
                                // ID do ML
                                echo '<br><small class="text-muted">ID: ' . htmlspecialchars($anuncio['ml_item_id']) . '</small>';
                                echo '</td>';
                                
                                echo '<td>' . formatCurrency($anuncio['preco']) . '</td>';
                                echo '<td>' . $tipo_anuncio . '</td>';
                                echo '<td><span class="badge ' . $status_class . '">' . $status_label . '</span></td>';
                                
                                // Produto vinculado
                                echo '<td>';
                                if ($anuncio['produto_id']) {
                                    echo '<div class="d-flex align-items-center">';
                                    echo '<span class="me-2">' . htmlspecialchars($anuncio['produto_nome']) . '</span>';
                                    
                                    // Mostrar custo e lucro estimado
                                    if ($anuncio['produto_custo'] > 0) {
                                        $taxa_ml = ($tipo_anuncio === 'Premium') ? 0.16 * $anuncio['preco'] : 0.12 * $anuncio['preco'];
                                        $lucro_estimado = $anuncio['preco'] - $anuncio['produto_custo'] - $taxa_ml;
                                        $classe_lucro = $lucro_estimado >= 0 ? 'text-success' : 'text-danger';
                                        
                                        echo '<span class="ms-1 ' . $classe_lucro . '">';
                                        echo '(' . formatCurrency($lucro_estimado) . ')';
                                        echo '</span>';
                                    }
                                    
                                    // Botão para desvincular
                                    echo '<a href="' . BASE_URL . '/anuncios.php?desvincular=' . $anuncio['id'] . '" class="btn btn-sm btn-link text-danger" title="Desvincular">';
                                    echo '<i class="fas fa-unlink"></i>';
                                    echo '</a>';
                                    echo '</div>';
                                } else {
                                    echo '<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#vincularProdutoModal" data-anuncio-id="' . $anuncio['id'] . '" data-anuncio-titulo="' . htmlspecialchars($anuncio['titulo']) . '">';
                                    echo 'Vincular Produto';
                                    echo '</button>';
                                }
                                echo '</td>';
                                
                                // Ações
                                echo '<td>';
                                
                                // Calculadora
                                echo '<a href="' . BASE_URL . '/calculadora.php?anuncio_id=' . $anuncio['id'] . '" class="btn btn-sm btn-primary" title="Calcular Lucro">';
                                echo '<i class="fas fa-calculator"></i>';
                                echo '</a> ';
                                
                                // Link para o Mercado Livre
                                if (!empty($anuncio['permalink'])) {
                                    echo '<a href="' . htmlspecialchars($anuncio['permalink']) . '" target="_blank" class="btn btn-sm btn-info" title="Ver no Mercado Livre">';
                                    echo '<i class="fas fa-external-link-alt"></i>';
                                    echo '</a>';
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
        <?php if (count($anuncios) > 0): ?>
        <div class="card-footer">
            <div class="row align-items-center">
                <div class="col-md-6">
                    Mostrando <?php echo count($anuncios); ?> anúncios
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="<?php echo BASE_URL; ?>/anuncios.php?sincronizar=1" class="btn btn-sm btn-primary">
                        <i class="fas fa-sync-alt"></i> Atualizar Anúncios
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Vincular Produto -->
<div class="modal fade" id="vincularProdutoModal" tabindex="-1" aria-labelledby="vincularProdutoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="vincularProdutoModalLabel">Vincular Anúncio a Produto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="vincularForm" method="POST" action="<?php echo BASE_URL; ?>/anuncios.php">
                    <input type="hidden" name="anuncio_id" id="modalAnuncioId">
                    
                    <div class="mb-3">
                        <p>Selecione o produto para vincular ao anúncio:</p>
                        <p><strong>Anúncio:</strong> <span id="modalAnuncioTitulo"></span></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="produto_id" class="form-label">Produto</label>
                        <select class="form-select" id="modalProdutoId" name="produto_id" required>
                            <option value="">Selecione um produto...</option>
                            <?php foreach ($produtos as $produto): ?>
                                <option value="<?php echo $produto['id']; ?>">
                                    <?php echo htmlspecialchars($produto['nome']); ?>
                                    <?php if (!empty($produto['sku'])): ?> (<?php echo htmlspecialchars($produto['sku']); ?>)<?php endif; ?>
                                    - Custo: <?php echo formatCurrency($produto['custo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (empty($produtos)): ?>
                    <div class="alert alert-warning">
                        <p>Você ainda não tem produtos cadastrados.</p>
                        <a href="<?php echo BASE_URL; ?>/adicionar_produto.php" class="btn btn-sm btn-primary">Adicionar Produto</a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo BASE_URL; ?>/adicionar_produto.php" class="btn btn-link">Criar Novo Produto</a>
                        <div>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" name="vincular_produto" class="btn btn-primary" <?php echo empty($produtos) ? 'disabled' : ''; ?>>Vincular</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configurar modal de vinculação de produto
    const vincularProdutoModal = document.getElementById('vincularProdutoModal');
    if (vincularProdutoModal) {
        vincularProdutoModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const anuncioId = button.getAttribute('data-anuncio-id');
            const anuncioTitulo = button.getAttribute('data-anuncio-titulo');
            
            document.getElementById('modalAnuncioId').value = anuncioId;
            document.getElementById('modalAnuncioTitulo').textContent = anuncioTitulo;
        });
    }
});
</script>

<?php
// Incluir rodapé
include(INCLUDES_DIR . '/footer.php');
?>
