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

// Incluir biblioteca de funções do Mercado Livre
require_once 'api_mercadolivre.php';

// Verificar se o vendedor tem um token válido do Mercado Livre
$ml_token = getMercadoLivreToken($pdo, $usuario_id);
$token_valido = !empty($ml_token);

if (!$token_valido) {
    $_SESSION['mensagem'] = "Você precisa autorizar o acesso ao Mercado Livre para usar esta função.";
    $_SESSION['tipo_mensagem'] = "warning";
    header("Location: {$base_url}/vendedor_mercadolivre.php");
    exit;
}

// Obter informações do usuário do Mercado Livre
$ml_usuario = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM mercadolivre_usuarios 
        WHERE usuario_id = ? 
        ORDER BY atualizado_em DESC 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $ml_usuario = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro
}

if (empty($ml_usuario['ml_user_id'])) {
    $_SESSION['mensagem'] = "Não foi possível encontrar seu ID de usuário do Mercado Livre.";
    $_SESSION['tipo_mensagem'] = "danger";
    header("Location: {$base_url}/vendedor_mercadolivre.php");
    exit;
}

// Inicializar variáveis
$ml_seller_id = $ml_usuario['ml_user_id'];
$access_token = $ml_token['access_token'];
$vendas_por_produto = [];
$curva_abc = [];
$total_geral = 0;
$relatorio_gerado = false;
$erro_api = false;
$mensagem_erro = '';

// Definir período padrão (último mês)
$hoje = new DateTime();
$um_mes_atras = (new DateTime())->modify('-1 month');
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : $um_mes_atras->format('Y-m-d');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : $hoje->format('Y-m-d');
$tipo_analise = isset($_GET['tipo_analise']) ? $_GET['tipo_analise'] : 'faturamento';
$categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';

