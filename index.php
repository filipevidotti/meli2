<?php
// Iniciar sessão
session_start();

// Incluir funções de autenticação
if (file_exists(__DIR__ . "/functions/auth.php")) {
    require_once __DIR__ . "/functions/auth.php";
}

// Verificar tipo de usuário e redirecionar
if (isset($_SESSION["user_id"])) {
    if (isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "admin") {
        header("Location: admin/index.php");
    } else {
        header("Location: vendedor_index.php");
    }
    exit;
} else {
    // Não está logado
    header("Location: login.php");
    exit;
}
?>