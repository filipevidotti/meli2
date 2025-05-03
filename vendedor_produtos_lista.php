<?php
// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Location: emergency_login.php");
    exit;
}

// Dados básicos
$base_url = 'http://www.annemacedo.com.br/novo2';
$usuario_id = $_SESSION['user_id'];
$usuario_nome = $_SESSION['user_name'] ?? 'Vendedor';

// Conectar ao banco de dados
$db_host = 'mysql.annemacedo.com.br';
$db_name = 'annemacedo02';
$db_user = 'annemacedo02';
$db_pass = 'Vingador13Anne';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Processar exclusão se solicitada
$mensagem = '';
$tipo_mensagem = '';

if (isset($_POST['excluir']) && isset($_POST['produto_id'])) {
    $produto_id = (int)$_POST['produto_id'];
    
    try {
        // Verificar se o produto pertence ao usuário atual
        $sql = "SELECT id FROM produtos WHERE id = ? AND usuario_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$produto_id, $usuario_id]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$produto) {
            $mensagem = "Produto não encontrado ou não pertence ao usuário atual.";
            $tipo_mensagem = "danger";
        } else {
            // Primeiro, desvincula o produto dos anúncios
            $sql = "UPDATE anuncios_ml SET produto_id = NULL WHERE produto_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$produto_id]);
            
            // Depois exclui o produto
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

// Verificar se há mensagem na sessão
if (isset($_SESSION['mensagem']) && isset($_SESSION['tipo_mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'];
    
    // Limpar as variáveis de sessão
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}

// Função para formatar moeda
function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding-top: 56px;
        }
        .sidebar {
            position: fixed;
            top: 56px;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #343a40;
            width: 240px;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 56px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            font-weight: 500;
            color: #ced4da;
            padding: .75rem 1rem;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255,255,255,.1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,.1);
            border-left: 4px solid #ff9a00;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .main-content {
            margin-left: 240px;
            padding: 1.5rem;
        }
        .btn-warning, .bg-warning {
            background-color: #ff9a00 !important;
            border-color: #ff9a00;
        }
        .badge-count {
            font-size: 85%;
            font-weight: 600;
            padding: 0.35em 0.65em;
        }
        .actions-column {
            width: 100px;
        }
    </style>
</head>
<body>
   <?php require_once 'sidebar.php'; ?>
    <!-- Conteúdo principal -->
    <main class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">Gerenciar Produtos</h1>
                <div>
                    <a href="<?php echo $base_url; ?>/vendedor_cadastrar_produto.php" class="btn btn-warning">
                        <i class="fas fa-plus"></i> Cadastrar Produto
                    </a>
                    <a href="<?php echo $base_url; ?>/vendedor_anuncios.php" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-tag"></i> Anúncios
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
                <div class="card-header">
                    <h5 class="card-title mb-0">Seus Produtos</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="tabelaProdutos">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>SKU</th>
                                    <th>Categoria</th>
                                    <th>Custo</th>
                                    <th>Peso</th>
                                    <th>Anúncios</th>
                                    <th class="actions-column">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Buscar produtos do usuário com contagem de anúncios
                                $sql = "SELECT p.*, c.nome as categoria_nome, COUNT(a.id) as anuncios_count 
                                        FROM produtos p
                                        LEFT JOIN categorias_ml c ON p.categoria_id = c.id
                                        LEFT JOIN anuncios_ml a ON p.id = a.produto_id
                                        WHERE p.usuario_id = ?
                                        GROUP BY p.id
                                        ORDER BY p.nome ASC";
                                
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute([$usuario_id]);
                                $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (empty($produtos)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="mb-3">
                                                <i class="fas fa-box-open fa-3x text-muted"></i>
                                            </div>
                                            <p class="mb-3">Você ainda não tem produtos cadastrados.</p>
                                            <a href="<?php echo $base_url; ?>/vendedor_cadastrar_produto.php" class="btn btn-warning">
                                                <i class="fas fa-plus"></i> Cadastrar Primeiro Produto
                                            </a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($produtos as $produto): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                                            <td><?php echo htmlspecialchars($produto['sku']); ?></td>
                                            <td><?php echo htmlspecialchars($produto['categoria_nome'] ?? 'N/A'); ?></td>
                                            <td><?php echo formatCurrency($produto['custo']); ?></td>
                                            <td><?php echo $produto['peso'] ? number_format($produto['peso'], 3, ',', '.') . ' kg' : 'N/D'; ?></td>
                                            <td>
                                                <?php if ($produto['anuncios_count'] > 0): ?>
                                                    <a href="<?php echo $base_url; ?>/vendedor_anuncios.php?produto_id=<?php echo $produto['id']; ?>" class="badge bg-success badge-count">
                                                        <?php echo $produto['anuncios_count']; ?> anúncio(s)
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary badge-count">Nenhum</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="<?php echo $base_url; ?>/vendedor_editar_produto.php?id=<?php echo $produto['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Excluir" onclick="confirmarExclusao(<?php echo $produto['id']; ?>, '<?php echo htmlspecialchars(addslashes($produto['nome'])); ?>')">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

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
                    <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Esta ação não poderá ser desfeita.</p>
                    <p class="text-info"><i class="fas fa-info-circle"></i> Os anúncios vinculados a este produto serão desvinculados, mas não excluídos.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="">
                        <input type="hidden" id="deleteProductId" name="produto_id">
                        <input type="hidden" name="excluir" value="1">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Confirmar Exclusão</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar DataTable se houver produtos
            if ($('#tabelaProdutos tbody tr').length > 1) { // Mais de uma linha
                $('#tabelaProdutos').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json',
                    },
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
                });
            }
        });

        function confirmarExclusao(produtoId, produtoNome) {
            document.getElementById('productName').textContent = produtoNome;
            document.getElementById('deleteProductId').value = produtoId;
            
            var modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
