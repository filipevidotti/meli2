<?php
// Iniciar sessão
session_start();

// Verificar acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Location: login.php");
    exit;
}

// Verificar se o método POST foi usado e se o ID foi enviado
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    header("Location: vendedor_index.php");
    exit;
}

$venda_id = intval($_POST['id']);
$usuario_id = $_SESSION['user_id'];
$base_url = 'http://www.annemacedo.com.br/novo2';

// Conectar ao banco de dados
$db_host = 'mysql.annemacedo.com.br';
$db_name = 'annemacedo02';
$db_user = 'annemacedo02';
$db_pass = 'Vingador13Anne';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Verificar se a venda pertence ao vendedor atual
    $stmt = $pdo->prepare("SELECT v.id 
                          FROM vendas v 
                          JOIN vendedores vend ON v.vendedor_id = vend.id 
                          WHERE v.id = ? AND vend.usuario_id = ?");
    $stmt->execute([$venda_id, $usuario_id]);
    
    if ($stmt->fetch()) {
        // A venda pertence ao vendedor, pode excluir
        $stmt = $pdo->prepare("DELETE FROM vendas WHERE id = ?");
        $stmt->execute([$venda_id]);
        
        $_SESSION['success'] = "Venda excluída com sucesso!";
    } else {
        $_SESSION['error'] = "Você não tem permissão para excluir esta venda.";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erro ao excluir venda: " . $e->getMessage();
}

// Redirecionar de volta
header("Location: " . $base_url . "/vendedor_index.php");
exit;
?>
