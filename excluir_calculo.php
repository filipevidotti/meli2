<?php
// excluir_calculo.php
require_once('init.php');

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Método não permitido');
}

// Obter dados JSON da requisição
$data = json_decode(file_get_contents('php://input'), true);
$response = ['success' => false, 'message' => ''];

if (!isset($data['id']) || !is_numeric($data['id'])) {
    $response['message'] = 'ID do cálculo não fornecido ou inválido.';
    echo json_encode($response);
    exit;
}

try {
    // Verificar se o cálculo pertence ao usuário atual
    $sql = "SELECT id FROM calculos_lucro WHERE id = ? AND usuario_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data['id'], $_SESSION['user_id']]);
    $calculo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$calculo) {
        $response['message'] = 'Cálculo não encontrado ou não pertence ao usuário atual.';
        echo json_encode($response);
        exit;
    }
    
    // Excluir o cálculo
    $sql = "DELETE FROM calculos_lucro WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data['id']]);
    
    $response['success'] = true;
    $response['message'] = 'Cálculo excluído com sucesso!';
    
} catch (PDOException $e) {
    $response['message'] = 'Erro ao excluir cálculo: ' . $e->getMessage();
}

// Enviar resposta JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
