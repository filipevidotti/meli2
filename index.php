<?php
// Incluir o arquivo de inicialização
require_once('init.php');

// Proteção da página
protegerPagina();

// Verificar filtro de datas
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Obter ID do usuário logado
$usuario_id = $_SESSION['user_id'] ?? 0;

// Obter ID do vendedor com base no ID do usuário
$sql = "SELECT id FROM vendedores WHERE usuario_id = ?";
$vendedor = fetchSingle($sql, [$usuario_id]);
$vendedor_id = $vendedor['id'] ?? 0;

// Verificar se é a primeira vez que o usuário acessa
if (!$vendedor_id && $usuario_id) {
    // Criar um registro de vendedor automaticamente
    $sql = "SELECT nome FROM usuarios WHERE id = ?";
    $usuario = fetchSingle($sql, [$usuario_id]);
    
    if ($usuario) {
        $nome_fantasia = $usuario['nome'];
        $sql = "INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)";
        try {
            executeQuery($sql, [$usuario_id, $nome_fantasia]);
            // Buscar o ID inserido
            $vendedor_id = $pdo->lastInsertId();
        } catch (Exception $e) {
            // Caso falhe, só registra o erro e continua
            error_log("Erro ao criar vendedor: " . $e->getMessage());
        }
    }
}

// Incluir cabeçalho
$page_title = 'Dashboard de Vendas';
include(INCLUDES_DIR . '/header.php');
?>

<!-- Resto do código... -->

<!-- Resto do seu código HTML/PHP -->


<!-- Resto do código HTML/PHP -->

<div class="container mt-4">
    <h2>Dashboard de Vendas</h2>
    
    <!-- Filtro de Data -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filtrar por Data</h5>
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
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Cards de Resumo -->
    <div class="row">
        <?php
        // Buscar dados de vendas do período
        $sql = "SELECT 
                SUM(valor_venda) as total_vendas,
                COUNT(*) as numero_vendas,
                SUM(lucro) as total_lucro,
                AVG(margem_lucro) as media_margem
                FROM vendas 
                WHERE vendedor_id = ? 
                AND data_venda BETWEEN ? AND ?";
        
        $resumo = fetchSingle($sql, [$vendedor_id, $start_date, $end_date]);
        
        if (!$resumo || $resumo['numero_vendas'] == 0) {
            echo '<div class="alert alert-info">Nenhuma venda encontrada no período selecionado.</div>';
        } else {
        ?>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total em Vendas</h5>
                        <h3><?php echo formatCurrency($resumo['total_vendas']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Lucro Total</h5>
                        <h3><?php echo formatCurrency($resumo['total_lucro']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Número de Vendas</h5>
                        <h3><?php echo $resumo['numero_vendas']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <h5 class="card-title">Margem Média</h5>
                        <h3><?php echo formatPercentage($resumo['media_margem']); ?></h3>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
    
    <!-- Lista de Vendas Recentes -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Vendas Recentes</h5>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Produto</th>
                        <th>Valor</th>
                        <th>Custo</th>
                        <th>Taxa ML</th>
                        <th>Lucro</th>
                        <th>Margem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Buscar vendas recentes
                    $sql = "SELECT * FROM vendas 
                            WHERE vendedor_id = ? 
                            AND data_venda BETWEEN ? AND ? 
                            ORDER BY data_venda DESC 
                            LIMIT 10";
                    
                    $vendas = fetchAll($sql, [$vendedor_id, $start_date, $end_date]);
                    
                    if (count($vendas) == 0) {
                        echo '<tr><td colspan="7" class="text-center">Nenhuma venda encontrada.</td></tr>';
                    } else {
                        foreach ($vendas as $venda) {
                            echo '<tr>';
                            echo '<td>' . date('d/m/Y', strtotime($venda['data_venda'])) . '</td>';
                            echo '<td>' . htmlspecialchars($venda['produto']) . '</td>';
                            echo '<td>' . formatCurrency($venda['valor_venda']) . '</td>';
                            echo '<td>' . formatCurrency($venda['custo_produto']) . '</td>';
                            echo '<td>' . formatCurrency($venda['taxa_ml']) . '</td>';
                            echo '<td>' . formatCurrency($venda['lucro']) . '</td>';
                            echo '<td>' . formatPercentage($venda['margem_lucro']) . '</td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Adicionar Nova Venda -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Adicionar Nova Venda</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novaVendaModal">
                Nova Venda
            </button>
        </div>
    </div>
</div>

<!-- Modal Nova Venda -->
<div class="modal fade" id="novaVendaModal" tabindex="-1" aria-labelledby="novaVendaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="novaVendaModalLabel">Registrar Nova Venda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formNovaVenda" method="POST" action="registrar_venda.php">
                    <div class="mb-3">
                        <label for="produto" class="form-label">Produto</label>
                        <input type="text" class="form-control" id="produto" name="produto" required>
                    </div>
                    <div class="mb-3">
                        <label for="data_venda" class="form-label">Data da Venda</label>
                        <input type="date" class="form-control" id="data_venda" name="data_venda" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="valor_venda" class="form-label">Valor da Venda</label>
                        <input type="number" step="0.01" class="form-control" id="valor_venda" name="valor_venda" required>
                    </div>
                    <div class="mb-3">
                        <label for="custo_produto" class="form-label">Custo do Produto</label>
                        <input type="number" step="0.01" class="form-control" id="custo_produto" name="custo_produto" required>
                    </div>
                    <div class="mb-3">
                        <label for="categoria" class="form-label">Categoria no Mercado Livre</label>
                        <select class="form-select" id="categoria" name="categoria" required>
                            <option value="">Selecione...</option>
                            <?php
                            // Incluir categorias do arquivo taxas.php
                            require_once(BASE_PATH . '/taxas.php');
                            foreach ($categorias_ml as $categoria => $info) {
                                echo '<option value="' . htmlspecialchars($categoria) . '">' . htmlspecialchars($info['nome']) . ' (' . $info['taxa'] . '%)</option>';
                            }
                            ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="formNovaVenda" class="btn btn-primary">Salvar</button>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer.php');
?>
