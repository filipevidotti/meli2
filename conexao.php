<?php
// Configuração de conexão com banco de dados
$servername = "mysql.annemacedo.com.br";
$username = "annemacedo01";
$password = "Vingador13Anne";
$dbname = "annemacedo01";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());

}
?>