<?php
// Iniciar sessão
session_start();

// Definir a URL base para apontar explicitamente para o subdiretório correto
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . $host . '/novo2';
}

// Definir a URL base
define('BASE_URL', getBaseUrl());

// Definir o caminho base do projeto - importante usar o caminho absoluto real
define('BASE_PATH', realpath(dirname(__FILE__)));

// Configuração de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', BASE_PATH . '/logs/error.log');

// Criar diretório de logs se não existir
if (!is_dir(BASE_PATH . '/logs')) {
    mkdir(BASE_PATH . '/logs', 0755, true);
}

// Diretórios comuns
define('CONFIG_DIR', BASE_PATH . '/config');
define('ADMIN_DIR', BASE_PATH . '/admin');
define('FUNCTIONS_DIR', BASE_PATH . '/functions');
define('CALCULADORA_DIR', BASE_PATH . '/calculadorameli');
define('ASSETS_DIR', BASE_PATH . '/assets');
define('INCLUDES_DIR', BASE_PATH . '/includes');

// URLs comuns
define('CONFIG_URL', BASE_URL . '/config');
define('ADMIN_URL', BASE_URL . '/admin');
define('ASSETS_URL', BASE_URL . '/assets');

// Incluir conexão com o banco de dados
if (file_exists(CONFIG_DIR . '/conexao.php')) {
    require_once(CONFIG_DIR . '/conexao.php');
} else {
    die("Arquivo de conexão com o banco de dados não encontrado.");
}

// Incluir arquivo de categorias e taxas
if (file_exists(BASE_PATH . '/taxas.php')) {
    require_once(BASE_PATH . '/taxas.php');
}

// Funções básicas de formatação
function formatCurrency($value) {
    $value = $value ?? 0;
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function formatPercentage($value) {
    $value = $value ?? 0;
    return number_format($value, 2, ',', '.') . '%';
}

function formatDate($date) {
    if (empty($date)) return '';
    try {
        return date('d/m/Y', strtotime($date));
    } catch (Exception $e) {
        return '';
    }
}

// Funções de autenticação e verificação de usuário
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

function isVendedor() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'vendedor';
}

// Função para proteger páginas
function protegerPagina() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Função para redirecionamento baseado no tipo de usuário
function redirectBasedOnUserType() {
    if (isAdmin()) {
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    } elseif (isVendedor()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    } else {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// Funções de Banco de Dados
function fetchSingle($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Erro na consulta: ' . $e->getMessage());
        return false;
    }
}

function fetchAll($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Erro na consulta: ' . $e->getMessage());
        return [];
    }
}

function executeQuery($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log('Erro na execução da query: ' . $e->getMessage());
        return false;
    }
}

// Verificar e criar registro de vendedor se necessário
function checkAndCreateVendedor($usuario_id) {
    if (empty($usuario_id)) return false;
    
    try {
        // Verificar se existe vendedor para este usuário
        $sql = "SELECT id FROM vendedores WHERE usuario_id = ?";
        $vendedor = fetchSingle($sql, [$usuario_id]);
        
        if ($vendedor) {
            return $vendedor['id'];
        } else {
            // Obter nome do usuário
            $sql = "SELECT nome FROM usuarios WHERE id = ?";
            $usuario = fetchSingle($sql, [$usuario_id]);
            
            if ($usuario) {
                $nome_fantasia = $usuario['nome'];
                $sql = "INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)";
                
                if (executeQuery($sql, [$usuario_id, $nome_fantasia])) {
                    global $pdo;
                    return $pdo->lastInsertId();
                }
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log('Erro ao verificar/criar vendedor: ' . $e->getMessage());
        return false;
    }
}

// Páginas públicas (não precisam de autenticação)
$public_pages = [
    'login.php', 
    'register.php', 
    'forgot_password.php', 
    'reset_password.php',
    'setup_tables.php', 
    'create_admin.php', 
    'callback.php', 
    'diagnostico.php',
    'config_vendedor.php',  // Adicionamos esta página como pública
    'emergency_login.php',  // Página de login emergencial
    'direct_index.php',     // Dashboard emergencial
    'test.php',             // Teste do sistema
    'fix_vendedores.php',   // Correção de vendedores
    'check_passwords.php'   // Verificação de senhas
];

// Verificar automaticamente a autenticação apenas para páginas restritas
$current_page = basename($_SERVER['PHP_SELF']);

// Importante: esta verificação deve vir DEPOIS de todas as definições de funções
if (!in_array($current_page, $public_pages) && $current_page != 'index.php') {
    // Se estiver em área de administração
    if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
        requireAdmin();
    } 
    // Para outras páginas restritas
    else {
        protegerPagina();
    }
}
?>
