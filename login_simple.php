<?php
// Iniciar sessão diretamente (sem incluir init.php)
session_start();

// Configurações básicas
$base_url = 'http://www.annemacedo.com.br/novo2';

// Conexão direta com o banco
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
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Verificar se já está logado
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
        header('Location: ' . $base_url . '/admin/index.php');
        exit;
    } else {
        header('Location: ' . $base_url . '/index.php');
        exit;
    }
}

// Mensagem de erro inicialmente vazia
$erro = '';

// Processar o formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $senha = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($email) || empty($senha)) {
        $erro = "Por favor, preencha todos os campos.";
    } else {
        try {
            // Buscar usuário pelo email
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
                // Login bem-sucedido
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['user_name'] = $usuario['nome'];
                $_SESSION['user_email'] = $usuario['email'];
                $_SESSION['user_type'] = $usuario['tipo'];
                
                // Verificar se o usuário é vendedor e tem perfil configurado
                if ($usuario['tipo'] === 'vendedor') {
                    $stmt = $pdo->prepare("SELECT id FROM vendedores WHERE usuario_id = ?");
                    $stmt->execute([$usuario['id']]);
                    $vendedor = $stmt->fetch();
                    
                    if (!$vendedor) {
                        // Criar vendedor automaticamente
                        $stmt = $pdo->prepare("INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)");
                        try {
                            $stmt->execute([$usuario['id'], $usuario['nome']]);
                        } catch (PDOException $e) {
                            // Apenas log, não interromper fluxo
                        }
                    }
                }
                
                // Redirecionar baseado no tipo
                if ($usuario['tipo'] === 'admin') {
                    header('Location: ' . $base_url . '/admin/index.php');
                } else {
                    header('Location: ' . $base_url . '/index.php');
                }
                exit;
            } else {
                $erro = "Email ou senha inválidos ou usuário inativo.";
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
    <title>Login Simples - CalcMeli</title>
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
                <p>Login Simples</p>
            </div>
            
            <?php if (!empty($erro)): ?>
                <div class="alert alert-danger"><?php echo $erro; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Senha</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Entrar</button>
                </div>
            </form>
            
            <div class="text-center mt-3">
                <small>Este é um login simplificado para acesso emergencial</small>
            </div>
        </div>
    </div>
</body>
</html>
