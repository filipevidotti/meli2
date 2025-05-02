<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

// Obter estatísticas
$total_vendedores = 0;
$total_vendas = 0;
$total_lucro = 0;

try {
    // Total de vendedores
    $sql = "SELECT COUNT(*) as total FROM vendedores";
    $stmt = $pdo->query($sql);
    $total_vendedores = $stmt->fetchColumn();

    // Total de vendas
    $sql = "SELECT COUNT(*) as total FROM vendas";
    $stmt = $pdo->query($sql);
    if ($stmt) {
        $total_vendas = $stmt->fetchColumn();
    }

    // Total de lucro
    $sql = "SELECT SUM(lucro) as total FROM vendas";
    $stmt = $pdo->query($sql);
    if ($stmt) {
        $total_lucro = $stmt->fetchColumn() ?: 0; // Usar 0 se o resultado for null
    }
} catch (PDOException $e) {
    // Silenciar erro
}

// Incluir cabeçalho
$page_title = 'Dashboard';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Dashboard</h2>
        <span class="text-muted">Bem-vindo, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
    </div>
    
    <!-- Cards Resumo -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Vendedores</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_vendedores; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Vendas Registradas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_vendas; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Lucro Total</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_lucro); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Vendas Recentes -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Vendas Recentes</h6>
                    <a href="<?php echo BASE_URL; ?>/admin/vendas.php" class="btn btn-sm btn-primary">Ver Todas</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Produto</th>
                                    <th>Valor</th>
                                    <th>Lucro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $sql = "SELECT v.*, vd.nome as vendedor_nome 
                                            FROM vendas v
                                            LEFT JOIN vendedores vd ON v.vendedor_id = vd.id
                                            ORDER BY v.data_venda DESC
                                            LIMIT 5";
                                    $vendas = fetchAll($sql);
                                    
                                    if (count($vendas) > 0) {
                                        foreach ($vendas as $venda) {
                                            $lucro_class = $venda['lucro'] >= 0 ? 'text-success' : 'text-danger';
                                            echo '<tr>';
                                            echo '<td>' . formatDate($venda['data_venda']) . '</td>';
                                            echo '<td>' . htmlspecialchars($venda['produto']) . '</td>';
                                            echo '<td>' . formatCurrency($venda['valor_venda']) . '</td>';
                                            echo '<td class="' . $lucro_class . '">' . formatCurrency($venda['lucro']) . '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="4" class="text-center">Nenhuma venda encontrada</td></tr>';
                                    }
                                } catch (PDOException $e) {
                                    echo '<tr><td colspan="4" class="text-center">Erro ao carregar vendas</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vendedores Ativos -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Vendedores Ativos</h6>
                    <a href="<?php echo BASE_URL; ?>/admin/vendedores.php" class="btn btn-sm btn-primary">Ver Todos</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $sql = "SELECT v.*, u.nome, u.email, u.status
                                            FROM vendedores v
                                            JOIN usuarios u ON v.usuario_id = u.id
                                            ORDER BY u.nome ASC
                                            LIMIT 5";
                                    $vendedores = fetchAll($sql);
                                    
                                    if (count($vendedores) > 0) {
                                        foreach ($vendedores as $vendedor) {
                                            $status_badge = $vendedor['status'] === 'ativo' ? 
                                                '<span class="badge bg-success">Ativo</span>' : 
                                                '<span class="badge bg-danger">Inativo</span>';
                                            
                                            echo '<tr>';
                                            echo '<td>' . htmlspecialchars($vendedor['nome']) . '</td>';
                                            echo '<td>' . htmlspecialchars($vendedor['email']) . '</td>';
                                            echo '<td>' . $status_badge . '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="3" class="text-center">Nenhum vendedor encontrado</td></tr>';
                                    }
                                } catch (PDOException $e) {
                                    echo '<tr><td colspan="3" class="text-center">Erro ao carregar vendedores</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ações Rápidas -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Ações Rápidas</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-3">
                    <a href="<?php echo BASE_URL; ?>/admin/adicionar_vendedor.php" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus mr-2"></i> Novo Vendedor
                    </a>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <a href="<?php echo BASE_URL; ?>/admin/registrar_venda.php" class="btn btn-success btn-block">
                        <i class="fas fa-plus-circle mr-2"></i> Registrar Venda
                    </a>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <a href="<?php echo BASE_URL; ?>/admin/categorias.php" class="btn btn-warning btn-block">
                        <i class="fas fa-tags mr-2"></i> Gerenciar Categorias
                    </a>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <a href="<?php echo BASE_URL; ?>/admin/relatorios.php" class="btn btn-info btn-block">
                        <i class="fas fa-chart-bar mr-2"></i> Ver Relatórios
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer_admin.php');
?>