// Verificar se está solicitando a geração do relatório
if (isset($_GET['gerar']) && $_GET['gerar'] == '1') {
    // Validar datas
    $data_inicio_obj = new DateTime($data_inicio);
    $data_fim_obj = new DateTime($data_fim);
    
    if ($data_inicio_obj > $data_fim_obj) {
        $mensagem = "A data inicial não pode ser posterior à data final.";
        $tipo_mensagem = "danger";
    } else {
        // Calcular intervalo de dias
        $intervalo = $data_inicio_obj->diff($data_fim_obj);
        $dias = $intervalo->days;
        
        // Limitar o período para evitar sobrecarga
        if ($dias > 180) {
            $mensagem = "O período máximo para análise é de 180 dias.";
            $tipo_mensagem = "warning";
        } else {
            try {
                // Buscar ordens do período
                $offset = 0;
                $limit = 50;
                $has_more = true;
                $total_ordens = 0;
                $produtos_vendidos = [];
                
                while ($has_more) {
                    $ordens = buscarOrdensVendedor(
                        $ml_seller_id, 
                        $access_token, 
                        'paid', // Apenas ordens pagas
                        $data_inicio,
                        $data_fim,
                        $offset,
                        $limit
                    );
                    
                    if ($ordens['total'] > 0) {
                        foreach ($ordens['ordens'] as $ordem) {
                            if (!empty($ordem['order_items'])) {
                                foreach ($ordem['order_items'] as $item) {
                                    $produto_id = $item['item']['id'];
                                    $titulo = $item['item']['title'];
                                    $categoria_id = $item['item']['category_id'] ?? '';
                                    $quantidade = $item['quantity'];
                                    $preco_unitario = $item['unit_price'];
                                    $valor_total = $preco_unitario * $quantidade;
                                    
                                    // Aplicar filtro de categoria se necessário
                                    if (!empty($categoria) && $categoria_id != $categoria) {
                                        continue;
                                    }
                                    
                                    // Adicionar ou atualizar produto na lista
                                    if (isset($produtos_vendidos[$produto_id])) {
                                        $produtos_vendidos[$produto_id]['quantidade'] += $quantidade;
                                        $produtos_vendidos[$produto_id]['valor_total'] += $valor_total;
                                    } else {
                                        $produtos_vendidos[$produto_id] = [
                                            'id' => $produto_id,
                                            'titulo' => $titulo,
                                            'categoria_id' => $categoria_id,
                                            'quantidade' => $quantidade,
                                            'preco_unitario' => $preco_unitario,
                                            'valor_total' => $valor_total
                                        ];
                                    }
                                }
                            }
                        }
                        
                        $offset += $limit;
                        $has_more = count($ordens['ordens']) == $limit && $offset < $ordens['total'];
                        $total_ordens = $ordens['total'];
                    } else {
                        $has_more = false;
                    }
                }
                
                // Se não houver vendas no período
                if (empty($produtos_vendidos)) {
                    $mensagem = "Não foram encontradas vendas no período selecionado.";
                    $tipo_mensagem = "info";
                } else {
                    // Ordenar produtos de acordo com o tipo de análise
                    $chave_ordenacao = ($tipo_analise == 'faturamento') ? 'valor_total' : 'quantidade';
                    
                    // Ordenar array por valor total ou quantidade (decrescente)
                    uasort($produtos_vendidos, function($a, $b) use ($chave_ordenacao) {
                        return $b[$chave_ordenacao] <=> $a[$chave_ordenacao];
                    });
                    
                    // Calcular o total geral
                    $total_geral = 0;
                    foreach ($produtos_vendidos as $produto) {
                        $total_geral += $produto[$chave_ordenacao];
                    }
                    
                    // Calcular percentuais e acumulados para a curva ABC
                    $percentual_acumulado = 0;
                    $produto_classificado = 0;
                    
                    foreach ($produtos_vendidos as &$produto) {
                        $produto_classificado++;
                        $percentual = ($produto[$chave_ordenacao] / $total_geral) * 100;
                        $percentual_acumulado += $percentual;
                        
                        // Classificar o produto (A, B ou C)
                        if ($percentual_acumulado <= 80) {
                            $classificacao = 'A';
                        } elseif ($percentual_acumulado <= 95) {
                            $classificacao = 'B';
                        } else {
                            $classificacao = 'C';
                        }
                        
                        // Adicionar informações de percentual e classificação
                        $produto['percentual'] = $percentual;
                        $produto['percentual_acumulado'] = $percentual_acumulado;
                        $produto['classificacao'] = $classificacao;
                        $produto['posicao'] = $produto_classificado;
                    }
                    
                    $vendas_por_produto = $produtos_vendidos;
                    $relatorio_gerado = true;
                    
                    // Agrupar produtos por classificação
                    $produtos_a = array_filter($vendas_por_produto, function($p) { return $p['classificacao'] == 'A'; });
                    $produtos_b = array_filter($vendas_por_produto, function($p) { return $p['classificacao'] == 'B'; });
                    $produtos_c = array_filter($vendas_por_produto, function($p) { return $p['classificacao'] == 'C'; });
                    
                    // Calcular estatísticas para o gráfico
                    $curva_abc = [
                        'a' => [
                            'quantidade' => count($produtos_a),
                            'percentual_itens' => (count($produtos_a) / count($vendas_por_produto)) * 100,
                            'percentual_valor' => array_sum(array_column($produtos_a, 'percentual'))
                        ],
                        'b' => [
                            'quantidade' => count($produtos_b),
                            'percentual_itens' => (count($produtos_b) / count($vendas_por_produto)) * 100,
                            'percentual_valor' => array_sum(array_column($produtos_b, 'percentual'))
                        ],
                        'c' => [
                            'quantidade' => count($produtos_c),
                            'percentual_itens' => (count($produtos_c) / count($vendas_por_produto)) * 100,
                            'percentual_valor' => array_sum(array_column($produtos_c, 'percentual'))
                        ]
                    ];
                }
            } catch (Exception $e) {
                $erro_api = true;
                $mensagem_erro = "Erro ao consultar API do Mercado Livre: " . $e->getMessage();
                $mensagem = $mensagem_erro;
                $tipo_mensagem = "danger";
            }
        }
    }
}

// Buscar categorias para o filtro
$categorias = [];
try {
    $stmt = $pdo->query("
        SELECT DISTINCT a.categoria_id, 
               (SELECT nome FROM categorias_ml WHERE id = a.categoria_id) as nome
        FROM anuncios_ml a
        WHERE a.usuario_id = $usuario_id AND a.categoria_id IS NOT NULL
        ORDER BY nome
    ");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro, usar array vazio
}

// Exibir mensagem de sessão, se existir
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'] ?? 'info';
    
    // Limpar a mensagem da sessão
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}

