<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

// Inicializar variáveis
$mensagem = '';
$tipo_mensagem = '';
$vendedor_id_filtro = isset($_GET['vendedor_id']) ? intval($_GET['vendedor_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Processar mensagens de sessão
if (isset($_SESSION['mensagem']) && isset($_SESSION['tipo_mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'];
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}

// Processar exclusão de produto
if (isset($_POST['delete_produto']) && !empty($_POST['produto_id'])) {
    try {
        $produto_id = intval($_POST['produto_id']);
        
        // Primeiro, remover associações
        $sql = "UPDATE anuncios_ml SET produto_id = NULL WHERE produto_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$produto_id]);
        
        // Depois, excluir o produto
        $sql = "DELETE FROM produtos WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$produto_id]);
        
        $mensagem = "Produto excluído com sucesso!";
        $tipo_mensagem = "success";
    } catch (PDOException $e) {
        $mensagem = "Erro ao excluir produto: " . $e->getMessage();
        $tipo_mensagem = "danger";
    }
}

// Buscar todos os vendedores para o filtro
$vendedores = [];
try {
    $sql = "SELECT id, nome FROM usuarios WHERE tipo = 'vendedor' OR tipo = 'admin' ORDER BY nome";
    $stmt = $pdo->query($sql);
    $vendedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro
}

// Construir consulta SQL com base nos filtros
try {
    $sql_count = "SELECT COUNT(*) FROM produtos p 
                  INNER JOIN usuarios u ON p.usuario_id = u.id 
                  WHERE 1=1";
    $sql = "SELECT p.*, u.nome as vendedor_nome 
            FROM produtos p 
            INNER JOIN usuarios u ON p.usuario_id = u.id 
            WHERE 1=1";
    
    $params = [];
    
    // Filtrar por vendedor se especificado
    if ($vendedor_id_filtro > 0) {
        $sql .= " AND p.usuario_id = ?";
        $sql_count .= " AND p.usuario_id = ?";
        $params[] = $vendedor_id_filtro;
    }
    
    // Filtrar por termo de busca se especificado
    if (!empty($search)) {
        $search_term = "%$search%";
        $sql .= " AND (p.nome LIKE ? OR p.sku LIKE ?)";
        $sql_count .= " AND (p.nome LIKE ? OR p.sku LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // Adicionar ordenação
    $sql .= " ORDER BY p.id DESC";
    
    // Preparar e executar consulta de contagem
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_produtos = $stmt_count->fetchColumn();
    
    // Paginação
    $por_pagina = 20;
    $total_paginas = ceil($total_produtos / $por_pagina);
    $pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($pagina_atual - 1) * $por_pagina;
    
    // Adicionar limite para paginação
    $sql .= " LIMIT $offset, $por_pagina";
    
    // Executar consulta principal
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar anúncios vinculados a cada produto
    foreach ($produtos as &$produto) {
        $sql = "SELECT id, titulo, preco FROM anuncios_ml WHERE produto_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$produto['id']]);
        $produto['anuncios'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $mensagem = "Erro ao buscar produtos: " . $e->getMessage();
    $tipo_mensagem = "danger";
    $produtos = [];
    $total_produtos = 0;
    $total_paginas = 1;
    $pagina_atual = 1;
}

// Incluir cabeçalho
$page_title = 'Gerenciar Produtos';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gerenciar Produtos</h2>
        <a href="<?php echo BASE_URL; ?>/admin/adicionar_produto.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Adicionar Novo Produto
        </a>
    </div>
    
    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensagem; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row gx-3 gy-2 align-items-center">
                <div class="col-md-4">
                    <label for="search" class="visually-hidden">Buscar</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" placeholder="Buscar por nome ou SKU" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <label for="vendedor_id" class="visually-hidden">Vendedor</label>
                    <select class="form-select" id="vendedor_id" name="vendedor_id" onchange="this.form.submit()">
                        <option value="0">Todos os vendedores</option>
                        <?php foreach ($vendedores as $vendedor): ?>
                            <option value="<?php echo $vendedor['id']; ?>" <?php echo ($vendedor_id_filtro == $vendedor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($vendedor['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4 text-md-end">
                    <?php if (!empty($search) || $vendedor_id_filtro > 0): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/produtos.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Limpar Filtros
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <?php if (count($produtos) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Vendedor</th> <!-- Nova coluna para Vendedor -->
                                <th>SKU</th>
                                <th>Custo</th>
                                <th>Anúncios</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produtos as $produto): ?>
                                <tr>
                                    <td><?php echo $produto['id']; ?></td>
                                    <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($produto['vendedor_nome']); ?></td> <!-- Coluna do Vendedor -->
                                    <td><?php echo htmlspecialchars($produto['sku'] ?? '—'); ?></td>
                                    <td><?php echo 'R$ ' . number_format($produto['custo'], 2, ',', '.'); ?></td>
                                    <td>
                                        <?php if (!empty($produto['anuncios'])): ?>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <?php echo count($produto['anuncios']); ?> anúncio(s)
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php foreach ($produto['anuncios'] as $anuncio): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/anuncio.php?id=<?php echo $anuncio['id']; ?>">
                                                                <?php echo htmlspecialchars($anuncio['titulo']); ?>
                                                                (<?php echo 'R$ ' . number_format($anuncio['preco'], 2, ',', '.'); ?>)
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Nenhum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?php echo BASE_URL; ?>/admin/editar_produto.php?id=<?php echo $produto['id']; ?>" class="btn btn-sm btn-warning" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" title="Excluir" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $produto['id']; ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Modal de Confirmação de Exclusão -->
                                        <div class="modal fade" id="deleteModal<?php echo $produto['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $produto['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $produto['id']; ?>">Confirmar Exclusão</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Tem certeza que deseja excluir o produto <strong><?php echo htmlspecialchars($produto['nome']); ?></strong>?</p>
                                                        <?php if (!empty($produto['anuncios'])): ?>
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle"></i> Este produto está vinculado a <?php echo count($produto['anuncios']); ?> anúncio(s). A exclusão irá remover esta vinculação.
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <form method="POST">
                                                            <input type="hidden" name="produto_id" value="<?php echo $produto['id']; ?>">
                                                            <button type="submit" name="delete_produto" class="btn btn-danger">Excluir</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <?php if ($total_paginas > 1): ?>
                    <nav aria-label="Navegação de páginas">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($pagina_atual == 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $pagina_atual - 1; ?>&search=<?php echo urlencode($search); ?>&vendedor_id=<?php echo $vendedor_id_filtro; ?>" tabindex="-1" aria-disabled="<?php echo ($pagina_atual == 1) ? 'true' : 'false'; ?>">Anterior</a>
                            </li>
                            
                            <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                                <li class="page-item <?php echo ($i == $pagina_atual) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&vendedor_id=<?php echo $vendedor_id_filtro; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($pagina_atual == $total_paginas) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $pagina_atual + 1; ?>&search=<?php echo urlencode($search); ?>&vendedor_id=<?php echo $vendedor_id_filtro; ?>">Próxima</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> Nenhum produto encontrado.
                    <?php if (!empty($search) || $vendedor_id_filtro > 0): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/produtos.php" class="alert-link">Limpar filtros</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer_admin.php');
?>
