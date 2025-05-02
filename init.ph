<?php
session_start();

// Definir o caminho base do projeto
define('BASE_PATH', realpath(dirname(__FILE__)));

// Diretórios comuns
define('CONFIG_DIR', BASE_PATH . '/config');
define('ADMIN_DIR', BASE_PATH . '/admin');
define('FUNCTIONS_DIR', BASE_PATH . '/functions');
define('CALCULADORA_DIR', BASE_PATH . '/calculadorameli');
define('ASSETS_DIR', BASE_PATH . '/assets');

// Incluir arquivos importantes
require_once(CONFIG_DIR . '/conexao.php');
require_once(FUNCTIONS_DIR . '/format_functions.php');
require_once(CONFIG_DIR . '/config.php');

// Função para verificar se é admin
function isAdmin() {
    return (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin');
}

// Função para verificar se é vendedor
function isVendedor() {
    return (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'vendedor');
}

// Função para exigir login de admin
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ' . BASE_PATH . '/login.php');
        exit;
    }
}

// Função para exigir login de vendedor ou admin
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_PATH . '/login.php');
        exit;
    }
}

// Função para redirecionamento baseado no tipo de usuário
function redirectBasedOnUserType() {
    if (isAdmin()) {
        header('Location: ' . BASE_PATH . '/admin/');
        exit;
    } elseif (isVendedor()) {
        header('Location: ' . BASE_PATH . '/index.php');
        exit;
    } else {
        header('Location: ' . BASE_PATH . '/login.php');
        exit;
    }
}
?>
