<?php
// Incluir arquivos necessários
require_once 'ml_config.php';
require_once 'ml_vendas_api.php';
require_once 'ml_curva_abc.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Location: login.php");
    exit;
}

// Dados básicos
$base_url = 'https://www.annemacedo.com.br/novo2';
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

// Buscar token válido
$ml_token = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM mercadolivre_tokens 
        WHERE usuario_id = ? AND revogado = 0 AND data_expiracao > NOW()
        ORDER BY data_expiracao DESC 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $ml_token = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erro ao buscar token: " . $e->getMessage();
}

// Verificar se tem token válido
if (empty($ml_token) || empty($ml_token['access_token'])) {
    $error_message = "Você não está conectado ao Mercado Livre ou seu token expirou. Por favor, faça a autenticação novamente.";
}

// Inicializar variáveis
$access_token = $ml_token['access_token'] ?? '';
$produtos_abc = [];
$estatisticas = [];
$valor_total_vendas = 0;

// Verificar qual usuário está usando o token
if (!empty($access_token)) {
    $ch = curl_init('https://api.mercadolibre.com/users/me');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($status == 200) {
        $user_data = json_decode($response, true);
        $ml_user_id = $user_data['id'] ?? 'Desconhecido';
        $ml_nickname = $user_data['nickname'] ?? 'Desconhecido';
        
        error_log("Usuário ML autenticado: {$ml_nickname} (ID: {$ml_user_id})");
    }
}

// Definir data inicial e final padrão
$data_hoje = date('Y-m-d');
$data_inicio = date('Y-m-d', strtotime('-30 days'));
$data_fim = $data_hoje;

// Processar formulário de filtro
if (isset($_POST['filtrar'])) {
    $data_inicio = $_POST['data_inicio'] ?? $data_inicio;
    $data_fim = $_POST['data_fim'] ?? $data_fim;
    
    // Validar datas
    if (strtotime($data_inicio) > strtotime($data_fim)) {
        $error_message = "Data inicial não pode ser maior que a data final.";
    } else {
        // Buscar vendas e gerar análise ABC
        try {
            // Exibir mensagem de carregamento
            $loading_message = "Buscando dados de vendas do Mercado Livre...";
            
            // Buscar vendas
            $produtos = buscarVendasML($access_token, $data_inicio, $data_fim);
            
            // Verificar se houve erro
            if (isset($produtos['error'])) {
                $error_message = $produtos['error'];
            } else {
                // Fazer análise ABC
                $resultado_abc = analiseCurvaABC($produtos);
                $produtos_abc = $resultado_abc['produtos'];
                $estatisticas = $resultado_abc['estatisticas'];
                $valor_total_vendas = $resultado_abc['valor_total_vendas'];
                
                $success_message = "Análise ABC gerada com sucesso!";
            }
        } catch (Exception $e) {
            $error_message = "Erro ao processar dados: " . $e->getMessage();
        }
    }
}

