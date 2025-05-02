<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin ou vendedor autorizado
requireLogin();

// Inicializar variáveis
$mensagem = '';
$tipo_mensagem = '';

// Configurar filtros
$vendedor_id = isset($_GET['vendedor_id']) ? intval($_GET['vendedor_id']) : 0;
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-30 days'));
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : '30';

// Ajustar datas com base no período selecionado
if ($periodo !== 'custom') {
    switch ($periodo) {
        case '7':
            $data_inicio = date('Y-m-d', strtotime('-7 days'));
            break;
        case '30':
            $data_inicio = date('Y-m-d', strtotime('-30 days'));
            break;
        case '90':
            $data_inicio = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'month':
            $data_inicio = date('Y-m-01');
            break;
        case 'last_month':
            $data_inicio = date('Y-m-01', strtotime('-1 month'));
            $data_fim = date('Y-m-t', strtotime('-1 month'));
            break;
        case 'year':
            $data_inicio = date('Y-01-01');
            break;
    }
}

// Formatar datas para exibição
$data_inicio_display = date('d/m/Y', strtotime($data_inicio));
$data_fim_display = date('d/m/Y', strtotime($data_fim));

// Buscar todos os vendedores para o filtro (apenas para admin)
$vendedores = [];
if (isAdmin()) {
    try {
        $sql = "SELECT id, nome FROM usuarios WHERE tipo = 'vendedor' OR tipo = 'admin' ORDER BY nome";
        $stmt = $pdo->query($sql);
        $vendedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Silenciar erro
    }
} else {
    // Se não for admin, só pode ver seus próprios dados
    $vendedor_id = $_SESSION['user_id'];
}

// Construir condição SQL para filtros
$where_conditions = [];
$params = [];

// Filtro de data
$where_conditions[] = "v.data_venda BETWEEN ? AND ?";
$params[] = $data_inicio . ' 00:00:00';
$params[] = $data_fim . ' 23:59:59';

