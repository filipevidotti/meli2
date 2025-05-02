<?php
// Parâmetros de conexão
$host = 'mysql.annemacedo.com.br';
$dbname = 'annemacedo02';
$username = 'annemacedo02'; 
$password = 'Vingador13Anne'; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Define a conexão como global para ser acessível em todos os arquivos
    $GLOBALS["pdo"] = $pdo;
} catch(PDOException $e) {
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}

// Função para executar queries com segurança
function executeQuery($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        die("Erro na execução da query: " . $e->getMessage());
    }
}

// Função para buscar um único registro
function fetchSingle($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Função para buscar múltiplos registros
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
