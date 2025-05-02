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
    $categoria = $_POST['categoria'] ?? '';
    $nome = $_POST['nome'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    
    if ($id <= 0 || empty($categoria) || empty($nome)) {
        throw new Exception("Dados inválidos");
    }
    
    // Atualizar tipo de despesa
    $sql = "UPDATE tipos_despesa SET 
            categoria = :categoria,
            nome = :nome,
            descricao = :descricao
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':categoria', $categoria);
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        throw new Exception("Tipo de despesa não encontrado");
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Tipo de despesa atualizado com sucesso!'
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>