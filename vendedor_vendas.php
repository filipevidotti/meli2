<?php
// Iniciar sessão
session_start();

// Verificar acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Location: login.php");
    exit;
}

// Dados básicos
$base_url = 'http://www.annemacedo.com.br/novo2';
$usuario_id = $_SESSION['user_id'];
$usuario_nome = $_SESSION['user_name'] ?? 'Vendedor';
$usuario_email = $_SESSION['user_email'] ?? '';

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
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Obter ID do vendedor
$vendedor_id = 0;
try {
    $stmt = $pdo->prepare("SELECT id FROM vendedores WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $vendedor = $stmt->fetch();
    
    if ($vendedor) {
        $vendedor_id = $vendedor['id'];
    } else {
        // Criar vendedor automaticamente
        $stmt = $pdo->prepare("INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)");
        $stmt->execute([$usuario_id, $usuario_nome]);
        $vendedor_id = $pdo->lastInsertId();
    }
} catch (PDOException $e) {
    error_log("Erro ao verificar vendedor: " . $e->getMessage());
}

// Verificar filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$data_inicio = isset($_GET['data_inicio']) ? trim($_GET['data_inicio']) : '';
$data_fim = isset($_GET['data_fim']) ? trim($_GET['data_fim']) : '';

// Filtro por período padrão (mês atual)
if (empty($data_inicio) && empty($data_fim)) {
    $data_inicio = date('Y-m-01'); // Primeiro dia do mês
    $data_fim = date('Y-m-t'); // Último dia do mês
}

// Buscar vendas
$vendas = [];
try {
    $sql = "SELECT v.*, p.nome as produto_nome, p.sku as produto_sku 
            FROM vendas v 
            LEFT JOIN produtos p ON v.produto_id = p.id 
            WHERE v.usuario_id = ?";
    $params = [$usuario_id];
    
    // Adicionar filtros
    if (!empty($search)) {
        $sql .= " AND (v.referencia LIKE ? OR p.nome LIKE ? OR v.cliente_nome LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($status)) {
        $sql .= " AND v.status = ?";
        $params[] = $status;
    }
    
    if (!empty($data_inicio)) {
        $sql .= " AND DATE(v.data_venda) >= ?";
        $params[] = $data_inicio;
    }
    
    if (!empty($data_fim)) {
        $sql .= " AND DATE(v.data_venda) <= ?";
        $params[] = $data_fim;
    }
    
    $sql .= " ORDER BY v.data_venda DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vendas = $stmt->fetchAll();
    
    // Calcular totais
    $total_vendas = count($vendas);
    $valor_total = 0;
    $lucro_total = 0;
    
    foreach ($vendas as $venda) {
        $valor_total += $venda['valor_total'];
        $lucro_total += $venda['lucro'];
    }
    
} catch (PDOException $e) {
    error_log("Erro ao buscar vendas: " . $e->getMessage());
}

// Funções de formatação
function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function formatDate($date, $format = 'd/m/Y') {
    if (empty($date) || $date == '0000-00-00') return '—';
    $dt = new DateTime($date);
    return $dt->format($format);
}

// Status de venda
$status_list = [
    'concluida' => 'Concluída',
    'pendente' => 'Pendente', 
    'cancelada' => 'Cancelada', 
    'enviada' => 'Enviada', 
    'entregue' => 'Entregue'
];

// Cores dos status
$status_colors = [
    'concluida' => 'success',
    'pendente' => 'warning',
    'cancelada' => 'danger',
    'enviada' => 'info',
    'entregue' => 'primary'
];

// Processar exclusão
$mensagem = '';
$tipo_mensagem = '';

if (isset($_POST['delete_venda']) && !empty($_POST['venda_id'])) {
    try {
        $venda_id = intval($_POST['venda_id']);
        
        // Verificar se a venda pertence ao vendedor
        $stmt = $pdo->prepare("SELECT id FROM vendas WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$venda_id, $usuario_id]);
        
        if ($stmt->fetch()) {
            // Excluir a venda
            $stmt = $pdo->prepare("DELETE FROM vendas WHERE id = ?");
            $stmt->execute([$venda_id]);
            
            $mensagem = "Venda excluída com sucesso!";
            $tipo_mensagem = "success";
        } else {
            $mensagem = "Você não tem permissão para excluir esta venda.";
            $tipo_mensagem = "danger";
        }
    } catch (PDOException $e) {
        $mensagem = "Erro ao excluir venda: " . $e->getMessage();
        $tipo_mensagem = "danger";
    }
}

// Mostrar mensagens de sessão
if (isset($_SESSION['mensagem']) && isset($_SESSION['tipo_mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'];
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Vendas - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
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
        .summary-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .summary-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">CalcMeli</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($usuario_nome); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/vendedor_config.php"><i class="fas fa-cog"></i> Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/login.php?logout=1"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_produtos.php">
                        <i class="fas fa-box"></i>
                        Produtos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo $base_url; ?>/vendedor_vendas.php">
                        <i class="fas fa-shopping-cart"></i>
                        Vendas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_anuncios.php">
                        <i class="fas fa-ad"></i>
                        Anúncios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_relatorios.php">
                        <i class="fas fa-chart-bar"></i>
                        Relatórios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_config.php">
                        <i class="fas fa-cog"></i>
                        Configurações
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Gerenciar Vendas</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="<?php echo $base_url; ?>/vendedor_nova_venda.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Registrar Nova Venda
                </a>
            </div>
        </div>
        
        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>
        
        <!-- Resumo de Vendas -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card summary-card h-100" style="border-left-color: #28a745;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">Total de Vendas</h6>
                                <h3><?php echo $total_vendas; ?></h3>
                            </div>
                            <div class="bg-light p-3 rounded">
                                <i class="fas fa-receipt text-success fa-2x"></i>
                            </div>
                        </div>
                        <small class="text-muted">Período: <?php echo formatDate($data_inicio); ?> a <?php echo formatDate($data_fim); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card summary-card h-100" style="border-left-color: #007bff;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">Valor Total</h6>
                                <h3><?php echo formatCurrency($valor_total); ?></h3>
                            </div>
                            <div class="bg-light p-3 rounded">
                                <i class="fas fa-dollar-sign text-primary fa-2x"></i>
                            </div>
                        </div>
                        <small class="text-muted">Soma de todas as vendas no período</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card summary-card h-100" style="border-left-color: #ff9a00;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">Lucro Total</h6>
                                <h3><?php echo formatCurrency($lucro_total); ?></h3>
                            </div>
                            <div class="bg-light p-3 rounded">
                                <i class="fas fa-chart-line text-warning fa-2x"></i>
                            </div>
                        </div>
                        <small class="text-muted">Lucro líquido no período</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros de Busca -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Referência, produto ou cliente" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos</option>
                            <?php foreach ($status_list as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo $status === $key ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="data_inicio" class="form-label">Data Início</label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="data_fim" class="form-label">Data Fim</label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="d-grid gap-2 d-md-flex w-100">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                            <a href="<?php echo $base_url; ?>/vendedor_vendas.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sync-alt"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Lista de Vendas -->
        <div class="card">
            <div class="card-body">
                <?php if (count($vendas) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Data</th>
                                    <th>Referência</th>
                                    <th>Produto</th>
                                    <th>Cliente</th>
                                    <th>Valor</th>
                                    <th>Lucro</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendas as $venda): ?>
                                    <tr>
                                        <td><?php echo $venda['id']; ?></td>
                                        <td><?php echo formatDate($venda['data_venda'], 'd/m/Y H:i'); ?></td>
                                        <td><?php echo htmlspecialchars($venda['referencia'] ?? '—'); ?></td>
                                        <td>
                                            <?php if (!empty($venda['produto_nome'])): ?>
                                                <?php echo htmlspecialchars($venda['produto_nome']); ?>
                                                <?php if (!empty($venda['produto_sku'])): ?>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($venda['produto_sku']); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($venda['cliente_nome'] ?? '—'); ?></td>
                                        <td><?php echo formatCurrency($venda['valor_total']); ?></td>
                                        <td>
                                            <span class="<?php echo $venda['lucro'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo formatCurrency($venda['lucro']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php $status_key = $venda['status'] ?? 'pendente'; ?>
                                            <span class="badge bg-<?php echo $status_colors[$status_key] ?? 'secondary'; ?> status-badge">
                                                <?php echo $status_list[$status_key] ?? 'Pendente'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="<?php echo $base_url; ?>/vendedor_editar_venda.php?id=<?php echo $venda['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" title="Excluir" onclick="confirmarExclusao(<?php echo $venda['id']; ?>, '<?php echo addslashes($venda['referencia'] ?? 'Venda #' . $venda['id']); ?>')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Nenhuma venda encontrada para os filtros selecionados.
                        <?php if (!empty($search) || !empty($status) || !empty($data_inicio) || !empty($data_fim)): ?>
                            <a href="<?php echo $base_url; ?>/vendedor_vendas.php" class="alert-link">Limpar filtros</a>
                        <?php else: ?>
                            <a href="<?php echo $base_url; ?>/vendedor_nova_venda.php" class="alert-link">Registrar sua primeira venda</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir a venda <strong id="referencia_venda"></strong>?</p>
                    <p class="text-danger"><small>Esta ação não pode ser desfeita.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST">
                        <input type="hidden" id="delete_id" name="venda_id" value="">
                        <button type="submit" name="delete_venda" class="btn btn-danger">Excluir</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmarExclusao(id, referencia) {
            document.getElementById('delete_id').value = id;
            document.getElementById('referencia_venda').textContent = referencia;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
