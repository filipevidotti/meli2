<?php
// Configuração básica - mostrar erros para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sessão
session_start();

// Definições básicas
$base_url = 'http://www.annemacedo.com.br/novo2';

// Verificar logout
if (isset($_GET['logout'])) {
    // Destruir sessão
    session_destroy();
    // Redirecionar para login após logout
    header("Location: " . $base_url . "/login.php");
    exit;
}

// Se já estiver logado, redirecionar
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        header('Location: ' . $base_url . '/admin/index.php');
        exit;
    } else {
        // Vendedor vai para a interface específica de vendedor
        header('Location: ' . $base_url . '/vendedor_index.php');
        exit;
    }
}

// Conectar ao banco de dados diretamente
$db_host = 'mysql.annemacedo.com.br';
$db_name = 'annemacedo02';
$db_user = 'annemacedo02';
$db_pass = 'Vingador13Anne';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user, 
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    // Teste básico para verificar a conexão
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    echo "Erro na conexão com o banco de dados: " . $e->getMessage();
    exit;
}

// Variáveis
$error = '';
$success = '';

// Verificar login
if (isset($_POST['login_submit'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Por favor, preencha todos os campos.";
    } else {
        try {
            // Buscar usuário
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['senha_hash'])) {
                // Login bem-sucedido
                
                // Verificar se o usuário tem um registro de vendedor (se não for admin)
                if ($user['tipo'] !== 'admin') {
                    // Verificar se existe vendedor
                    $stmt = $pdo->prepare("SELECT id FROM vendedores WHERE usuario_id = ?");
                    $stmt->execute([$user['id']]);
                    $vendedor = $stmt->fetch();
                    
                    if (!$vendedor) {
                        // Criar vendedor automaticamente
                        $stmt = $pdo->prepare("INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)");
                        $stmt->execute([$user['id'], $user['nome']]);
                    }
                }
                
                // Armazenar dados na sessão
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nome'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['tipo'];
                
                $success = "Login bem-sucedido! Redirecionando...";
                
                // Redirecionar em JavaScript após 2 segundos
                echo "<script>
                    setTimeout(function() {
                        window.location.href = '" . 
                        ($user['tipo'] === 'admin' ? 
                         $base_url . '/admin/index.php' : 
                         $base_url . '/vendedor_index.php') . "';
                    }, 2000);
                </script>";
            } else {
                $error = "Email ou senha incorretos.";
            }
        } catch (PDOException $e) {
            $error = "Erro ao processar login: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Emergencial - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding: 20px;
        }
        .login-container {
            max-width: 500px;
            margin: 50px auto;
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
        .status {
            margin-top: 20px;
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo">
                <h2>CalcMeli</h2>
                <p>Login Emergencial</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Senha</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" name="login_submit" class="btn btn-primary">Entrar</button>
                </div>
            </form>
            
            <div class="status mt-4">
                <h5>Status do Sistema</h5>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Conexão com Banco de Dados
                        <span class="badge bg-success">OK</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Sessão PHP
                        <span class="badge bg-success">OK</span>
                    </li>
                </ul>
            </div>
            
            <div class="d-flex justify-content-between mt-3">
                <a href="test.php" class="btn btn-sm btn-outline-secondary">Diagnóstico</a>
                <a href="fix_vendedores.php" class="btn btn-sm btn-outline-secondary">Verificar Vendedores</a>
                <a href="login.php" class="btn btn-sm btn-outline-secondary">Login Normal</a>
            </div>
        </div>
    </div>
</body>
</html>
