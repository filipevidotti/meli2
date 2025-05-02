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
    $id = intval($_POST['id'] ?? 0);
    $data = $_POST['data'] ?? date('Y-m-d');
    $tipo_id = intval($_POST['tipo_id'] ?? 0);
    $descricao = $_POST['descricao'] ?? '';
    $valor = floatval($_POST['valor'] ?? 0);
    
    if ($id <= 0 || $tipo_id <= 0) {
        throw new Exception("ID inválido");
    }
    
    // Atualizar despesa
    $sql = "UPDATE despesas SET 
            data = :data,
            tipo_id = :tipo_id,
            descricao = :descricao,
            valor = :valor
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':data', $data);
    $stmt->bindParam(':tipo_id', $tipo_id);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':valor', $valor);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        throw new Exception("Despesa não encontrada");
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Despesa atualizada com sucesso!'
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>