// Filtro de vendedor
if ($vendedor_id > 0) {
    $where_conditions[] = "p.usuario_id = ?";
    $params[] = $vendedor_id;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Buscar dados para o dashboard
try {
    // Métricas gerais
    $sql = "SELECT 
                SUM(vi.preco * vi.quantidade) as faturamento,
                SUM((vi.preco * vi.quantidade) * (1 - v.taxa_marketplace/100)) as liquido_marketplace,
                SUM((vi.preco * vi.quantidade) - (p.custo * vi.quantidade)) as lucro_bruto,
                CASE 
                    WHEN SUM(vi.preco * vi.quantidade) > 0 
                    THEN (SUM((vi.preco * vi.quantidade) - (p.custo * vi.quantidade)) / SUM(vi.preco * vi.quantidade)) * 100 
                    ELSE 0 
                END as margem,
                SUM(vi.quantidade) as unidades_vendidas,
                COUNT(DISTINCT v.id) as num_vendas,
                CASE 
                    WHEN SUM(vi.quantidade) > 0 
                    THEN SUM(vi.preco * vi.quantidade) / SUM(vi.quantidade) 
                    ELSE 0 
                END as ticket_medio
            FROM vendas v 
            JOIN vendas_itens vi ON v.id = vi.venda_id 
            JOIN produtos p ON vi.produto_id = p.id
            $where_clause";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $metricas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Gastos com ADS
    $sql_ads = "SELECT SUM(valor) as total_ads FROM gastos_ads WHERE data BETWEEN ? AND ?";
    $params_ads = [$data_inicio . ' 00:00:00', $data_fim . ' 23:59:59'];
    
    if ($vendedor_id > 0) {
        $sql_ads .= " AND usuario_id = ?";
        $params_ads[] = $vendedor_id;
    }
    
    $stmt_ads = $pdo->prepare($sql_ads);
    $stmt_ads->execute($params_ads);
    $ads = $stmt_ads->fetch(PDO::FETCH_ASSOC);
    
    // Calcular métricas pós ADS
    $valor_ads = $ads['total_ads'] ?? 0;
    $lucro_apos_ads = ($metricas['lucro_bruto'] ?? 0) - $valor_ads;
    $tacos = $metricas['faturamento'] > 0 ? ($valor_ads / $metricas['faturamento']) * 100 : 0;
    $margem_pos_ads = $metricas['faturamento'] > 0 ? ($lucro_apos_ads / $metricas['faturamento']) * 100 : 0;
    $roi = $valor_ads > 0 ? ($lucro_apos_ads / $valor_ads) * 100 : 0;
    
    // Top produtos vendidos
    $sql_produtos = "SELECT 
                        p.id,
                        p.nome,
                        p.sku,
                        p.custo,
                        p.dimensoes,
                        AVG(vi.preco) as preco_medio,
                        SUM(vi.quantidade) as unidades_vendidas_total,
                        SUM(CASE WHEN v.origem = 'meli' THEN vi.quantidade ELSE 0 END) as unidades_vendidas_meli,
                        SUM(CASE WHEN v.origem = 'amazon' THEN vi.quantidade ELSE 0 END) as unidades_vendidas_amazon,
                        SUM(vi.preco * vi.quantidade) as total_faturado,
                        (SUM(vi.preco * vi.quantidade) / 
                            (SELECT SUM(vi2.preco * vi2.quantidade) 
                             FROM vendas v2 
                             JOIN vendas_itens vi2 ON v2.id = vi2.venda_id 
                             JOIN produtos p2 ON vi2.produto_id = p2.id
                             $where_clause)) * 100 as representatividade,
                        SUM((vi.preco * vi.quantidade) - (p.custo * vi.quantidade)) as lucro,
                        CASE 
                            WHEN SUM(vi.preco * vi.quantidade) > 0 
                            THEN (SUM((vi.preco * vi.quantidade) - (p.custo * vi.quantidade)) / SUM(vi.preco * vi.quantidade)) * 100 
                            ELSE 0 
                        END as margem
                    FROM vendas v 
                    JOIN vendas_itens vi ON v.id = vi.venda_id 
                    JOIN produtos p ON vi.produto_id = p.id
                    $where_clause
                    GROUP BY p.id
                    ORDER BY total_faturado DESC
                    LIMIT 15";
    
    $stmt_produtos = $pdo->prepare($sql_produtos);
    $stmt_produtos->execute($params);
    $produtos = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar gastos com ADS por produto
    foreach ($produtos as &$produto) {
        $sql_produto_ads = "SELECT SUM(ga.valor) as custo_ads 
                           FROM gastos_ads ga 
                           JOIN gastos_ads_produtos gap ON ga.id = gap.gasto_id 
                           WHERE gap.produto_id = ? AND ga.data BETWEEN ? AND ?";
        $params_produto_ads = [$produto['id'], $data_inicio . ' 00:00:00', $data_fim . ' 23:59:59'];
        
        if ($vendedor_id > 0) {
            $sql_produto_ads .= " AND ga.usuario_id = ?";
            $params_produto_ads[] = $vendedor_id;
        }
        
        $stmt_produto_ads = $pdo->prepare($sql_produto_ads);
        $stmt_produto_ads->execute($params_produto_ads);
        $produto_ads = $stmt_produto_ads->fetch(PDO::FETCH_ASSOC);
        
        $produto['custo_ads'] = $produto_ads['custo_ads'] ?? 0;
        $produto['lucro_pos_ads'] = $produto['lucro'] - $produto['custo_ads'];
        $produto['margem_pos_ads'] = $produto['total_faturado'] > 0 ? 
                                      ($produto['lucro_pos_ads'] / $produto['total_faturado']) * 100 : 0;
    }
    
} catch (PDOException $e) {
    $mensagem = "Erro ao buscar dados: " . $e->getMessage();
    $tipo_mensagem = "danger";
    
    // Inicializar variáveis para evitar erros
    $metricas = [
        'faturamento' => 0,
        'liquido_marketplace' => 0,
        'lucro_bruto' => 0,
        'margem' => 0,
        'unidades_vendidas' => 0,
        'num_vendas' => 0,
        'ticket_medio' => 0
    ];
    $valor_ads = 0;
    $lucro_apos_ads = 0;
    $tacos = 0;
    $margem_pos_ads = 0;
    $roi = 0;
    $produtos = [];
}

// Incluir cabeçalho
$page_title = 'Relatório de Lucro por Produto';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Relatório de Lucro por Produto</h2>
        <div>
            <button class="btn btn-outline-secondary" id="btnExportCSV">
                <i class="fas fa-file-csv"></i> Exportar CSV
            </button>
            <button class="btn btn-outline-secondary" id="btnExportPDF">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </button>
        </div>
    </div>
    
    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensagem; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <!-- Filtro de Período -->
                <div class="col-md-4">
                    <label for="periodo" class="form-label">Período</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-calendar"></i>
                        </span>
                        <select class="form-select" id="periodo" name="periodo">
                            <option value="7" <?php echo $periodo == '7' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                            <option value="30" <?php echo $periodo == '30' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                            <option value="90" <?php echo $periodo == '90' ? 'selected' : ''; ?>>Últimos 90 dias</option>
                            <option value="month" <?php echo $periodo == 'month' ? 'selected' : ''; ?>>Este mês</option>
                            <option value="last_month" <?php echo $periodo == 'last_month' ? 'selected' : ''; ?>>Mês anterior</option>
                            <option value="year" <?php echo $periodo == 'year' ? 'selected' : ''; ?>>Este ano</option>
                            <option value="custom" <?php echo $periodo == 'custom' ? 'selected' : ''; ?>>Personalizado</option>
                        </select>
                    </div>
                </div>
                
                <!-- Datas Personalizadas -->
                <div class="col-md-4" id="divDataPersonalizada" style="<?php echo $periodo == 'custom' ? '' : 'display: none;'; ?>">
                    <label for="data_inicio" class="form-label">Período Personalizado</label>
                    <div class="input-group">
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>">
                        <span class="input-group-text">a</span>
                        <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>">
                    </div>
                </div>
                
                <!-- Filtro de Vendedor (apenas para admin) -->
                <?php if (isAdmin()): ?>
                    <div class="col-md-3">
                        <label for="vendedor_id" class="form-label">Vendedor</label>
                        <select class="form-select" id="vendedor_id" name="vendedor_id">
                            <option value="0">Todos os vendedores</option>
                            <?php foreach ($vendedores as $vendedor): ?>
                                <option value="<?php echo $vendedor['id']; ?>" <?php echo ($vendedor_id == $vendedor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vendedor['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Dashboard de Métricas -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line"></i> 
                        Dashboard de Desempenho: <?php echo $data_inicio_display; ?> a <?php echo $data_fim_display; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Primeira linha de métricas -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle text-muted mb-1">Faturamento</h6>
                                    <h4 class="card-title mb-0">R$ <?php echo number_format($metricas['faturamento'] ?? 0, 2, ',', '.'); ?></h4>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle text-muted mb-1">
                                        Líq. do Marketplace
                                        <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Valor após as taxas do marketplace"></i>
                                    </h6>
                                    <h4 class="card-title mb-0">R$ <?php echo number_format($metricas['liquido_marketplace'] ?? 0, 2, ',', '.'); ?></h4>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle text-muted mb-1">Lucro Bruto</h6>
                                    <h4 class="card-title mb-0">R$ <?php echo number_format($metricas['lucro_bruto'] ?? 0, 2, ',', '.'); ?></h4>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle text-muted mb-1">Margem</h6>
                                    <h4 class="card-title mb-0"><?php echo number_format($metricas['margem'] ?? 0, 2, ',', '.'); ?>%</h4>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Segunda linha de métricas -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle text-muted mb-1">Número de Vendas</h6>
                                    <h4 class="card-title mb-0"><?php echo number_format($metricas['num_vendas'] ?? 0, 0, ',', '.'); ?></h4>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle text-muted mb-1">Número de Unidades Vendidas</h6>
                                    <h4 class="card-title mb-0"><?php echo number_format($metricas['unidades_vendidas'] ?? 0, 0, ',', '.'); ?></h4>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle text-muted mb-1">Ticket Médio</h6>
                                    <h4 class="card-title mb-0">R$ <?php echo number_format($metricas['ticket_medio'] ?? 0, 2, ',', '.'); ?></h4>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle text-muted mb-1">
                                        Retorno Sobre Investimento
                                        <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Lucro após ADS dividido pelo investimento em ADS"></i>
                                    </h6>
                                    <h4 class="card-title mb-0"><?php echo number_format($roi, 2, ',', '.'); ?>%</h4>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Terceira linha de métricas - ADS -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 bg-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle text-muted mb-1">Valor em ADS</h6>
                                    <h4 class="card-title mb-0 text-primary">R$ <?php echo number_format($valor_ads, 2, ',', '.'); ?></h4>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 bg-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle text-muted mb-1">
                                        TACOS
                                        <i class="fas fa-info-circle" data-bs-toggle="tooltip" title="Total Advertising Cost of Sale - Custo de publicidade em relação às vendas"></i>
                                    </h6>
                                    <h4 class="card-title mb-0"><?php echo number_format($tacos, 2, ',', '.'); ?>%</h4>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 bg-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle text-muted mb-1">Lucro bruto após ADS</h6>
                                    <h4 class="card-title mb-0">R$ <?php echo number_format($lucro_apos_ads, 2, ',', '.'); ?></h4>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 bg-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle text-muted mb-1">Margem após ADS</h6>
                                    <h4 class="card-title mb-0"><?php echo number_format($margem_pos_ads, 2, ',', '.'); ?>%</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabela Top Produtos Vendidos -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0">
                <i class="fas fa-trophy"></i> Top 15 produtos vendidos
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="tableProdutos">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Preço<br>Médio</th>
                            <th>Custo<br>Unitário<br>Médio</th>
                            <th>Unidades<br>Vendidas<br>Totais</th>
                            <th>Unidades<br>Vendidas<br>Amazon</th>
                            <th>Unidades<br>Vendidas<br>Meli</th>
                            <th>Total<br>Faturado</th>
                            <th>Represent.</th>
                            <th>Lucro</th>
                            <th>Margem</th>
                            <th>Custo ADS</th>
                            <th>Margem<br>Pós ADS</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $produto): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="produto-img me-2">
                                            <img src="<?php echo getProductImage($produto['id']); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>" class="img-thumbnail" style="width: 40px; height: 40px; object-fit: contain;">
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($produto['nome']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($produto['sku'] ?? 'Sem SKU'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>R$ <?php echo number_format($produto['preco_medio'], 2, ',', '.'); ?></td>
                                <td>R$ <?php echo number_format($produto['custo'], 2, ',', '.'); ?></td>
                                <td class="text-center"><?php echo $produto['unidades_vendidas_total']; ?></td>
                                <td class="text-center"><?php echo $produto['unidades_vendidas_amazon']; ?></td>
                                <td class="text-center"><?php echo $produto['unidades_vendidas_meli']; ?></td>
                                <td>R$ <?php echo number_format($produto['total_faturado'], 2, ',', '.'); ?></td>
                                <td><?php echo number_format($produto['representatividade'], 2, ',', '.'); ?>%</td>
                                <td>R$ <?php echo number_format($produto['lucro'], 2, ',', '.'); ?></td>
                                <td>
                                    <span class="badge <?php echo $produto['margem'] >= 20 ? 'bg-success' : ($produto['margem'] >= 10 ? 'bg-warning' : 'bg-danger'); ?>">
                                        <?php echo number_format($produto['margem'], 2, ',', '.'); ?>%
                                    </span>
                                </td>
                                <td>R$ <?php echo number_format($produto['custo_ads'], 2, ',', '.'); ?></td>
                                <td>
                                    <span class="badge <?php echo $produto['margem_pos_ads'] >= 15 ? 'bg-success' : ($produto['margem_pos_ads'] >= 5 ? 'bg-warning' : 'bg-danger'); ?>">
                                        <?php echo number_format($produto['margem_pos_ads'], 2, ',', '.'); ?>%
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary" title="Ver detalhes" onclick="verDetalhes(<?php echo $produto['id']; ?>)">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($produtos)): ?>
                            <tr>
                                <td colspan="13" class="text-center">Nenhum produto vendido no período selecionado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Gráfico de Desempenho (opcional) -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0">
                <i class="fas fa-chart-bar"></i> Desempenho por Produto (Top 5)
            </h5>
        </div>
        <div class="card-body">
            <canvas id="chartProdutos" height="300"></canvas>
        </div>
    </div>
</div>

<!-- Modal de Detalhes do Produto -->
<div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Produto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalhesContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gerenciar a exibição do período personalizado
    const periodoSelect = document.getElementById('periodo');
    const divDataPersonalizada = document.getElementById('divDataPersonalizada');
    
    periodoSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            divDataPersonalizada.style.display = 'block';
        } else {
            divDataPersonalizada.style.display = 'none';
        }
    });
    
    // Inicializar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Exportar para CSV
    document.getElementById('btnExportCSV').addEventListener('click', function() {
        exportTableToCSV('produtos_lucro.csv');
    });
    
    // Exportar para PDF
    document.getElementById('btnExportPDF').addEventListener('click', function() {
        window.print();
    });
    
    // Gráfico de produtos
    const ctx = document.getElementById('chartProdutos').getContext('2d');
    
    <?php
    // Preparar dados para o gráfico - Top 5 produtos por faturamento
    
	?>