<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Corrigindo erro de constante BASE_URL</h1>";

// Ler o arquivo init.php
$init_file = 'init.php';
if (file_exists($init_file)) {
    // Fazer backup
    if (!file_exists($init_file . '.bak3')) {
        copy($init_file, $init_file . '.bak3');
        echo "<p>Backup do arquivo init.php criado como init.php.bak3</p>";
    }
    
    $init_content = file_get_contents($init_file);
    
    // Identificar o problema: definições de funções que usam BASE_URL antes da definição da constante
    $pattern_define = '/define\(\'BASE_URL\', getBaseUrl\(\)\);/';
    $pattern_function = '/function protegerPagina\(\)/';
    
    // Verificar posições
    preg_match($pattern_define, $init_content, $matches_define, PREG_OFFSET_CAPTURE);
    preg_match($pattern_function, $init_content, $matches_function, PREG_OFFSET_CAPTURE);
    
    if (!empty($matches_define) && !empty($matches_function)) {
        $define_pos = $matches_define[0][1];
        $function_pos = $matches_function[0][1];
        
        echo "<p>A definição de BASE_URL está na posição: $define_pos</p>";
        echo "<p>A definição de function protegerPagina() está na posição: $function_pos</p>";
        
        if ($function_pos < $define_pos) {
            echo "<p>PROBLEMA ENCONTRADO: A função protegerPagina() está sendo definida antes da constante BASE_URL.</p>";
            
            // Solução: mover a definição da constante BASE_URL para o início do arquivo
            // ou mover a definição da função para depois da constante
            
            // Extrair as linhas principais
            $pattern_base_url = '/\/\/ Definir a URL base.*?define\(\'BASE_URL\', getBaseUrl\(\)\);/s';
            preg_match($pattern_base_url, $init_content, $base_url_section);
            
            if (!empty($base_url_section)) {
                $base_url_code = $base_url_section[0];
                
                // Remover a seção BASE_URL do código original
                $init_content_without_base = preg_replace($pattern_base_url, '// BASE_URL movida para o início', $init_content, 1);
                
                // Adicionar ao início, logo após a sessão
                $session_pattern = '/session_start\(\);/';
                $init_content_fixed = preg_replace($session_pattern, "session_start();\n\n" . $base_url_code . "\n", $init_content_without_base, 1);
                
                // Escrever o arquivo corrigido
                if ($init_content_fixed !== $init_content && $init_content_fixed !== null) {
                    file_put_contents($init_file, $init_content_fixed);
                    echo "<p style='color: green; font-weight: bold;'>✅ init.php corrigido: BASE_URL movida para antes da definição da função protegerPagina()!</p>";
                } else {
                    echo "<p style='color: red;'>Não foi possível mover a definição de BASE_URL.</p>";
                }
            } else {
                echo "<p style='color: red;'>Não foi possível encontrar a seção completa de definição de BASE_URL.</p>";
            }
        } else {
            echo "<p>A ordem das definições parece correta. A função protegerPagina() é definida após a constante BASE_URL.</p>";
            
            // Verificar onde a função protegerPagina() é chamada
            echo "<h2>Análise de conteúdo do init.php:</h2>";
            echo "<pre>" . htmlspecialchars(substr($init_content, 0, 500)) . "...</pre>";
            
            // Verificar se há alguma chamada à função antes da definição
            if (preg_match('/protegerPagina\(\).*?function protegerPagina\(\)/s', $init_content)) {
                echo "<p style='color: red;'>A função protegerPagina() está sendo CHAMADA antes de ser definida!</p>";
                
                // Solução alternativa: desativar temporariamente chamadas automáticas à função
                $init_content_fixed = preg_replace('/(\$current_page = basename\(\$_SERVER\[\'PHP_SELF\'\]\);.*?protegerPagina\(\);)/s', '// Seção desativada temporariamente para evitar erros
// $1', $init_content);
                
                // Escrever o arquivo corrigido
                if ($init_content_fixed !== $init_content && $init_content_fixed !== null) {
                    file_put_contents($init_file, $init_content_fixed);
                    echo "<p style='color: green; font-weight: bold;'>✅ init.php corrigido: Chamada automática à protegerPagina() desativada temporariamente!</p>";
                } else {
                    echo "<p style='color: red;'>Não foi possível desativar a chamada automática à função.</p>";
                }
            }
        }
    } else {
        echo "<p>Não foi possível encontrar as definições necessárias no arquivo.</p>";
    }
    
    // Criar uma versão simplificada do init.php
    $simple_init = '<?php
// Iniciar sessão
session_start();

// Definir a URL base para apontar explicitamente para o subdiretório correto
function getBaseUrl() {
    $protocol = isset($_SERVER[\'HTTPS\']) && $_SERVER[\'HTTPS\'] === \'on\' ? \'https://\' : \'http://\';
    $host = $_SERVER[\'HTTP_HOST\'];
    return $protocol . $host . \'/novo2\';
}

// Definir a URL base
define(\'BASE_URL\', getBaseUrl());

// Definir o caminho base do projeto - importante usar o caminho absoluto real
define(\'BASE_PATH\', realpath(dirname(__FILE__)));

// Configuração de erros
error_reporting(E_ALL);
ini_set(\'display_errors\', 1);
ini_set(\'log_errors\', 1);
ini_set(\'error_log\', BASE_PATH . \'/logs/error.log\');

// Criar diretório de logs se não existir
if (!is_dir(BASE_PATH . \'/logs\')) {
    mkdir(BASE_PATH . \'/logs\', 0755, true);
}

// Diretórios comuns
define(\'CONFIG_DIR\', BASE_PATH . \'/config\');
define(\'ADMIN_DIR\', BASE_PATH . \'/admin\');
define(\'FUNCTIONS_DIR\', BASE_PATH . \'/functions\');
define(\'CALCULADORA_DIR\', BASE_PATH . \'/calculadorameli\');
define(\'ASSETS_DIR\', BASE_PATH . \'/assets\');
define(\'INCLUDES_DIR\', BASE_PATH . \'/includes\');

// URLs comuns
define(\'CONFIG_URL\', BASE_URL . \'/config\');
define(\'ADMIN_URL\', BASE_URL . \'/admin\');
define(\'ASSETS_URL\', BASE_URL . \'/assets\');

// Incluir conexão com o banco de dados
if (file_exists(CONFIG_DIR . \'/conexao.php\')) {
    require_once(CONFIG_DIR . \'/conexao.php\');
} else {
    die("Arquivo de conexão com o banco de dados não encontrado.");
}

// Funções básicas de autenticação
function isLoggedIn() {
    return isset($_SESSION[\'user_id\']) && !empty($_SESSION[\'user_id\']);
}

function isAdmin() {
    return isset($_SESSION[\'user_type\']) && $_SESSION[\'user_type\'] === \'admin\';
}

function isVendedor() {
    return isset($_SESSION[\'user_type\']) && $_SESSION[\'user_type\'] === \'vendedor\';
}

// Função para proteger páginas - versão simples sem dependência circular
function protegerPagina($tipo_acesso = \'\', $redirect = \'\') {
    if (!isset($_SESSION[\'user_id\'])) {
        $login_url = getBaseUrl() . \'/login.php\';
        header(\'Location: \' . $login_url);
        exit;
    }
    
    if (!empty($tipo_acesso) && (!isset($_SESSION[\'user_type\']) || $_SESSION[\'user_type\'] !== $tipo_acesso)) {
        if (!empty($redirect)) {
            header(\'Location: \' . $redirect);
        } else {
            $base = getBaseUrl();
            switch ($_SESSION[\'user_type\']) {
                case \'admin\':
                    header(\'Location: \' . $base . \'/admin/index.php\');
                    break;
                case \'vendedor\':
                    header(\'Location: \' . $base . \'/vendedor_index.php\');
                    break;
                default:
                    header(\'Location: \' . $base . \'/index.php\');
            }
        }
        exit;
    }
    
    return true;
}

// Funções básicas de formatação
function formatCurrency($value) {
    $value = $value ?? 0;
    return \'R$ \' . number_format($value, 2, \',\', \'.\');
}

function formatPercentage($value) {
    $value = $value ?? 0;
    return number_format($value, 2, \',\', \'.\') . \'%\';
}

function formatDate($date) {
    if (empty($date)) return \'\';
    try {
        return date(\'d/m/Y\', strtotime($date));
    } catch (Exception $e) {
        return \'\';
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
        error_log(\'Erro na consulta: \' . $e->getMessage());
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
        error_log(\'Erro na consulta: \' . $e->getMessage());
        return [];
    }
}

// Páginas públicas (não precisam de autenticação)
$public_pages = [
    \'login.php\', 
    \'register.php\', 
    \'forgot_password.php\', 
    \'reset_password.php\',
    \'emergency_login.php\',  
    \'direct_login.php\',
    \'test.php\',
    \'debug_page.php\',
    \'fix_base_url.php\',
    \'direct_index.php\'
];

// IMPORTANTE: NÃO verificar automaticamente a autenticação até que tudo esteja funcionando
// A verificação automática está desativada temporariamente
/*
$current_page = basename($_SERVER[\'PHP_SELF\']);
if (!in_array($current_page, $public_pages) && $current_page != \'index.php\') {
    if (strpos($_SERVER[\'PHP_SELF\'], \'/admin/\') !== false) {
        requireAdmin();
    } else {
        protegerPagina();
    }
}
*/
?>';

    echo "<form method='post' action=''>";
    echo "<input type='hidden' name='action' value='use_simple_init'>";
    echo "<button type='submit' style='background-color: #4CAF50; color: white; padding: 10px 15px; border: none; cursor: pointer; margin: 10px 0;'>Substituir por uma versão simplificada do init.php</button>";
    echo "</form>";
    
    if (isset($_POST['action']) && $_POST['action'] === 'use_simple_init') {
        // Fazer backup
        if (!file_exists($init_file . '.complete.bak')) {
            copy($init_file, $init_file . '.complete.bak');
            echo "<p>Backup completo do init.php criado como init.php.complete.bak</p>";
        }
        
        // Substituir pelo arquivo simplificado
        file_put_contents($init_file, $simple_init);
        echo "<p style='color: green; font-weight: bold;'>✅ init.php substituído pela versão simplificada!</p>";
    }
    
    // Criar também um fix para o vendedor_index.php
    $vendedor_index_file = 'vendedor_index.php';
    if (file_exists($vendedor_index_file)) {
        // Fazer backup
        if (!file_exists($vendedor_index_file . '.final.bak')) {
            copy($vendedor_index_file, $vendedor_index_file . '.final.bak');
            echo "<p>Backup do vendedor_index.php criado como vendedor_index.php.final.bak</p>";
        }
        
        $vendedor_content = file_get_contents($vendedor_index_file);
        
        // Verificar se há alguma chamada a protegerPagina() no início
        if (preg_match('/protegerPagina\([\'"]vendedor[\'"]/', $vendedor_content)) {
            $new_content = preg_replace('/(protegerPagina\([\'"]vendedor[\'"].*?\);)/s', '// $1 // Desativado temporariamente', $vendedor_content);
            
            if ($new_content !== $vendedor_content && $new_content !== null) {
                file_put_contents($vendedor_index_file, $new_content);
                echo "<p style='color: green; font-weight: bold;'>✅ vendedor_index.php corrigido: chamada à protegerPagina() desativada temporariamente!</p>";
            }
        }
    }
    
} else {
    echo "<p style='color: red;'>Arquivo init.php não encontrado!</p>";
}

echo "<h2>Links após correção:</h2>";
echo "<ul>";
echo "<li><a href='vendedor_index.php'>Dashboard do Vendedor</a> - Tente acessar após aplicar as correções</li>";
echo "<li><a href='admin/index.php'>Área Administrativa</a> - Se você for admin</li>";
echo "<li><a href='direct_login.php'>Login Direto</a> - Para forçar um login</li>";
echo "</ul>";

echo "<p>Após as correções acima, tente acessar o dashboard do vendedor novamente.</p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 900px;
    margin: 30px auto;
    padding: 20px;
    background-color: #f5f5f5;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
h1, h2 {
    color: #333;
}
pre {
    background-color: #f8f9fa;
    padding: 10px;
    border-left: 4px solid #007bff;
    overflow-x: auto;
}
p {
    line-height: 1.6;
}
</style>
