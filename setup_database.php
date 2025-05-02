<?php
require_once('init.php');

// Verificar se o arquivo já foi executado
if (isset($_GET['setup']) && $_GET['setup'] == 'confirm') {
    try {
        // Verificar se a tabela 'usuarios' já tem algum registro
        $sql = "SELECT COUNT(*) FROM usuarios";
        $count = $pdo->query($sql)->fetchColumn();
        
        if ($count > 0) {
            echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
            echo "<h2>Banco de dados já configurado</h2>";
            echo "<p>A tabela 'usuarios' já existe e contém registros. Não é necessário criar novamente.</p>";
            echo "<p>Você pode criar um usuário administrador usando o script <a href='create_admin.php'>create_admin.php</a>.</p>";
            echo "</div>";
        } else {
            // Criar usuário administrador padrão
            $nome = "Administrador";
            $email = "admin@annemacedo.com.br";
            $senha = "Admin#2025";
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO usuarios (nome, email, senha_hash, tipo) VALUES (?, ?, ?, 'admin')";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$nome, $email, $senha_hash]);
            
            if ($result) {
                echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
                echo "<h2 style='color: #28a745;'>Configuração concluída com sucesso!</h2>";
                echo "<p>O banco de dados foi configurado e um usuário administrador foi criado.</p>";
                echo "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
                echo "<p><strong>Senha:</strong> " . htmlspecialchars($senha) . "</p>";
                echo "<p>Guarde estas informações em um local seguro.</p>";
                echo "<a href='login.php' style='display: inline-block; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Ir para Login</a>";
                echo "<p style='margin-top: 20px; color: #dc3545; font-weight: bold;'>ATENÇÃO: Por segurança, remova este arquivo após o uso!</p>";
                echo "</div>";
            } else {
                echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
                echo "<h2 style='color: #dc3545;'>Erro</h2>";
                echo "<p>Não foi possível criar o usuário administrador.</p>";
                echo "</div>";
            }
        }
    } catch (PDOException $e) {
        echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
        echo "<h2 style='color: #dc3545;'>Erro</h2>";
        echo "<p>Ocorreu um erro: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
} else {
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
    echo "<h2>Configurar Banco de Dados</h2>";
    echo "<p>Este script verificará se as tabelas necessárias existem no banco de dados e criará um usuário administrador.</p>";
    echo "<p><strong>Email:</strong> admin@annemacedo.com.br</p>";
    echo "<p><strong>Senha:</strong> Admin#2025</p>";
    echo "<p>Para continuar, clique no botão abaixo:</p>";
    echo "<a href='?setup=confirm' style='display: inline-block; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Configurar Banco de Dados</a>";
    echo "<p style='margin-top: 20px; color: #dc3545;'><strong>ATENÇÃO:</strong> Por segurança, remova este arquivo após configurar o banco de dados!</p>";
    echo "</div>";
}
?>
