<?php
// Iniciar sessão
session_start();

// Verificar tipo de usuário e redirecionar
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: vendedor_index.php');
    }
    exit;
} else {
    // Não está logado
    header('Location: login.php');
    exit;
}
?>
