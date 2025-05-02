<?php
// Configurações do banco de dados
$db_host = 'mysql.annemacedo.com.br';
$db_name = 'annemacedo02';
$db_user = 'annemacedo02';
$db_pass = 'Vingador13Anne';

try {
    // Conectar usando PDO
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Log de conexão bem-sucedida (opcional)
    // error_log("Conexão com banco de dados estabelecida com sucesso.");
} catch (PDOException $e) {
    // Exibir mensagem de erro de forma segura
    error_log("Erro na conexão com o banco de dados: " . $e->getMessage());
    
    // Mensagem genérica para usuário
    die("Não foi possível estabelecer uma conexão com o banco de dados. Por favor, tente novamente mais tarde.");
}
?>
