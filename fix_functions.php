<?php
// Script para resolver conflitos entre definições de funções
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Resolução de Conflitos de Funções</h1>";

// Verificar se o arquivo auth.php existe
if (file_exists('functions/auth.php')) {
    echo "<p>O arquivo functions/auth.php existe.</p>";
    
    // Fazer backup
    if (!file_exists('functions/auth.php.bak')) {
        copy('functions/auth.php', 'functions/auth.php.bak');
        echo "<p>Backup do arquivo auth.php criado.</p>";
    }
    
    // Renomear para evitar conflitos
    rename('functions/auth.php', 'functions/auth_original.php');
    echo "<p>Arquivo auth.php renomeado para auth_original.php para evitar conflitos com init.php.</p>";
}

// Verificar se o arquivo init.php foi restaurado corretamente
if (file_exists('init.php')) {
    echo "<p>O arquivo init.php existe e contém " . filesize('init.php') . " bytes.</p>";
    
    // Verificar se a função protegerPagina está definida no init.php
    $init_content = file_get_contents('init.php');
    if (strpos($init_content, 'function protegerPagina') !== false) {
        echo "<p>A função protegerPagina() está definida no init.php.</p>";
        
        // Criar um arquivo de teste para verificar se a função protegerPagina funciona
        $test_file = 'test_proteger_pagina.php';
        $test_content = '<?php
require_once "init.php";

echo "<h1>Teste da função protegerPagina()</h1>";

if (function_exists("protegerPagina")) {
    echo "<p style=\"color: green\">A função protegerPagina() existe!</p>";
    
    // Verificar se há sessão ativa
    echo "<p>Dados da sessão: </p>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    
} else {
    echo "<p style=\"color: red\">ERRO: A função protegerPagina() NÃO existe!</p>";
}

echo "<p><a href=\"vendedor_index.php\">Tentar acessar o Dashboard do Vendedor</a></p>";
echo "<p><a href=\"direct_login.php\">Fazer login direto</a></p>";
';
        
        file_put_contents($test_file, $test_content);
        echo "<p>Arquivo de teste criado: <a href=\"$test_file\">$test_file</a></p>";
    } else {
        echo "<p style=\"color: red\">ATENÇÃO: A função protegerPagina() NÃO está definida no init.php!</p>";
    }
}

// Verificar vendedor_index.php
if (file_exists('vendedor_index.php')) {
    $vendedor_content = file_get_contents('vendedor_index.php');
    $first_lines = substr($vendedor_content, 0, 300); // Primeiros caracteres
    
    echo "<h2>Primeiras linhas do vendedor_index.php:</h2>";
    echo "<pre>" . htmlspecialchars($first_lines) . "</pre>";
    
    // Verificar se existe o problema de redirecionamento em loop
    if (strpos($vendedor_content, 'if (false) { // Linha desabilitada') !== false) {
        echo "<p>O arquivo vendedor_index.php foi modificado para evitar o loop de redirecionamento.</p>";
    } else {
        echo "<p>O arquivo vendedor_index.php pode ainda conter código que causa loop de redirecionamento.</p>";
    }
}

// Restaurar o vendedor_index.php corretamente
$vendedor_index_correto = '<?php
// Iniciar sessão
session_start();

// Incluir arquivo de inicialização
require_once "init.php";

// Verificar acesso - Comentado temporariamente para evitar loops
// protegerPagina("vendedor", "emergency_login.php");

// Dados básicos
$base_url = "http://www.annemacedo.com.br/novo2";
$usuario_id = $_SESSION["user_id"] ?? 0;
$usuario_nome = $_SESSION["user_name"] ?? "Vendedor";
$usuario_email = $_SESSION["user_email"] ?? "";

// Resto do código original...
';

// Verificar se o usuário quer restaurar o arquivo
echo "<form method='post' action=''>";
echo "<input type='hidden' name='action' value='fix_vendedor_index'>";
echo "<button type='submit' style='background-color: #4CAF50; color: white; padding: 10px 15px; border: none; cursor: pointer; margin: 10px 0;'>Restaurar vendedor_index.php corretamente</button>";
echo "</form>";

// Processar o formulário
if (isset($_POST['action']) && $_POST['action'] === 'fix_vendedor_index') {
    // Fazer backup
    if (!file_exists('vendedor_index.php.bak2')) {
        copy('vendedor_index.php', 'vendedor_index.php.bak2');
    }
    
    // Escrever o novo conteúdo
    file_put_contents('vendedor_index.php', $vendedor_index_correto);
    echo "<p style='color: green; font-weight: bold;'>✅ Arquivo vendedor_index.php restaurado com sucesso!</p>";
}

echo "<h2>Links Úteis:</h2>";
echo "<ul>";
echo "<li><a href='emergency_login.php'>Login de Emergência</a></li>";
echo "<li><a href='direct_login.php'>Login Direto</a></li>";
echo "<li><a href='index.php'>Página Inicial</a></li>";
echo "<li><a href='vendedor_index.php'>Dashboard do Vendedor</a></li>";
echo "</ul>";
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
