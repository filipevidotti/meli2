<?php
// Exibir todos os erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Conectar ao banco de dados
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
    echo "<p>Conexão estabelecida com sucesso!</p>";
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Verificar senhas
$users = $pdo->query("SELECT id, email, senha_hash FROM usuarios")->fetchAll();

echo "<h1>Verificação de Hash de Senhas</h1>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Email</th><th>Hash Format</th><th>Formato Correto?</th><th>Ações</th></tr>";

foreach ($users as $user) {
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['email']}</td>";
    
    // Verificar formato do hash
    $hash = $user['senha_hash'];
    $hash_info = password_get_info($hash);
    $formato_correto = ($hash_info['algo'] !== 0);
    
    echo "<td>" . ($hash ? substr($hash, 0, 30) . "..." : "VAZIO") . "</td>";
    echo "<td>" . ($formato_correto ? "<span style='color:green'>SIM</span>" : "<span style='color:red'>NÃO</span>") . "</td>";
    
    echo "<td>";
    // Se o formato não for correto, oferecer opção para redefinir
    if (!$formato_correto) {
        echo "<form method='post'>";
        echo "<input type='hidden' name='reset_password' value='{$user['id']}'>";
        echo "<input type='password' name='new_password' placeholder='Nova senha' required>";
        echo "<button type='submit'>Redefinir</button>";
        echo "</form>";
    }
    echo "</td>";
    
    echo "</tr>";
}
echo "</table>";

// Processar redefinição de senha
if (isset($_POST['reset_password']) && isset($_POST['new_password'])) {
    $user_id = $_POST['reset_password'];
    $new_password = $_POST['new_password'];
    
    // Gerar novo hash
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
        $result = $stmt->execute([$new_hash, $user_id]);
        
        if ($result) {
            echo "<p style='color:green'>Senha redefinida com sucesso!</p>";
            echo "<script>window.location.reload();</script>";
        } else {
            echo "<p style='color:red'>Erro ao redefinir senha.</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color:red'>Erro: " . $e->getMessage() . "</p>";
    }
}
?>

<hr>
<h2>Criar Novo Usuário</h2>
<form method="post">
    <input type="hidden" name="action" value="create_user">
    <div>
        <label>Nome:</label>
        <input type="text" name="nome" required>
    </div>
    <div>
        <label>Email:</label>
        <input type="email" name="email" required>
    </div>
    <div>
        <label>Senha:</label>
        <input type="password" name="senha" required>
    </div>
    <div>
        <label>Tipo:</label>
        <select name="tipo">
            <option value="vendedor">Vendedor</option>
            <option value="admin">Admin</option>
        </select>
    </div>
    <button type="submit">Criar Usuário</button>
</form>

<?php
// Processar criação de usuário
if (isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    $tipo = $_POST['tipo'];
    
    try {
        // Verificar se o email já existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo "<p style='color:red'>Email já está em uso!</p>";
        } else {
            // Inserir novo usuário
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, tipo, status) VALUES (?, ?, ?, ?, 'ativo')");
            $result = $stmt->execute([$nome, $email, $hash, $tipo]);
            
            if ($result) {
                $user_id = $pdo->lastInsertId();
                echo "<p style='color:green'>Usuário criado com sucesso! ID: $user_id</p>";
                
                // Criar vendedor automaticamente se for do tipo vendedor
                if ($tipo === 'vendedor') {
                    $stmt = $pdo->prepare("INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)");
                    $stmt->execute([$user_id, $nome]);
                    echo "<p style='color:green'>Registro de vendedor criado automaticamente.</p>";
                }
                
                echo "<script>window.location.reload();</script>";
            } else {
                echo "<p style='color:red'>Erro ao criar usuário.</p>";
            }
        }
    } catch (PDOException $e) {
        echo "<p style='color:red'>Erro: " . $e->getMessage() . "</p>";
    }
}
?>
