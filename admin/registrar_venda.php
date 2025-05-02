<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter dados do formulário
    $vendedor_id = isset($_POST['vendedor_id']) ? intval($_POST['vendedor_id']) : 0;
    $produto = isset($_POST['produto']) ? trim($_POST['produto']) : '';
    $data_venda = isset($_POST['data_venda']) ? $_POST['data_venda'] : date('Y-m-d');
    $valor_venda = isset($_POST['valor_venda']) ? floatval($_POST['valor_venda']) : 0;
    $custo_produto = isset($_POST['custo_produto']) ? floatval($_POST['custo_produto']) : 0;
    $categoria_ml = isset($_POST['categoria']) ? trim($_POST['categoria']) : '';
    $custo_envio = isset($_POST['custo_envio']) ? floatval($_POST['custo_envio']) : 0;
    $custos_adicionais = isset($_POST['custos_adicionais']) ? floatval($_POST['custos_adicionais']) : 0;
    
    // Validar dados
    if (empty($produto) || $vendedor_id <= 0) {
        $mensagem = "Por favor, preencha todos os campos obrigatórios.";
        $tipo_mensagem = "danger";
    } elseif ($valor_venda <= 0) {
        $mensagem = "O valor da venda deve ser maior que zero.";
        $tipo_mensagem = "danger";
    } else {
        try {
            // Calcular taxa do ML com base na categoria
            $taxa_percentual = 13; // Padrão de 13%
            
            // Verificar se a categoria existe no array $categorias_ml
            if (isset($categorias_ml) && isset($categorias_ml[$categoria_ml])) {
                $taxa_percentual = $categorias_ml[$categoria_ml]['taxa'];
            }
            
            $taxa_ml = ($taxa_percentual / 100) * $valor_venda;
            
            // Calcular lucro e margem
            $lucro = $valor_venda - $custo_produto - $taxa_ml - $custo_envio - $custos_adicionais;
            $margem_lucro = ($valor_venda > 0) ? ($lucro / $valor_venda) * 100 : 0;
            
            // Inserir venda no banco de dados
            $sql = "INSERT INTO vendas (
                vendedor_id, produto, data_venda, valor_venda, custo_produto, 
                taxa_ml, custo_envio, custos_adicionais, lucro, margem_lucro, categoria_ml
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?
            )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $vendedor_id, $produto, $data_venda, $valor_venda, $custo_produto,
                $taxa_ml, $custo_envio, $custos_adicionais, $lucro, $margem_lucro, $categoria_ml
            ]);
            
            $mensagem = "Venda registrada com sucesso!";
            $tipo_mensagem = "success";
            
            // Limpar dados do formulário após sucesso
            $produto = '';
            $valor_venda = '';
            $custo_produto = '';
            $custo_envio = '';
            $custos_adicionais = '';
            
        } catch (PDOException $e) {
            $mensagem = "Erro ao registrar venda: " . $e->getMessage();
            $tipo_mensagem = "danger";
        }
    }
}

// Obter lista de vendedores
$vendedores = [];
try {
    $sql = "SELECT v.id, u.nome FROM vendedores v 
            JOIN usuarios u ON v.usuario_id = u.id
            WHERE u.status = 'ativo' 
            ORDER BY u.nome ASC";
    $stmt = $pdo->query($sql);
    $vendedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro
}

// Incluir cabeçalho
$page_title = 'Registrar Venda';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Registrar Nova Venda</h2>
        <a href="<?php echo BASE_URL; ?>/admin/vendas.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para Lista
        </a>
    </div>
    
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
                        <label for="vendedor_id" class="form-label">Vendedor <span class="text-danger">*</span></label>
                        <select class="form-select" id="vendedor_id" name="vendedor_id" required>
                            <option value="">Selecione um vendedor...</option>
                            <?php foreach ($vendedores as $vendedor): ?>
                                <option value="<?php echo $vendedor['id']; ?>" <?php echo isset($vendedor_id) && $vendedor_id == $vendedor['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vendedor['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="data_venda" class="form-label">Data da Venda <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_venda" name="data_venda" value="<?php echo isset($data_venda) ? htmlspecialchars($data_venda) : date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="produto" class="form-label">Produto <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="produto" name="produto" value="<?php echo isset($produto) ? htmlspecialchars($produto) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="valor_venda" class="form-label">Valor da Venda (R$) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="valor_venda" name="valor_venda" value="<?php echo isset($valor_venda) ? htmlspecialchars($valor_venda) : ''; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="custo_produto" class="form-label">Custo do Produto (R$) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control" id="custo_produto" name="custo_produto" value="<?php echo isset($custo_produto) ? htmlspecialchars($custo_produto) : ''; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="categoria" class="form-label">Categoria no Mercado Livre</label>
                        <select class="form-select" id="categoria" name="categoria">
                            <option value="">Selecione uma categoria...</option>
                            <?php 
                            // Verificar se $categorias_ml está disponível
                            if (isset($categorias_ml) && is_array($categorias_ml)):
                                foreach ($categorias_ml as $key => $categoria):
                            ?>
                                <option value="<?php echo $key; ?>" <?php echo isset($categoria_ml) && $categoria_ml == $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['nome']); ?> (<?php echo $categoria['taxa']; ?>%)
                                </option>
                            <?php 
                                endforeach;
                            endif;
                            ?>
                        </select>
                        <div class="form-text">A taxa do Mercado Livre será calculada automaticamente com base na categoria.</div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="custo_envio" class="form-label">Custo de Envio (R$)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="custo_envio" name="custo_envio" value="<?php echo isset($custo_envio) ? htmlspecialchars($custo_envio) : '0.00'; ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="custos_adicionais" class="form-label">Custos Adicionais (R$)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="custos_adicionais" name="custos_adicionais" value="<?php echo isset($custos_adicionais) ? htmlspecialchars($custos_adicionais) : '0.00'; ?>">
                        <div class="form-text">Embalagem, impostos, etc.</div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Registrar Venda</button>
                    <a href="<?php echo BASE_URL; ?>/admin/vendas.php" class="btn btn-secondary ms-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Script para calcular automaticamente a taxa do Mercado Livre
document.addEventListener('DOMContentLoaded', function() {
    const categoriaSelect = document.getElementById('categoria');
    const valorVendaInput = document.getElementById('valor_venda');
    const custoProdutoInput = document.getElementById('custo_produto');
    
    // Função para calcular taxa
    function calcularTaxa() {
        const categoria = categoriaSelect.value;
        const valorVenda = parseFloat(valorVendaInput.value) || 0;
        
        // Aqui você pode adicionar lógica para mostrar a taxa calculada
        // em algum elemento HTML de sua escolha
    }
    
    // Adicionar event listeners
    if (categoriaSelect && valorVendaInput) {
        categoriaSelect.addEventListener('change', calcularTaxa);
        valorVendaInput.addEventListener('input', calcularTaxa);
    }
});
</script>

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer_admin.php');
?>
