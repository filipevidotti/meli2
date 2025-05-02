<?php
require_once('init.php');

// Proteção da página
protegerPagina();

// Obter ID do vendedor atual
$usuario_id = $_SESSION['user_id'];
$sql = "SELECT id FROM vendedores WHERE usuario_id = ?";
$vendedor = fetchSingle($sql, [$usuario_id]);

if (!$vendedor) {
    // Se não encontrar o vendedor, criar um registro
    $sql = "INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id, $_SESSION['user_name']]);
    $vendedor_id = $pdo->lastInsertId();
} else {
    $vendedor_id = $vendedor['id'];
}

$mensagem = '';
$tipo_mensagem = '';

// Processar o formulário de registro de venda
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capturar dados do formulário
    $produto = isset($_POST['produto']) ? trim($_POST['produto']) : '';
    $data_venda = isset($_POST['data_venda']) ? $_POST['data_venda'] : date('Y-m-d');
    $valor_venda = isset($_POST['valor_venda']) ? floatval($_POST['valor_venda']) : 0;
    $custo_produto = isset($_POST['custo_produto']) ? floatval($_POST['custo_produto']) : 0;
    $categoria_ml = isset($_POST['categoria']) ? trim($_POST['categoria']) : '';
    
    // Calcular taxa do ML com base na categoria (simplificado para exemplo)
    $taxa_percentual = 13; // Padrão de 13%
    
    // Verificar se a categoria existe no array $categorias_ml (que deve estar definido em outro arquivo)
    if (isset($categorias_ml[$categoria_ml])) {
        $taxa_percentual = $categorias_ml[$categoria_ml]['taxa'];
    }
    
    $taxa_ml = ($taxa_percentual / 100) * $valor_venda;
    $custo_envio = isset($_POST['custo_envio']) ? floatval($_POST['custo_envio']) : 0;
    $custos_adicionais = isset($_POST['custos_adicionais']) ? floatval($_POST['custos_adicionais']) : 0;
    
    // Calcular lucro e margem
    $lucro = $valor_venda - $custo_produto - $taxa_ml - $custo_envio - $custos_adicionais;
    $margem_lucro = ($valor_venda > 0) ? ($lucro / $valor_venda) * 100 : 0;
    
    try {
        // Inserir a venda no banco de dados
        $sql = "INSERT INTO vendas (vendedor_id, produto, data_venda, valor_venda, custo_produto, 
                taxa_ml, custo_envio, custos_adicionais, lucro, margem_lucro, categoria_ml)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $resultado = $stmt->execute([
            $vendedor_id, $produto, $data_venda, $valor_venda, $custo_produto,
            $taxa_ml, $custo_envio, $custos_adicionais, $lucro, $margem_lucro, $categoria_ml
        ]);
        
        if ($resultado) {
            $mensagem = "Venda registrada com sucesso!";
            $tipo_mensagem = "success";
            
            // Redirecionar para a página inicial após um registro bem-sucedido
            header("Location: index.php?success=1");
            exit;
        } else {
            $mensagem = "Erro ao registrar a venda. Por favor, tente novamente.";
            $tipo_mensagem = "danger";
        }
    } catch (PDOException $e) {
        $mensagem = "Erro ao registrar a venda: " . $e->getMessage();
        $tipo_mensagem = "danger";
    }
}

// Incluir cabeçalho
$page_title = 'Registrar Venda';
include(INCLUDES_DIR . '/header.php');
?>

<div class="container mt-4">
    <h2>Registrar Nova Venda</h2>
    
    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensagem; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="produto" class="form-label">Nome do Produto</label>
                        <input type="text" class="form-control" id="produto" name="produto" required>
                    </div>
                    <div class="col-md-6">
                        <label for="data_venda" class="form-label">Data da Venda</label>
                        <input type="date" class="form-control" id="data_venda" name="data_venda" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="valor_venda" class="form-label">Valor da Venda (R$)</label>
                        <input type="number" class="form-control" id="valor_venda" name="valor_venda" step="0.01" required>
                    </div>
                    <div class="col-md-4">
                        <label for="custo_produto" class="form-label">Custo do Produto (R$)</label>
                        <input type="number" class="form-control" id="custo_produto" name="custo_produto" step="0.01" required>
                    </div>
                    <div class="col-md-4">
                        <label for="categoria" class="form-label">Categoria no Mercado Livre</label>
                        <select class="form-select" id="categoria" name="categoria" required>
                            <option value="">Selecione...</option>
                            <?php
                            // Incluir categorias do arquivo taxas.php
                            if (isset($categorias_ml) && is_array($categorias_ml)) {
                                foreach ($categorias_ml as $categoria => $info) {
                                    echo '<option value="' . htmlspecialchars($categoria) . '">' . 
                                        htmlspecialchars($info['nome']) . ' (' . $info['taxa'] . '%)</option>';
                                }
                            } else {
                                echo '<option value="eletronicos">Eletrônicos (13%)</option>';
                                echo '<option value="moda">Moda (16%)</option>';
                                echo '<option value="casa_decoracao">Casa e Decoração (17%)</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="custo_envio" class="form-label">Custo de Envio (R$)</label>
                        <input type="number" class="form-control" id="custo_envio" name="custo_envio" step="0.01" value="0">
                    </div>
                    <div class="col-md-4">
                        <label for="custos_adicionais" class="form-label">Custos Adicionais (R$)</label>
                        <input type="number" class="form-control" id="custos_adicionais" name="custos_adicionais" step="0.01" value="0">
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Registrar Venda</button>
                    <a href="index.php" class="btn btn-secondary ms-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Script para calcular automaticamente a taxa, lucro e margem (opcional)
document.addEventListener('DOMContentLoaded', function() {
    const valorVendaInput = document.getElementById('valor_venda');
    const custoProdutoInput = document.getElementById('custo_produto');
    const categoriaSelect = document.getElementById('categoria');
    const custoEnvioInput = document.getElementById('custo_envio');
    const custosAdicionaisInput = document.getElementById('custos_adicionais');
    
    // Adicione aqui lógica para calcular os valores em tempo real se desejar
});
</script>

<?php
// Incluir rodapé
include(INCLUDES_DIR . '/footer.php');
?>
