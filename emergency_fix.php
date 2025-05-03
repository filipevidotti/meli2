<?php
/*
 * Script de emergência para corrigir problemas de função indefinida
 * Este arquivo detecta e resolve automaticamente problemas na estrutura do sistema
 */

// Iniciar a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o diretório functions existe
$functions_dir = __DIR__ . '/functions';
if (!is_dir($functions_dir)) {
    // Criar o diretório se ele não existir
    mkdir($functions_dir, 0755, true);
    echo "<p>Diretório functions criado com sucesso.</p>";
}

// Definir o conteúdo do arquivo auth.php
$auth_content = '<?php
/**
 * Funções de autenticação e proteção de páginas
 */

/**
 * Protege uma página, exigindo que o usuário esteja autenticado
 * 
 * @param string $tipo_acesso Tipo de acesso requerido (admin, vendedor)
 * @param string $redirect URL para redirecionamento em caso de falha na autenticação
 * @return bool
 */
function protegerPagina($tipo_acesso = "", $redirect = "") {
    // Se não estiver logado
    if (!isset($_SESSION["user_id"])) {
        // Se um redirecionamento específico foi definido
        if (!empty($redirect)) {
            header("Location: " . $redirect);
        } else {
            // Senão, vai para a página de login
            header("Location: emergency_login.php");
        }
        exit;
    }
    
    // Se um tipo específico de acesso foi exigido
    if (!empty($tipo_acesso)) {
        // Se o usuário não tem o tipo de acesso necessário
        if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== $tipo_acesso) {
            // Redirecionar para página específica se definida
            if (!empty($redirect)) {
                header("Location: " . $redirect);
            } else {
                // Ou redirecionar para a página apropriada conforme o tipo do usuário
                switch ($_SESSION["user_type"]) {
                    case "admin":
                        header("Location: admin/index.php");
                        break;
                    case "vendedor":
                        header("Location: vendedor_index.php");
                        break;
                    default:
                        header("Location: index.php");
                }
            }
            exit;
        }
    }
    
    return true;
}

/**
 * Verifica se o usuário está logado
 *
 * @return bool
 */
function estaLogado() {
    return isset($_SESSION["user_id"]);
}

/**
 * Verifica se o usuário tem permissão de administrador
 *
 * @return bool
 */
function ehAdmin() {
    return isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "admin";
}

/**
 * Verifica se o usuário tem permissão de vendedor
 *
 * @return bool
 */
function ehVendedor() {
    return isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "vendedor";
}';

// Criar ou substituir o arquivo auth.php
$auth_file = $functions_dir . '/auth.php';
file_put_contents($auth_file, $auth_content);
echo "<p>Arquivo auth.php criado/atualizado com sucesso.</p>";

// Agora vamos verificar o arquivo index.php
$index_file = __DIR__ . '/index.php';
$index_content = '';

if (file_exists($index_file)) {
    $index_content = file_get_contents($index_file);
    
    // Fazer backup do arquivo original
    $backup_file = __DIR__ . '/index.php.bak';
    if (!file_exists($backup_file)) {
        file_put_contents($backup_file, $index_content);
        echo "<p>Backup do arquivo index.php criado com sucesso.</p>";
    }
    
    // Verificar se o index.php já requer o arquivo auth.php
    if (strpos($index_content, 'functions/auth.php') === false) {
        // Criar um novo conteúdo para o index.php
        $new_index_content = '<?php
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
?>';

        // Atualizar o conteúdo do arquivo
        file_put_contents($index_file, $new_index_content);
        echo "<p>Arquivo index.php atualizado com sucesso.</p>";
    } else {
        echo "<p>Arquivo index.php já está configurado corretamente.</p>";
    }
} else {
    echo "<p>Arquivo index.php não encontrado.</p>";
}

// Verificar o arquivo vendedor_mercadolivre.php
$ml_file = __DIR__ . '/vendedor_mercadolivre.php';
$ml_fixed = false;

if (file_exists($ml_file)) {
    // Criar backup do arquivo
    $ml_backup = __DIR__ . '/vendedor_mercadolivre.php.bak';
    if (!file_exists($ml_backup)) {
        copy($ml_file, $ml_backup);
        echo "<p>Backup do arquivo vendedor_mercadolivre.php criado com sucesso.</p>";
    }
    
    // Ler o conteúdo do arquivo
    $ml_content = file_get_contents($ml_file);
    
    // Verificar se tem erro de sintaxe na linha 631 (como mencionado)
    $lines = explode("\n", $ml_content);
    
    if (count($lines) >= 631) {
        // Linha problemática e algumas linhas acima e abaixo
        $problem_start = max(0, 625);
        $problem_end = min(count($lines), 635);
        
        $snippet = '';
        for ($i = $problem_start; $i < $problem_end; $i++) {
            $snippet .= "Linha " . ($i + 1) . ": " . $lines[$i] . "\n";
        }
        
        echo "<h3>Possível problema no arquivo vendedor_mercadolivre.php:</h3>";
        echo "<pre>" . htmlspecialchars($snippet) . "</pre>";
        
        // Aqui poderíamos tentar consertar automaticamente, mas sem ver o código completo é arriscado
        echo "<p>Por favor, verifique o trecho acima para detectar problemas de sintaxe como 'if' sem 'endif' ou chaves '{' sem suas correspondentes '}' de fechamento.</p>";
    } else {
        echo "<p>O arquivo vendedor_mercadolivre.php tem menos de 631 linhas. Por favor, verifique manualmente por erros de sintaxe.</p>";
    }
}

echo "<h2>Verificação concluída!</h2>";
echo "<p>Agora você pode <a href='index.php'>voltar à página inicial</a> ou <a href='vendedor_index.php'>ir para o dashboard do vendedor</a>.</p>";
echo "<p>Se ainda houver problemas, use <a href='emergency_login.php'>o login de emergência</a>.</p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 30px auto;
    padding: 20px;
    background-color: #f5f5f5;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
h2, h3 {
    color: #333;
}
pre {
    background-color: #f8f9fa;
    padding: 10px;
    border-left: 4px solid #dc3545;
    overflow-x: auto;
}
p {
    line-height: 1.6;
    margin-bottom: 15px;
}
a {
    color: #007bff;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
</style>
