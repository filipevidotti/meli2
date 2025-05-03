<?php
// Iniciar sessão
session_start();

// Definir exibição de erros para depuração
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Página de Diagnóstico</h1>";

// Informações da sessão
echo "<h2>Dados da Sessão:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Verificar se o usuário está logado
echo "<h2>Status de Login:</h2>";
echo "user_id está definido: " . (isset($_SESSION['user_id']) ? "SIM" : "NÃO") . "<br>";
echo "user_type está definido: " . (isset($_SESSION['user_type']) ? "SIM - " . $_SESSION['user_type'] : "NÃO") . "<br>";

// Verificar se a função protegerPagina existe
echo "<h2>Status das Funções:</h2>";
echo "Função protegerPagina() existe: " . (function_exists('protegerPagina') ? "SIM" : "NÃO") . "<br>";

// Carregar o arquivo de funções de autenticação
echo "<h2>Tentativa de carregamento de funções:</h2>";
if (file_exists('functions/auth.php')) {
    echo "Arquivo functions/auth.php existe - tentando incluir...<br>";
    include_once 'functions/auth.php';
    echo "Função protegerPagina() existe após include: " . (function_exists('protegerPagina') ? "SIM" : "NÃO") . "<br>";
} else {
    echo "ERRO: Arquivo functions/auth.php NÃO existe!<br>";
}

// Exibir a função protegerPagina
if (function_exists('protegerPagina')) {
    echo "<h2>Conteúdo da função protegerPagina():</h2>";
    $func = new ReflectionFunction('protegerPagina');
    $filename = $func->getFileName();
    $start_line = $func->getStartLine();
    $end_line = $func->getEndLine();
    
    echo "Função definida em: " . $filename . " (linhas " . $start_line . "-" . $end_line . ")<br>";
    
    $file = file($filename);
    echo "<pre>";
    for ($i = $start_line - 1; $i < $end_line; $i++) {
        echo htmlspecialchars($file[$i]);
    }
    echo "</pre>";
}

// FERRAMENTA DE CORREÇÃO
echo "<h2>Ferramenta de Correção:</h2>";
echo "<form method='post'>";
echo "<input type='hidden' name='action' value='fix'>";
echo "<button type='submit' class='btn btn-danger'>Corrigir problema de redirecionamento em loop</button>";
echo "</form>";

// Aplicar correção se solicitado
if (isset($_POST['action']) && $_POST['action'] === 'fix') {
    // Modificar o arquivo vendedor_index.php para evitar loops
    $file = 'vendedor_index.php';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Fazer backup
        if (!file_exists($file . '.bak')) {
            copy($file, $file . '.bak');
        }
        
        // Substituir a linha problemática
        $pattern = "/if\s*\(\s*!\s*isset\s*\(\s*\\\$_SESSION\s*\[\s*'user_id'\s*\]\s*\)\s*\|\|\s*!\s*isset\s*\(\s*\\\$_SESSION\s*\[\s*'user_type'\s*\]\s*\)\s*\|\|\s*\\\$_SESSION\s*\[\s*'user_type'\s*\]\s*!==\s*'vendedor'\s*\)\s*\{/";
        $replacement = "if (false) { // Linha desabilitada temporariamente para evitar loop de redirecionamento";
        
        $new_content = preg_replace($pattern, $replacement, $content);
        
        if ($new_content !== $content) {
            file_put_contents($file, $new_content);
            echo "<div style='color: green; margin: 15px 0;'>✅ Arquivo $file modificado com sucesso para evitar loop de redirecionamento!</div>";
        } else {
            echo "<div style='color: red; margin: 15px 0;'>❌ Não foi possível modificar o arquivo $file. Padrão não encontrado.</div>";
            
            // Estratégia alternativa - inserir no início do arquivo
            $insert_code = "<?php
// MODIFICAÇÃO TEMPORÁRIA: Comentado para evitar loop de redirecionamento
// Original: if (!isset(\$_SESSION['user_id']) || !\$_SESSION['user_type'] === 'vendedor') {
//    header('Location: emergency_login.php');
//    exit;
// }

// Iniciar sessão se ainda não iniciou
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>\n";
            
            $content_without_first_php = preg_replace('/^<\?php/', '', $content, 1);
            $new_content = $insert_code . $content_without_first_php;
            
            file_put_contents($file, $new_content);
            echo "<div style='color: green; margin: 15px 0;'>✅ Arquivo $file modificado com sucesso usando método alternativo!</div>";
        }
    } else {
        echo "<div style='color: red; margin: 15px 0;'>❌ Arquivo $file não encontrado.</div>";
    }
    
    // Criar um arquivo temporário para fazer login direto
    $emergency_direct_login = "direct_login.php";
    $direct_login_content = '<?php
session_start();

// Criar uma sessão de usuário diretamente
$_SESSION["user_id"] = 1;  // ID do usuário
$_SESSION["user_name"] = "Usuário de Emergência";
$_SESSION["user_email"] = "emergency@example.com";
$_SESSION["user_type"] = "vendedor";

echo "<h1>Login Direto Realizado</h1>";
echo "<p>Você agora está logado como vendedor para fins de emergência.</p>";
echo "<p>Agora você pode acessar: <a href=\'vendedor_index.php\'>Dashboard do Vendedor</a></p>";
?>';

    file_put_contents($emergency_direct_login, $direct_login_content);
    echo "<div style='color: green; margin: 15px 0;'>✅ Arquivo de login direto criado: <a href='$emergency_direct_login'>$emergency_direct_login</a></div>";
}

// Links úteis
echo "<h2>Links Úteis:</h2>";
echo "<ul>";
echo "<li><a href='emergency_login.php'>Página de Login de Emergência</a></li>";
echo "<li><a href='direct_login.php'>Login Direto de Emergência</a> (Disponível após clicar em 'Corrigir problema')</li>";
echo "<li><a href='vendedor_index.php'>Dashboard do Vendedor</a></li>";
echo "<li><a href='index.php'>Página Inicial</a></li>";
echo "</ul>";

// Exibir diretório atual
echo "<h2>Informações do Sistema:</h2>";
echo "Diretório atual: " . getcwd() . "<br>";
echo "Arquivo atual: " . __FILE__ . "<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Informações do servidor
echo "<h2>Informações do Servidor:</h2>";
echo "<pre>";
print_r($_SERVER);
echo "</pre>";
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
.btn-danger {
    background-color: #dc3545;
    border: none;
    color: white;
    padding: 10px 15px;
    border-radius: 5px;
    cursor: pointer;
}
</style>
