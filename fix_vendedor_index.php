<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Correção do arquivo vendedor_index.php</h1>";

// Verificar se o arquivo existe
$vendedor_file = 'vendedor_index.php';
if (file_exists($vendedor_file)) {
    // Fazer backup
    if (!file_exists($vendedor_file . '.original.bak')) {
        copy($vendedor_file, $vendedor_file . '.original.bak');
        echo "<p>Backup do arquivo original criado como vendedor_index.php.original.bak</p>";
    }
    
    // Criar uma versão básica e funcional do arquivo
    $basic_vendedor_index = '<?php
// Controle de erros
ini_set("display_errors", 1);
error_reporting(E_ALL);

// Iniciar sessão
session_start();

// Dados básicos - não precisamos do init.php por enquanto
$base_url = "http://www.annemacedo.com.br/novo2";
$usuario_id = $_SESSION["user_id"] ?? 0;
$usuario_nome = $_SESSION["user_name"] ?? "Vendedor";

// Conectar ao banco de dados diretamente para evitar dependências
try {
    $pdo = new PDO(
        "mysql:host=mysql.annemacedo.com.br;dbname=annemacedo02;charset=utf8mb4",
        "annemacedo02",
        "Vingador13Anne",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Funções básicas necessárias
function formatCurrency($value) {
    return "R$ " . number_format((float)$value, 2, ",", ".");
}

function formatPercentage($value) {
    return number_format((float)$value, 2, ",", ".") . "%";
}

function formatDate($date) {
    return date("d/m/Y", strtotime($date));
}

// Obter ou criar o registro de vendedor
$vendedor_id = 0;
try {
    $stmt = $pdo->prepare("SELECT id FROM vendedores WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $vendedor = $stmt->fetch();
    
    if ($vendedor) {
        $vendedor_id = $vendedor["id"];
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
$start_date = isset($_GET["start_date"]) ? $_GET["start_date"] : date("Y-m-01");
$end_date = isset($_GET["end_date"]) ? $_GET["end_date"] : date("Y-m-d");

// Obter métricas
$resumo = [
    "total_vendas" => 0,
    "numero_vendas" => 0,
    "total_lucro" => 0,
    "media_margem" => 0
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

// Verificar categorias
$categorias_ml = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM categorias_ml ORDER BY nome");
    while ($row = $stmt->fetch()) {
        $categorias_ml[$row["id"]] = ["nome" => $row["nome"], "taxa" => 11]; // Taxa padrão 11%
    }
} catch (PDOException $e) {
    // Se não conseguir carregar as categorias, usar algumas padrão
    $categorias_ml = [
        "MLB5672" => ["nome" => "Acessórios para Veículos", "taxa" => 11],
        "MLB1051" => ["nome" => "Celulares e Telefones", "taxa" => 16],
        "MLB1648" => ["nome" => "Computadores e Informática", "taxa" => 14],
        "MLB1574" => ["nome" => "Eletrônicos, Áudio e Vídeo", "taxa" => 15]
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
            font-family: "Segoe UI", Arial, sans-serif;
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
                    <a class="nav-link" href="<?php echo $base_url; ?>/debug_fix.php">
                        <i class="fas fa-life-ring"></i>
                        Diagnóstico
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
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/fix_functions.php">
                        <i class="fas fa-wrench"></i>
                        Corrigir Funções
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
        
        <!-- Status do Sistema -->
        <div class="alert alert-info">
            <h4>Status de Emergência</h4>
            <p>Esta é uma versão simplificada do dashboard para fins de diagnóstico e recuperação do sistema.</p>
            <p><strong>Sessão Atual:</strong> <?php echo $usuario_id ? "Logado como $usuario_nome (ID: $usuario_id)" : "Sem login"; ?></p>
            <p><strong>Vendedor ID:</strong> <?php echo $vendedor_id; ?></p>
            <a href="fix_base_url.php" class="btn btn-primary">Corrigir Problemas do Sistema</a>
            <a href="direct_login.php" class="btn btn-warning ms-2">Login Direto</a>
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
                        <h3 class="mb-0"><?php echo formatCurrency($resumo["total_vendas"] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card card-dashboard h-100 bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-dollar-sign"></i> Lucro Total</h5>
                        <h3 class="mb-0"><?php echo formatCurrency($resumo["total_lucro"] ?? 0); ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card card-dashboard h-100 bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chart-line"></i> Número de Vendas</h5>
                        <h3 class="mb-0"><?php echo $resumo["numero_vendas"] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card card-dashboard h-100 bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-percentage"></i> Margem Média</h5>
                        <h3 class="mb-0"><?php echo formatPercentage($resumo["media_margem"] ?? 0); ?></h3>
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
                                        <td><?php echo formatDate($venda["data_venda"]); ?></td>
                                        <td><?php echo htmlspecialchars($venda["produto"]); ?></td>
                                        <td><?php echo formatCurrency($venda["valor_venda"]); ?></td>
                                        <td><?php echo formatCurrency($venda["custo_produto"]); ?></td>
                                        <td><?php echo formatCurrency($venda["taxa_ml"]); ?></td>
                                        <td><?php echo formatCurrency($venda["lucro"]); ?></td>
                                        <td>
                                            <?php
                                            $margem_class = "";
                                            if ($venda["margem_lucro"] >= 20) {
                                                $margem_class = "bg-success";
                                            } elseif ($venda["margem_lucro"] >= 10) {
                                                $margem_class = "bg-primary";
                                            } elseif ($venda["margem_lucro"] > 0) {
                                                $margem_class = "bg-warning";
                                            } else {
                                                $margem_class = "bg-danger";
                                            }
                                            ?>
                                            <span class="badge <?php echo $margem_class; ?>">
                                                <?php echo formatPercentage($venda["margem_lucro"]); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="<?php echo $base_url; ?>/vendedor_editar_venda.php?id=<?php echo $venda["id"]; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" title="Excluir" onclick="confirmarExclusao(<?php echo $venda["id"]; ?>)">
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
        document.addEventListener("DOMContentLoaded", function() {
            // Calculadora Rápida
            document.getElementById("btnCalcular")?.addEventListener("click", function() {
                const valorProduto = parseFloat(document.getElementById("valor_produto").value) || 0;
                const custoProduto = parseFloat(document.getElementById("custo_produto_calc").value) || 0;
                const taxaCategoria = parseFloat(document.getElementById("taxa_categoria").value) || 0;
                const taxaPersonalizada = parseFloat(document.getElementById("taxa_personalizada").value) || 0;
                
                // Usar taxa da categoria se selecionada, senão usar a personalizada
                const taxaFinal = taxaCategoria > 0 ? taxaCategoria : taxaPersonalizada;
                
                // Calcular valores
                const valorTaxa = valorProduto * (taxaFinal / 100);
                const lucro = valorProduto - custoProduto - valorTaxa;
                const margem = valorProduto > 0 ? (lucro / valorProduto) * 100 : 0;
                
                // Atualizar resultado
                document.getElementById("resultadoTaxa").textContent = "R$ " + valorTaxa.toFixed(2).replace(".", ",");
                document.getElementById("resultadoLucro").textContent = "R$ " + lucro.toFixed(2).replace(".", ",");
                document.getElementById("resultadoMargem").textContent = margem.toFixed(2).replace(".", ",") + "%";
                
                // Mostrar div de resultado
                document.getElementById("resultadoCalculo").style.display = "block";
            });
        });
        
        // Função para confirmar exclusão
        function confirmarExclusao(id) {
            document.getElementById("delete_id").value = id;
            new bootstrap.Modal(document.getElementById("deleteModal")).show();
        }
    </script>
</body>
</html>';

    // Escrever a nova versão
    file_put_contents($vendedor_file, $basic_vendedor_index);
    echo "<div style='color: green; margin: 15px 0; font-weight: bold;'>✅ Arquivo vendedor_index.php substituído por uma versão básica e funcional!</div>";
    
    echo "<p>Esta versão básica não depende do arquivo init.php e contém apenas o código necessário para funcionar.</p>";
} else {
    echo "<div style='color: red; margin: 15px 0;'>❌ Arquivo vendedor_index.php não encontrado.</div>";
}

echo "<h2>Próximos Passos:</h2>";
echo "<ol>";
echo "<li>Acesse o <a href='vendedor_index.php'>Dashboard do Vendedor</a> para verificar se a nova versão está funcionando.</li>";
echo "<li>Se funcionar, use esta versão básica enquanto você corrige os outros problemas.</li>";
echo "<li>Após corrigir o arquivo init.php e garantir que todas as funções estão definidas corretamente, você pode restaurar a versão original do vendedor_index.php.</li>";
echo "</ol>";

echo "<h2>Links Úteis:</h2>";
echo "<ul>";
echo "<li><a href='vendedor_index.php'>Dashboard do Vendedor</a> (Nova versão básica)</li>";
echo "<li><a href='direct_login.php'>Login Direto</a> (Para forçar um login se necessário)</li>";
echo "<li><a href='fix_base_url.php'>Corrigir Problema de BASE_URL</a> (Para resolver o erro da constante)</li>";
echo "</ul>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 900px;
    margin: 30px auto;
    padding: 20px;
    background-color: #f5f5f5;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
h1, h2 {
    color: #333;
}
pre {
    background-color: #f8f9fa;
    padding: 10px;
    border-left: 4px solid #007bff;
    overflow-x: auto;
}
</style>
