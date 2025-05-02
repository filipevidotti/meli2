<?php
// Ativar exibição de erros para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sessão
session_start();

// Conectar diretamente ao banco
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
} catch (PDOException $e) {
    echo "<div style='color:red; padding:20px; background:#ffeeee; border:1px solid #ff0000; margin:20px;'>";
    echo "<h3>Erro de Conexão</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
    exit;
}

// Verificar se já está logado
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'] ?? '';
    
    // Buscar informações do usuário para confirmar
    $stmt = $pdo->prepare("SELECT id, tipo FROM usuarios WHERE id = ? AND status = 'ativo'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $url = 'http://www.annemacedo.com.br/novo2';
        if ($user['tipo'] === 'admin') {
            header("Location: $url/admin/index.php");
        } else {
            header("Location: $url/index.php");
        }
        exit;
    } else {
        // Sessão inválida, destruir
        session_destroy();
    }
}

// Variáveis para o formulário
$erro = '';
$email = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $erro = "Por favor, preencha todos os campos.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                if (password_verify($password, $user['senha_hash'])) {
                    // Login correto
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['nome'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_type'] = $user['tipo'];
                    
                    $url = 'http://www.annemacedo.com.br/novo2';
                    if ($user['tipo'] === 'admin') {
                        header("Location: $url/admin/index.php");
                    } else {
                        header("Location: $url/index.php");
                    }
                    exit;
                } else {
                    $erro = "Senha incorreta.";
                }
            } else {
                $erro = "E-mail não encontrado ou conta inativa.";
            }
        } catch (PDOException $e) {
            $erro = "Erro ao processar login: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h2 {
            color: #ff9a00;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #ff9a00;
            border-color: #ff9a00;
        }
        .btn-primary:hover {
            background-color: #e08a00;
            border-color: #e08a00;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo">
                <h2>CalcMeli</h2>
                <p>Sistema de Cálculo para Mercado Livre</p>
            </div>
            
            <?php if (!empty($erro)): ?>
                <div class="alert alert-danger"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Senha</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Entrar</button>
                </div>
            </form>
            
            <div class="text-center mt-4">
                <a href="check_passwords.php" class="btn btn-sm btn-outline-secondary">Verificar Contas</a>
            </div>
        </div>
    </div>
</body>
</html>
