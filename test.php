<?php
// Verificar se init.php existe e pode ser lido
if (file_exists('init.php')) {
    $init_content = file_get_contents('init.php');
    echo "<p>init.php existe e contém " . strlen($init_content) . " bytes.</p>";
} else {
    echo "<p>ERRO: init.php não encontrado.</p>";
}

// Verificar se o diretório functions existe
if (is_dir('functions')) {
    echo "<p>Diretório functions encontrado.</p>";
} else {
    echo "<p>ERRO: Diretório functions não encontrado.</p>";
    // Tentar criar
    if (mkdir('functions', 0755)) {
        echo "<p>Diretório functions criado com sucesso.</p>";
    } else {
        echo "<p>ERRO: Falha ao criar diretório functions.</p>";
    }
}

// Verificar se auth.php existe e pode ser lido
if (file_exists('functions/auth.php')) {
    $auth_content = file_get_contents('functions/auth.php');
    echo "<p>functions/auth.php existe e contém " . strlen($auth_content) . " bytes.</p>";
} else {
    echo "<p>ERRO: functions/auth.php não encontrado.</p>";
}

// Testar inclusão de init.php (que por sua vez inclui auth.php)
try {
    require_once 'init.php';
    echo "<p>init.php incluído com sucesso.</p>";
    
    // Verificar se a função protegerPagina está definida
    if (function_exists('protegerPagina')) {
        echo "<p>Função protegerPagina() está definida.</p>";
    } else {
        echo "<p>ERRO: Função protegerPagina() não está definida.</p>";
    }
} catch (Exception $e) {
    echo "<p>ERRO ao incluir init.php: " . $e->getMessage() . "</p>";
}

echo "<p>Teste concluído!</p>";

?>