// Função auxiliar para formatar valor monetário
function formatarValor($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função auxiliar para formatar número com tratamento de erro
function formatarNumero($valor, $casas = 2) {
    if (is_array($valor)) {
        error_log("AVISO: Tentativa de formatar um array como número: " . print_r($valor, true));
        return '0.00';
    }
    
    if (!is_numeric($valor)) {
        error_log("AVISO: Valor não numérico: " . print_r($valor, true));
        return '0.00';
    }
    
    return number_format($valor, $casas);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise de Curva ABC - CalcMeli</title>
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
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        }
        .produto-img {
            max-width: 50px;
            max-height: 50px;
            object-fit: contain;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(255, 154, 0, 0.1);
        }
        .badge-a {
            background-color: #28a745;
            color: white;
        }
        .badge-b {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-c {
            background-color: #dc3545;
            color: white;
        }
        .chart-container {
            height: 300px;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .loading-content {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
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
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_produtos.php">
                        <i class="fas fa-box"></i> Produtos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_anuncios.php">
                        <i class="fas fa-tags"></i> Anúncios ML
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_vendas.php">
                        <i class="fas fa-shopping-cart"></i> Vendas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo $base_url; ?>/vendedor_analise_abc.php">
                        <i class="fas fa-chart-pie"></i> Curva ABC
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_relatorios.php">
                        <i class="fas fa-chart-bar"></i> Relatórios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_config.php">
                        <i class="fas fa-cog"></i> Configurações
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Conteúdo principal -->
    <main class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">Análise de Curva ABC</h1>
            </div>

            <?php if (!empty($ml_user_id) && !empty($ml_nickname)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-user-circle me-2"></i> Usuário autenticado no ML: <strong><?php echo htmlspecialchars($ml_nickname); ?></strong> (ID: <?php echo $ml_user_id; ?>)
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                
                <?php if (strpos($error_message, "não está conectado") !== false): ?>
                <div class="alert alert-warning">
                    <p><strong>Atenção:</strong> Para usar a análise de curva ABC, você precisa renovar sua autenticação com o Mercado Livre para obter as permissões necessárias.</p>
                    <a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php?acao=autorizar" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Renovar Autenticação
                    </a>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Filtrar Vendas</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="" id="filtroForm">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="data_inicio" class="form-label">Data Inicial</label>
                                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="data_fim" class="form-label">Data Final</label>
                                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" name="filtrar" class="btn btn-primary" id="btnFiltrar">
                                            <i class="fas fa-filter"></i> Filtrar e Gerar Curva ABC
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($produtos_abc)): ?>
                <!-- Resumo da Curva ABC -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Resumo da Curva ABC</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Período Analisado</h6>
                                <p><?php echo date('d/m/Y', strtotime($data_inicio)) . ' até ' . date('d/m/Y', strtotime($data_fim)); ?></p>
                                <h6>Valor Total de Vendas</h6>
                                <p class="h4 text-success"><?php echo formatarValor($valor_total_vendas); ?></p>
                                <h6>Quantidade de Produtos Analisados</h6>
                                <p><?php echo count($produtos_abc); ?> produtos</p>
                            </div>
                            <div class="col-md-6">
                                <div class="chart-container">
                                    <canvas id="chartABC"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Classe</th>
                                        <th>Quantidade de Itens</th>
                                        <th>% de Itens</th>
                                        <th>Valor Total</th>
                                        <th>% do Valor</th>
                                        <th>Importância</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (['A', 'B', 'C'] as $categoria): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower($categoria); ?>">
                                                    Classe <?php echo $categoria; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $estatisticas[$categoria]['quantidade_produtos']; ?></td>
                                            <td><?php echo formatarNumero($estatisticas[$categoria]['percentual_quantidade']); ?>%</td>
                                            <td><?php echo formatarValor($estatisticas[$categoria]['valor_total']); ?></td>
                                            <td><?php echo formatarNumero($estatisticas[$categoria]['percentual_valor']); ?>%</td>
                                            <td>
                                                <?php if($categoria === 'A'): ?>
                                                    <span class="text-success">Alta</span>
                                                <?php elseif($categoria === 'B'): ?>
                                                    <span class="text-warning">Média</span>
                                                <?php else: ?>
                                                    <span class="text-danger">Baixa</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Produtos -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Produtos Analisados</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="tabelaProdutos">
                                <thead>
                                    <tr>
                                        <th>Classificação</th>
                                        <th>Produto</th>
                                        <th>Categoria</th>
                                        <th>Quantidade</th>
                                        <th>Preço Unitário</th>
                                        <th>Valor Total</th>
                                        <th>% do Total</th>
                                        <th>% Acumulado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($produtos_abc as $produto): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower($produto['classificacao']); ?>">
                                                    <?php echo $produto['classificacao']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($produto['thumbnail'])): ?>
                                                        <img src="<?php echo htmlspecialchars($produto['thumbnail']); ?>" class="produto-img me-2" alt="<?php echo htmlspecialchars($produto['titulo']); ?>">
                                                    <?php endif; ?>
                                                    <div>
                                                        <a href="<?php echo htmlspecialchars($produto['permalink'] ?? '#'); ?>" target="_blank" title="<?php echo htmlspecialchars($produto['titulo']); ?>">
                                                            <?php echo htmlspecialchars(mb_strimwidth($produto['titulo'], 0, 50, '...')); ?>
                                                        </a>
                                                        <br>
                                                        <small class="text-muted">ID: <?php echo $produto['id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($produto['categoria_nome'] ?? 'N/A'); ?></td>
                                            <td><?php echo $produto['quantidade']; ?></td>
                                            <td><?php echo formatarValor($produto['preco_unitario']); ?></td>
                                            <td><?php echo formatarValor($produto['valor_total']); ?></td>
                                            <td><?php echo formatarNumero($produto['percentual']); ?>%</td>
                                            <td><?php echo formatarNumero($produto['percentual_acumulado']); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Exportar opções -->
                <div class="mt-4 text-end">
                    <button class="btn btn-success" id="btnExportarExcel">
                        <i class="fas fa-file-excel"></i> Exportar para Excel
                    </button>
                    <button class="btn btn-danger" id="btnExportarPDF">
                        <i class="fas fa-file-pdf"></i> Exportar para PDF
                    </button>
                </div>
                
            <?php elseif (isset($_POST['filtrar']) && empty($error_message)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Nenhum produto encontrado no período selecionado.
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Overlay de carregamento -->
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="loading-content">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <h5>Carregando dados do Mercado Livre</h5>
            <p>Isso pode levar alguns minutos...</p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar DataTables
            var table = $('#tabelaProdutos').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json',
                },
                order: [[5, 'desc']],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        title: 'Análise Curva ABC - <?php echo date('d/m/Y'); ?>',
                        className: 'buttons-excel d-none'
                    },
                    {
                        extend: 'pdf',
                        title: 'Análise Curva ABC - <?php echo date('d/m/Y'); ?>',
                        className: 'buttons-pdf d-none'
                    }
                ]
            });
            
            // Mostrar overlay de carregamento ao enviar formulário
            $('#filtroForm').on('submit', function() {
                $('#loadingOverlay').show();
            });
            
            // Exportar para Excel
            $('#btnExportarExcel').on('click', function() {
                table.button('.buttons-excel').trigger();
            });
            
            // Exportar para PDF
            $('#btnExportarPDF').on('click', function() {
                table.button('.buttons-pdf').trigger();
            });
            
            <?php if (!empty($estatisticas) && !empty($produtos_abc)): ?>
            // Criar gráfico de pizza para curva ABC
            var ctx = document.getElementById('chartABC').getContext('2d');
            var myChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: [
                        'Classe A (<?php echo formatarNumero(isset($estatisticas['A']['percentual_quantidade']) ? $estatisticas['A']['percentual_quantidade'] : 0, 1); ?>% dos itens)',
                        'Classe B (<?php echo formatarNumero(isset($estatisticas['B']['percentual_quantidade']) ? $estatisticas['B']['percentual_quantidade'] : 0, 1); ?>% dos itens)',
                        'Classe C (<?php echo formatarNumero(isset($estatisticas['C']['percentual_quantidade']) ? $estatisticas['C']['percentual_quantidade'] : 0, 1); ?>% dos itens)'
                    ],
                    datasets: [{
                        label: '% do Valor Total',
                        data: [
                            <?php echo formatarNumero(isset($estatisticas['A']['percentual_valor']) ? $estatisticas['A']['percentual_valor'] : 0, 2); ?>,
                            <?php echo formatarNumero(isset($estatisticas['B']['percentual_valor']) ? $estatisticas['B']['percentual_valor'] : 0, 2); ?>,
                            <?php echo formatarNumero(isset($estatisticas['C']['percentual_valor']) ? $estatisticas['C']['percentual_valor'] : 0, 2); ?>
                        ],
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.7)',
                            'rgba(255, 193, 7, 0.7)',
                            'rgba(220, 53, 69, 0.7)'
                        ],
                        borderColor: [
                            'rgba(40, 167, 69, 1)',
                            'rgba(255, 193, 7, 1)',
                            'rgba(220, 53, 69, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        title: {
                            display: true,
                            text: 'Distribuição do Valor de Vendas por Classe'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || '';
                                    var value = context.raw || 0;
							  return label + ': ' + value.toFixed(2) + '% do valor total';
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>
