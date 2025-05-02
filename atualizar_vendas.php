<?php
header('Content-Type: application/json');
include_once 'includes/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    // Conectar ao banco de dados
    global $conn;
    
    // Receber dados do formulário
    $produto_id = intval($_POST['produto_id'] ?? 0);
    $unidades_vendidas = intval($_POST['unidades_vendidas'] ?? 0);
    
    if ($produto_id <= 0) {
        throw new Exception("ID de produto inválido");
    }
    
    // Buscar informações do produto
    $sql = "SELECT preco_venda, unidades_vendidas FROM produtos WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $produto_id);
    $stmt->execute();
    $produto = $stmt->fetch();
    
    if (!$produto) {
        throw new Exception("Produto não encontrado");
    }
    
    // Calcular novo faturamento
    $novas_unidades = $produto['unidades_vendidas'] + $unidades_vendidas;
    $faturado = $novas_unidades * $produto['preco_venda'];
    
    // Atualizar no banco
    $sql = "UPDATE produtos SET 
            unidades_vendidas = :unidades,
            faturado = :faturado
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':unidades', $novas_unidades);
    $stmt->bindParam(':faturado', $faturado);
    $stmt->bindParam(':id', $produto_id);
    $stmt->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Vendas atualizadas com sucesso!'
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>