// Funções de formatação
function formatarPreco($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatarPorcentagem($valor) {
    return number_format($valor, 2, ',', '.') . '%';
}

function formatarNumero($valor) {
    return number_format($valor, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise Curva ABC - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .classificacao {
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-weight: bold;
            color: white;
            text-align: center;
        }
        .classificacao-a {
            background-color: #28a745;
        }
        .classificacao-b {
            background-color: #ffc107;
            color: #343a40;
        }
        .classificacao-c {
            background-color: #dc3545;
        }
        .progress-bar-a {
            background-color: #28a745;
        }
        .progress-bar-b {
            background-color: #ffc107;
        }
        .progress-bar-c {
            background-color: #dc3545;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(255, 154, 0, 0.05);
        }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1) !important;
        }
        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
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
                    <a class="nav-link active" href="<?php echo $base_url; ?>/vendedor_relatorios.php">
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
                <div>
                    <h1 class="h2">Análise Curva ABC</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/vendedor_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>/vendedor_relatorios.php">Relatórios</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Curva ABC</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <?php if ($relatorio_gerado): ?>
                    <button type="button" class="btn btn-outline-primary me-2" onclick="exportarCSV()">
                        <i class="fas fa-file-csv"></i> Exportar CSV
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensagem); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Filtros e Formulário -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Parâmetros da Análise</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="" class="row g-3">
                        <div class="col-md-3">
                            <label for="data_inicio" class="form-label">Data Inicial</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>" required>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="data_fim" class="form-label">Data Final</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>" required>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="tipo_analise" class="form-label">Tipo de Análise</label>
                            <select class="form-select" id="tipo_analise" name="tipo_analise">
                                <option value="faturamento" <?php echo $tipo_analise === 'faturamento' ? 'selected' : ''; ?>>Por Faturamento</option>
                                <option value="quantidade" <?php echo $tipo_analise === 'quantidade' ? 'selected' : ''; ?>>Por Quantidade Vendida</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="categoria" class="form-label">Categoria (opcional)</label>
                            <select class="form-select" id="categoria" name="categoria">
                                <option value="">Todas as Categorias</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['categoria_id']; ?>" <?php echo $categoria === $cat['categoria_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nome'] ?? $cat['categoria_id']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <input type="hidden" name="gerar" value="1">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-chart-pie"></i> Gerar Análise
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($relatorio_gerado): ?>
            
            <!-- Resumo da Análise -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm stats-card h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Produtos Classe A</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="mb-0"><?php echo $curva_abc['a']['quantidade']; ?> produtos</h3>
                                <span class="classificacao classificacao-a">A</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block">Representam</small>
                                    <span class="fw-bold"><?php echo formatarPorcentagem($curva_abc['a']['percentual_itens']); ?></span> <small>dos itens</small>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">Geram</small>
                                    <span class="fw-bold"><?php echo formatarPorcentagem($curva_abc['a']['percentual_valor']); ?></span> <small>das vendas</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm stats-card h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Produtos Classe B</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="mb-0"><?php echo $curva_abc['b']['quantidade']; ?> produtos</h3>
                                <span class="classificacao classificacao-b">B</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block">Representam</small>
                                    <span class="fw-bold"><?php echo formatarPorcentagem($curva_abc['b']['percentual_itens']); ?></span> <small>dos itens</small>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">Geram</small>
                                    <span class="fw-bold"><?php echo formatarPorcentagem($curva_abc['b']['percentual_valor']); ?></span> <small>das vendas</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm stats-card h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Produtos Classe C</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="mb-0"><?php echo $curva_abc['c']['quantidade']; ?> produtos</h3>
                                <span class="classificacao classificacao-c">C</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block">Representam</small>
                                    <span class="fw-bold"><?php echo formatarPorcentagem($curva_abc['c']['percentual_itens']); ?></span> <small>dos itens</small>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">Geram</small>
                                    <span class="fw-bold"><?php echo formatarPorcentagem($curva_abc['c']['percentual_valor']); ?></span> <small>das vendas</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráficos -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Distribuição por Classificação</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="classificationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Curva ABC</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="paretoChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Informações Gerais -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Informações da Análise</h5>
                    <span class="badge bg-primary">
                        <?php echo count($vendas_por_produto); ?> produtos analisados
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6>Período Analisado</h6>
                                <p class="mb-0">
                                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                                    De <?php echo date
									                                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                                    De <?php echo date('d/m/Y', strtotime($data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($data_fim)); ?>
                                </p>
                            </div>
                            <div class="mb-3">
                                <h6>Tipo de Análise</h6>
                                <p class="mb-0">
                                    <i class="fas fa-chart-line text-primary me-2"></i>
                                    <?php echo $tipo_analise == 'faturamento' ? 'Por Faturamento (Valor)' : 'Por Quantidade Vendida'; ?>
                                </p>
                            </div>
                            <div>
                                <h6>Filtro de Categoria</h6>
                                <p class="mb-0">
                                    <i class="fas fa-tag text-primary me-2"></i>
                                    <?php 
                                    if (!empty($categoria)) {
                                        foreach ($categorias as $cat) {
                                            if ($cat['categoria_id'] == $categoria) {
                                                echo htmlspecialchars($cat['nome'] ?? $cat['categoria_id']);
                                                break;
                                            }
                                        }
                                    } else {
                                        echo 'Todas as categorias';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6>Total <?php echo $tipo_analise == 'faturamento' ? 'Faturado' : 'de Itens Vendidos'; ?></h6>
                                <p class="mb-0">
                                    <i class="fas fa-dollar-sign text-success me-2"></i>
                                    <?php 
                                    if ($tipo_analise == 'faturamento') {
                                        echo formatarPreco($total_geral);
                                    } else {
                                        echo formatarNumero($total_geral) . ' unidades';
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="mb-3">
                                <h6>Princípio de Pareto (80/20)</h6>
                                <p class="mb-0">
                                    <i class="fas fa-balance-scale text-primary me-2"></i>
                                    <?php echo formatarPorcentagem($curva_abc['a']['percentual_itens']); ?> dos produtos geram <?php echo formatarPorcentagem($curva_abc['a']['percentual_valor']); ?> do <?php echo $tipo_analise == 'faturamento' ? 'faturamento' : 'volume de vendas'; ?>
                                </p>
                            </div>
                            <div>
                                <h6>Data de Geração</h6>
                                <p class="mb-0">
                                    <i class="fas fa-clock text-primary me-2"></i>
                                    <?php echo date('d/m/Y H:i:s'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabela de Produtos -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Produtos Analisados</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="produtosTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Posição</th>
                                    <th>Produto</th>
                                    <th class="text-end">Preço</th>
                                    <th class="text-end">Qtd. Vendida</th>
                                    <th class="text-end">Valor Total</th>
                                    <th class="text-center">% Total</th>
                                    <th class="text-center">% Acumulado</th>
                                    <th class="text-center">Classe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendas_por_produto as $produto): ?>
                                    <tr>
                                        <td><?php echo $produto['posicao']; ?></td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($produto['titulo']); ?></div>
                                            <small class="text-muted"><?php echo $produto['id']; ?></small>
                                        </td>
                                        <td class="text-end"><?php echo formatarPreco($produto['preco_unitario']); ?></td>
                                        <td class="text-end"><?php echo formatarNumero($produto['quantidade']); ?></td>
                                        <td class="text-end"><?php echo formatarPreco($produto['valor_total']); ?></td>
                                        <td class="text-center"><?php echo formatarPorcentagem($produto['percentual']); ?></td>
                                        <td class="text-center"><?php echo formatarPorcentagem($produto['percentual_acumulado']); ?></td>
                                        <td class="text-center">
                                            <span class="classificacao classificacao-<?php echo strtolower($produto['classificacao']); ?>">
                                                <?php echo $produto['classificacao']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Explicação da Curva ABC -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">O que é a Curva ABC?</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-7">
                            <p>A Curva ABC é uma metodologia de classificação de inventário baseada no princípio de Pareto (regra 80/20), que permite identificar quais itens têm maior impacto no negócio.</p>
                            
                            <div class="mb-3">
                                <h6><span class="badge classificacao-a">A</span> Itens Classe A</h6>
                                <p>São os produtos mais importantes, representando aproximadamente 20% dos itens, mas responsáveis por cerca de 80% do faturamento/volume. Merecem maior atenção, controle rigoroso e gestão prioritária.</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6><span class="badge classificacao-b">B</span> Itens Classe B</h6>
                                <p>São produtos de importância intermediária, representando cerca de 30% dos itens e 15% do faturamento/volume. Requerem gestão moderada e atenção regular.</p>
                            </div>
                            
                            <div>
                                <h6><span class="badge classificacao-c">C</span> Itens Classe C</h6>
                                <p>São produtos de menor impacto financeiro, representando aproximadamente 50% dos itens, mas apenas 5% do faturamento/volume. Geralmente necessitam de controle simplificado.</p>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Como usar esta análise:</h6>
                                    <ul class="mb-0">
                                        <li class="mb-2"><strong>Itens A:</strong> Foque em manter estoque adequado, precificação estratégica e promoções direcionadas.</li>
                                        <li class="mb-2"><strong>Itens B:</strong> Monitore regularmente, mas com controles menos rigorosos que os itens A.</li>
                                        <li class="mb-2"><strong>Itens C:</strong> Avalie a continuidade desses produtos ou mantenha estoque mínimo.</li>
                                        <li>Use esta análise para direcionar seus esforços de marketing, otimizar seu estoque e aumentar a lucratividade.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($relatorio_gerado): ?>
    <script>
        // Gráfico de distribuição por classificação (pizza)
        const classCtx = document.getElementById('classificationChart').getContext('2d');
        const classChart = new Chart(classCtx, {
            type: 'pie',
            data: {
                labels: ['Classe A', 'Classe B', 'Classe C'],
                datasets: [
                    {
                        label: 'Quantidade de Produtos',
                        data: [
                            <?php echo $curva_abc['a']['quantidade']; ?>,
                            <?php echo $curva_abc['b']['quantidade']; ?>,
                            <?php echo $curva_abc['c']['quantidade']; ?>
                        ],
                        backgroundColor: [
                            '#28a745',
                            '#ffc107',
                            '#dc3545'
                        ],
                        borderWidth: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = ((value / total) * 100).toFixed(2) + '%';
                                return `${label}: ${value} produtos (${percentage})`;
                            }
                        }
                    }
                }
            }
        });
        
        // Gráfico de Pareto (linha + barras)
        const paretoCtx = document.getElementById('paretoChart').getContext('2d');
        
        // Preparar dados para o gráfico de Pareto
        const labels = [];
        const values = [];
        const acumulado = [];
        
        <?php
        $i = 0;
        $acum = 0;
        foreach (array_slice($vendas_por_produto, 0, 20) as $produto) {
            echo "labels.push('" . $i+1 . "');";
            echo "values.push(" . $produto['percentual'] . ");";
            $acum += $produto['percentual'];
            echo "acumulado.push(" . $acum . ");";
            $i++;
        }
        ?>
        
        const paretoChart = new Chart(paretoCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: '% Individual',
                        data: values,
                        backgroundColor: function(context) {
                            const index = context.dataIndex;
                            const value = context.dataset.data[index];
                            const acum = acumulado[index];
                            
                            if (acum <= 80) {
                                return '#28a745';
                            } else if (acum <= 95) {
                                return '#ffc107';
                            } else {
                                return '#dc3545';
                            }
                        },
                        borderWidth: 0,
                        order: 2
                    },
                    {
                        label: '% Acumulado',
                        data: acumulado,
                        type: 'line',
                        borderColor: '#0d6efd',
                        borderWidth: 2,
                        pointBackgroundColor: '#0d6efd',
                        fill: false,
                        tension: 0.1,
                        order: 1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Posição do Produto'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: '% Individual'
                        },
                        max: 100
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        },
                        title: {
                            display: true,
                            text: '% Acumulado'
                        },
                        max: 100
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw || 0;
                                return `${label}: ${value.toFixed(2)}%`;
                            }
                        }
                    }
                }
            }
        });
        
        // Função para exportar dados para CSV
        function exportarCSV() {
            let csv = 'Posição,Produto,ID,Preço,Quantidade,Valor Total,Percentual,Percentual Acumulado,Classificação\n';
            
            const table = document.getElementById('produtosTable');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const posicao = cells[0].textContent.trim();
                const produto = cells[1].querySelector('.fw-bold').textContent.trim();
                const id = cells[1].querySelector('small').textContent.trim();
                const preco = cells[2].textContent.trim();
                const quantidade = cells[3].textContent.trim();
                const valorTotal = cells[4].textContent.trim();
                const percentual = cells[5].textContent.trim();
                const acumulado = cells[6].textContent.trim();
                const classe = cells[7].textContent.trim();
                
                csv += `${posicao},"${produto}",${id},${preco},${quantidade},${valorTotal},${percentual},${acumulado},${classe}\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'curva_abc_<?php echo date('Y-m-d'); ?>.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
    <?php endif; ?>
</body>
</html>
