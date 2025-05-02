<?php
// Exibir todos os erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Informações básicas
echo "<h1>Teste de PHP</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Testar conexão com o banco de dados
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
    echo "<p style='color:green'>Conexão com o banco de dados: OK</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>Erro de conexão com o banco de dados: " . $e->getMessage() . "</p>";
}

// Exibir informações do servidor
echo "<h2>Informações do Servidor</h2>";
echo "<pre>";
print_r($_SERVER);
echo "</pre>";

// Testar acesso a arquivos
echo "<h2>Verificação de Arquivos</h2>";
$files = ['init.php', 'login.php', 'index.php', 'config/conexao.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p style='color:green'>$file: Existe</p>";
        echo "<p>Tamanho: " . filesize($file) . " bytes</p>";
        echo "<p>Última modificação: " . date("F d Y H:i:s", filemtime($file)) . "</p>";
    } else {
        echo "<p style='color:red'>$file: Não existe</p>";
    }
}
?>
