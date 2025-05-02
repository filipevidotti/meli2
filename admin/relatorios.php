<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

// Processar filtros
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$vendedor_id = isset($_GET['vendedor_id']) ? $_GET['vendedor_id'] : '';

// Incluir cabeçalho
$page_title = 'Relatórios';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container mt-4">
    <h2>Relatórios e Análises</h2>
    
    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">Data Final</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="vendedor_id" class="form-label">Vendedor</label>
                        <select class="form-select" id="vendedor_id" name="vendedor_id">
                            <option value="">Todos os vendedores</option>
                            <?php
                            $sql = "SELECT v.id, u.nome FROM vendedores v JOIN usuarios u ON v.usuario_id = u.id ORDER BY u.nome";
                            $vendedores = fetchAll($sql);
                            foreach ($vendedores as $v) {
                                $selected = ($vendedor_id == $v['id']) ? 'selected' : '';
                                echo "<option value=\"{$v['id']}\" {$selected}>{$v['nome']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Aplicar Filtros</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Resumo -->
    <div class="row mb-4">
        <?php
        // Construir a consulta SQL com filtros
        $where = [];
        $params = [];
        
        $where[] = "v.data_venda BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
        
        if (!empty($vendedor_id)) {
            $where[] = "v.vendedor_id = ?";
            $params[] = $vendedor_id;
        }
        
        $whereClause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";
        
        // Consulta para resumo
        $sql = "SELECT 
                COUNT(*) as total_vendas,
                SUM(valor_venda) as valor_total,
                SUM(custo_produto) as custo_total,
                SUM(taxa_ml) as taxas_total,
                SUM(lucro) as lucro_total,
                AVG(margem_lucro) as margem_media
                FROM vendas v
                $whereClause";
        
        $resumo = fetchSingle($sql, $params);
        ?>
        <div class="col-md-4 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Total de Vendas</h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mb-0"><?php echo $resumo['total_vendas'] ?? 0; ?></h2>
                        <i class="fas fa-shopping-cart fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Valor Total</h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mb-0"><?php echo formatCurrency($resumo['valor_total'] ?? 0); ?></h2>
                        <i class="fas fa-dollar-sign fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Lucro Total</h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mb-0"><?php echo formatCurrency($resumo['lucro_total'] ?? 0); ?></h2>
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabela de dados -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Detalhamento por Vendedor</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Vendedor</th>
                            <th>Vendas</th>
                            <th>Valor Total</th>
                            <th>Custo Total</th>
                            <th>Taxas ML</th>
                            <th>Lucro Total</th>
                            <th>Margem Média</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Consulta por vendedor
                        $sql = "SELECT 
                                u.nome as vendedor_nome,
                                COUNT(*) as total_vendas,
                                SUM(v.valor_venda) as valor_total,
                                SUM(v.custo_produto) as custo_total,
                                SUM(v.taxa_ml) as taxas_total,
                                SUM(v.lucro) as lucro_total,
                                AVG(v.margem_lucro) as margem_media
                                FROM vendas v
                                JOIN vendedores ve ON v.vendedor_id = ve.id
                                JOIN usuarios u ON ve.usuario_id = u.id
                                $whereClause
                                GROUP BY ve.id, u.nome
                                ORDER BY u.nome";
                        
                        $resultado = fetchAll($sql, $params);
                        
                        if (count($resultado) == 0) {
                            echo '<tr><td colspan="7" class="text-center">Nenhum dado disponível para o período selecionado.</td></tr>';
                        } else {
                            foreach ($resultado as $row) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($row['vendedor_nome']) . '</td>';
                                echo '<td>' . $row['total_vendas'] . '</td>';
                                echo '<td>' . formatCurrency($row['valor_total']) . '</td>';
                                echo '<td>' . formatCurrency($row['custo_total']) . '</td>';
                                echo '<td>' . formatCurrency($row['taxas_total']) . '</td>';
                                echo '<td>' . formatCurrency($row['lucro_total']) . '</td>';
                                echo '<td>' . formatPercentage($row['margem_media']) . '</td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="d-flex justify-content-end mb-4">
        <a href="<?php echo ADMIN_URL; ?>/exportar_relatorio.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&vendedor_id=<?php echo urlencode($vendedor_id); ?>" class="btn btn-success">
            <i class="fas fa-file-excel me-2"></i> Exportar para Excel
        </a>
    </div>
</div>

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer_admin.php');
?>
