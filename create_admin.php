<?php
require_once('init.php');

// Verificar se o arquivo já foi executado
if (isset($_GET['create']) && $_GET['create'] == 'confirm') {
    // Dados do administrador
    $nome = "Administrador";
    $email = "admin@annemacedo.com.br";  // Pode alterar para seu email preferido
    $senha = "Admin#2025";  // Senha forte com letras, números e símbolos
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    
    try {
        // Verificar se o email já existe
        $check_sql = "SELECT COUNT(*) FROM usuarios WHERE email = ?";
        $count = $pdo->prepare($check_sql);
        $count->execute([$email]);
        $exists = $count->fetchColumn();
        
        if ($exists) {
            echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 100px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
            echo "<h2>Usuário já existe</h2>";
            echo "<p>Um usuário com este email já existe. Você pode tentar fazer login ou usar a função 'Esqueci minha senha'.</p>";
            echo "<a href='login.php' style='display: inline-block; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Ir para Login</a>";
            echo "</div>";
        } else {
            // Criar o usuário administrador
            $sql = "INSERT INTO usuarios (nome, email, senha_hash, tipo) VALUES (?, ?, ?, 'admin')";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$nome, $email, $senha_hash]);
            
            if ($result) {
                echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 100px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
                echo "<h2 style='color: #28a745;'>Administrador criado com sucesso!</h2>";
                echo "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
                echo "<p><strong>Senha:</strong> " . htmlspecialchars($senha) . "</p>";
                echo "<p>Guarde estas informações em um local seguro.</p>";
                echo "<a href='login.php' style='display: inline-block; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Ir para Login</a>";
                echo "<p style='margin-top: 20px; color: #dc3545; font-weight: bold;'>ATENÇÃO: Por segurança, remova este arquivo após o uso!</p>";
                echo "</div>";
            } else {
                echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 100px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
                echo "<h2 style='color: #dc3545;'>Erro ao criar administrador</h2>";
                echo "<p>Não foi possível criar o usuário administrador. Verifique se você tem permissões para inserir dados no banco.</p>";
                echo "</div>";
            }
        }
    } catch (PDOException $e) {
        echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 100px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
        echo "<h2 style='color: #dc3545;'>Erro</h2>";
        echo "<p>Ocorreu um erro: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
} else {
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 100px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
    echo "<h2>Criar Usuário Administrador</h2>";
    echo "<p>Este script criará um novo usuário administrador no sistema.</p>";
    echo "<p><strong>Email:</strong> admin@annemacedo.com.br</p>";
    echo "<p><strong>Senha:</strong> Admin#2025</p>";
    echo "<p>Para continuar, clique no botão abaixo:</p>";
    echo "<a href='?create=confirm' style='display: inline-block; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Criar Administrador</a>";
    echo "<p style='margin-top: 20px; color: #dc3545;'><strong>ATENÇÃO:</strong> Por segurança, remova este arquivo após criar o administrador!</p>";
    echo "</div>";
}
?>
