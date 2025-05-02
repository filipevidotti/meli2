<?php
header('Content-Type: application/json');
include_once 'includes/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    // Conectar ao banco de dados (do arquivo conexao.php)
    global $conn;
    
    // Receber dados do formulário
    $dados = [
        'nome' => $_POST['nome_produto'] ?? '',
        'sku' => $_POST['sku'] ?? '',
        'preco_venda' => floatval($_POST['preco_venda'] ?? 0),
        'custo' => floatval($_POST['preco_custo'] ?? 0),
        'categoria_id' => $_POST['categoria'] ?? '',
        'categoria_nome' => $_POST['categoria_nome'] ?? '',
        'taxa_categoria' => floatval($_POST['taxa_categoria'] ?? 0),
        'tipo_anuncio' => $_POST['tipo_anuncio'] ?? 'classico',
        'is_supermercado' => isset($_POST['is_supermercado']) ? 1 : 0,
        'is_categoria_especial' => isset($_POST['is_categoria_especial']) ? 1 : 0,
        'is_full' => isset($_POST['is_full']) ? 1 : 0,
        'peso' => floatval($_POST['peso'] ?? 0),
        'regiao_origem' => $_POST['regiao_origem'] ?? 'sul_sudeste',
        'estado_produto' => $_POST['estado_produto'] ?? 'novo',
        'custo_fixo' => floatval($_POST['custo_fixo'] ?? 0),
        'valor_frete' => floatval($_POST['valor_frete'] ?? 0),
        'total_taxas' => floatval($_POST['total_taxas'] ?? 0),
        'lucro' => floatval($_POST['lucro'] ?? 0),
        'margem' => floatval($_POST['margem'] ?? 0),
        'roi' => floatval($_POST['roi'] ?? 0),
        'notas' => $_POST['notas'] ?? ''
    ];
    
    // Preparar e executar a query de inserção
    $sql = "INSERT INTO produtos (nome, sku, preco_venda, custo, categoria_id, categoria_nome, 
                                taxa_categoria, tipo_anuncio, is_supermercado, is_categoria_especial, 
                                is_full, peso, regiao_origem, estado_produto, custo_fixo, 
                                valor_frete, total_taxas, lucro, margem, roi, notas) 
            VALUES (:nome, :sku, :preco_venda, :custo, :categoria_id, :categoria_nome, 
                    :taxa_categoria, :tipo_anuncio, :is_supermercado, :is_categoria_especial, 
                    :is_full, :peso, :regiao_origem, :estado_produto, :custo_fixo, 
                    :valor_frete, :total_taxas, :lucro, :margem, :roi, :notas)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($dados);
    
    $produto_id = $conn->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Produto salvo com sucesso!',
        'produto_id' => $produto_id
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Erro ao salvar produto: ' . $e->getMessage()
    ]);
}
?>