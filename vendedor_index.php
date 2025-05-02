<?php
// Iniciar sessão
session_start();

// Verificar acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Location: emergency_login.php");
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

// Obter ou criar o registro de vendedor
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
    error_log("Erro ao verificar/criar vendedor: " . $e->getMessage());
}

// Verificar filtro de datas
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Obter métricas
$resumo = [
    'total_vendas' => 0,
    'numero_vendas' => 0,
    'total_lucro' => 0,
    'media_margem' => 0
];

try {
    $stmt = $pdo->prepare("SELECT 
        SUM(valor_venda) as total_vendas,
        COUNT(*) as numero_vendas,
        SUM(lucro) as total_lucro,
        AVG(margem_lucro) as media_margem
        FROM vendas 
        WHERE vendedor_id = ? 
        AND data_venda BETWEEN ? AND ?");
    $stmt->execute([$vendedor_id, $start_date, $end_date]);
    $resultado = $stmt->fetch();
    
    if ($resultado) {
        $resumo = $resultado;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar métricas: " . $e->getMessage());
}

// Buscar vendas recentes
$vendas = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM vendas 
                          WHERE vendedor_id = ? 
                          AND data_venda BETWEEN ? AND ? 
                          ORDER BY data_venda DESC 
                          LIMIT 10");
    $stmt->execute([$vendedor_id, $start_date, $end_date]);
    $vendas = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar vendas: " . $e->getMessage());
}

// Funções de formatação
function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function formatPercentage($value) {
    return number_format((float)$value, 2, ',', '.') . '%';
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Verificar categorias
$categorias_ml = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM categorias_ml ORDER BY nome");
    while ($row = $stmt->fetch()) {
        $categorias_ml[$row['id']] = ['nome' => $row['nome'], 'taxa' => 11]; // Taxa padrão 11%
    }
} catch (PDOException $e) {
    // Se não conseguir carregar as categorias, usar algumas padrão
    $categorias_ml = [
        'MLB5672' => ['nome' => 'Acessórios para Veículos', 'taxa' => 11],
        'MLB1051' => ['nome' => 'Celulares e Telefones', 'taxa' => 16],
        'MLB1648' => ['nome' => 'Computadores e Informática', 'taxa' => 14],
        'MLB1574' => ['nome' => 'Eletrônicos, Áudio e Vídeo', 'taxa' => 15]
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Vendedor - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,.1);
        }
        .sidebar .nav-link.active {
            border-left: 4px solid #ff9a00;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .card-dashboard {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,.1);
            transition: transform 0.3s;
        }
        .card-dashboard:hover {
            transform: translateY(-5px);
        }
        .main-content {
            margin-left: 240px;
            padding: 1.5rem;
        }
        .btn-warning, .bg-warning {
            background-color: #ff9a00 !important;
            border-color: #ff9a00;
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
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/perfil.php"><i class="fas fa-user-cog"></i> Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/emergency_login.php?logout=1"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar col-md-3 col-lg-2 d-md-block">
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo $base_url; ?>/vendedor_index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_vendas.php">
                        <i class="fas fa-shopping-cart"></i>
                        Vendas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_produtos.php">
                        <i class="fas fa-box"></i>
                        Produtos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_calculadora.php">
                        <i class="fas fa-calculator"></i>
                        Calculadora ML
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_relatorios.php">
                        <i class="fas fa-chart-bar"></i>
                        Relatórios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_anuncios.php">
                        <i class="fas fa-ad"></i>
                        Anúncios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/direct_index.php">
                        <i class="fas fa-life-ring"></i>
                        Acesso Emergencial
                    </a>
                </li>
            </ul>
            
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                <span>Ferramentas</span>
            </h6>
            <ul class="nav flex-column mb-2">
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
            <h1 class="h2">Dashboard do Vendedor</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="<?php echo $base_url; ?>/vendedor_nova_venda.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-plus"></i> Nova Venda
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Filtro de Data -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filtrar por Data</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Data Inicial</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">Data Final</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                            <a href="<?php echo $base_url; ?>/vendedor_index.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-sync-alt"></i> Limpar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Cards de Resumo -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card card-dashboard h-100 bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-shopping-bag"></i> Total em Vendas</h5>
                        <h3 class="mb-0"><?php echo formatCurrency($resumo['total_vendas'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card card-dashboard h-100 bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-dollar-sign"></i> Lucro Total</h5>
                        <h3 class="mb-0"><?php echo formatCurrency($resumo['total_lucro'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card card-dashboard h-100 bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chart-line"></i> Número de Vendas</h5>
                        <h3 class="mb-0"><?php echo $resumo['numero_vendas'] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card card-dashboard h-100 bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-percentage"></i> Margem Média</h5>
                        <h3 class="mb-0"><?php echo formatPercentage($resumo['media_margem'] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lista de Vendas Recentes -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list"></i> Vendas Recentes</h5>
                <a href="<?php echo $base_url; ?>/vendedor_nova_venda.php" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i> Nova Venda
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($vendas)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Nenhuma venda encontrada no período selecionado.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Data</th>
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
                                <?php foreach ($vendas as $venda): ?>
                                    <tr>
                                        <td><?php echo formatDate($venda['data_venda']); ?></td>
                                        <td><?php echo htmlspecialchars($venda['produto']); ?></td>
                                        <td><?php echo formatCurrency($venda['valor_venda']); ?></td>
                                        <td><?php echo formatCurrency($venda['custo_produto']); ?></td>
                                        <td><?php echo formatCurrency($venda['taxa_ml']); ?></td>
                                        <td><?php echo formatCurrency($venda['lucro']); ?></td>
                                        <td>
                                            <?php
                                            $margem_class = '';
                                            if ($venda['margem_lucro'] >= 20) {
                                                $margem_class = 'bg-success';
                                            } elseif ($venda['margem_lucro'] >= 10) {
                                                $margem_class = 'bg-primary';
                                            } elseif ($venda['margem_lucro'] > 0) {
                                                $margem_class = 'bg-warning';
                                            } else {
                                                $margem_class = 'bg-danger';
                                            }
                                            ?>
                                            <span class="badge <?php echo $margem_class; ?>">
                                                <?php echo formatPercentage($venda['margem_lucro']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="<?php echo $base_url; ?>/vendedor_editar_venda.php?id=<?php echo $venda['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" title="Excluir" onclick="confirmarExclusao(<?php echo $venda['id']; ?>)">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($vendas) >= 10): ?>
                        <div class="text-center mt-3">
                            <a href="<?php echo $base_url; ?>/vendedor_vendas.php" class="btn btn-outline-primary">Ver Todas as Vendas</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Calculadora Rápida -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-calculator"></i> Calculadora Rápida</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <input type="number" class="form-control" id="valor_produto" step="0.01" min="0" value="0.00">
                            <label for="valor_produto">Valor do Produto (R$)</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <input type="number" class="form-control" id="custo_produto_calc" step="0.01" min="0" value="0.00">
                            <label for="custo_produto_calc">Custo do Produto (R$)</label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <select class="form-select" id="taxa_categoria">
                                <option value="">Selecione a categoria...</option>
                                <?php foreach ($categorias_ml as $codigo => $categoria): ?>
                                    <option value="<?php echo $categoria['taxa']; ?>">
                                        <?php echo htmlspecialchars($categoria['nome']); ?> (<?php echo $categoria['taxa']; ?>%)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="taxa_categoria">Categoria no Mercado Livre</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-floating">
                            <input type="number" class="form-control" id="taxa_personalizada" step="0.01" min="0" max="100" value="0.00">
                            <label for="taxa_personalizada">Taxa Personalizada (%)</label>
                        </div>
                    </div>
                </div>
                <button type="button" id="btnCalcular" class="btn btn-primary">Calcular</button>
                
                <div class="mt-4" id="resultadoCalculo" style="display:none;">
                    <h5>Resultado:</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Taxa do ML</h6>
                                    <p class="card-text" id="resultadoTaxa">R$ 0,00</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Lucro</h6>
                                    <p class="card-text" id="resultadoLucro">R$ 0,00</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Margem</h6>
                                    <p class="card-text" id="resultadoMargem">0,00%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir esta venda?</p>
                    <p class="text-danger"><small>Esta ação não pode ser desfeita.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form id="deleteForm" method="POST" action="vendedor_excluir_venda.php">
                        <input type="hidden" id="delete_id" name="id" value="">
                        <button type="submit" class="btn btn-danger">Excluir</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Calculadora Rápida
            document.getElementById('btnCalcular').addEventListener('click', function() {
                const valorProduto = parseFloat(document.getElementById('valor_produto').value) || 0;
                const custoProduto = parseFloat(document.getElementById('custo_produto_calc').value) || 0;
                const taxaCategoria = parseFloat(document.getElementById('taxa_categoria').value) || 0;
                const taxaPersonalizada = parseFloat(document.getElementById('taxa_personalizada').value) || 0;
                
                // Usar taxa da categoria se selecionada, senão usar a personalizada
                const taxaFinal = taxaCategoria > 0 ? taxaCategoria : taxaPersonalizada;
                
                // Calcular valores
                const valorTaxa = valorProduto * (taxaFinal / 100);
                const lucro = valorProduto - custoProduto - valorTaxa;
                const margem = valorProduto > 0 ? (lucro / valorProduto) * 100 : 0;
                
                // Atualizar resultado
                document.getElementById('resultadoTaxa').textContent = 'R$ ' + valorTaxa.toFixed(2).replace('.', ',');
                document.getElementById('resultadoLucro').textContent = 'R$ ' + lucro.toFixed(2).replace('.', ',');
                document.getElementById('resultadoMargem').textContent = margem.toFixed(2).replace('.', ',') + '%';
                
                // Mostrar div de resultado
                document.getElementById('resultadoCalculo').style.display = 'block';
            });
        });
        
        // Função para confirmar exclusão
        function confirmarExclusao(id) {
            document.getElementById('delete_id').value = id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
