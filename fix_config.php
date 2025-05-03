<?php
// Conexão com o banco de dados
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
    
    // Verificar se a coluna já existe
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'telefone'");
    $column_exists = $stmt->rowCount() > 0;
    
    if (!$column_exists) {
        // Adicionar a coluna telefone
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN telefone VARCHAR(20) NULL");
        echo "Coluna 'telefone' adicionada com sucesso à tabela 'usuarios'.";
    } else {
        echo "A coluna 'telefone' já existe na tabela 'usuarios'.";
    }
    
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
