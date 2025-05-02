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
    
    // Receber ID do produto
    $produto_id = intval($_POST['id'] ?? 0);
    
    if ($produto_id <= 0) {
        throw new Exception("ID de produto inválido");
    }
    
    // Excluir produto
    $sql = "DELETE FROM produtos WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $produto_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        throw new Exception("Produto não encontrado");
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Produto excluído com sucesso!'
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>