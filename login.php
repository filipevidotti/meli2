<?php
// Incluir o arquivo de inicialização
require_once('init.php');

// Se já estiver logado, redirecionar conforme o tipo de usuário
if (isLoggedIn()) {
    redirectBasedOnUserType();
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
        // Buscar usuário pelo email
        $sql = "SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'";
        $usuario = fetchSingle($sql, [$email]);

        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            // Login bem-sucedido
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_name'] = $usuario['nome'];
            $_SESSION['user_email'] = $usuario['email'];
            $_SESSION['user_type'] = $usuario['tipo'];
            
            // Atualizar data de último acesso (opcional)
            $sql = "UPDATE usuarios SET data_atualizacao = NOW() WHERE id = ?";
            executeQuery($sql, [$usuario['id']]);

            // Redireciona baseado no tipo de usuário
            redirectBasedOnUserType();
        } else {
            $erro = "Email ou senha inválidos ou usuário inativo.";
        }
    }
}
?>
<!DOCTYPE html>
<!-- Resto do código HTML -->

<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CalcMeli</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
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
        .btn-outline-primary {
            color: #ff9a00;
            border-color: #ff9a00;
        }
        .btn-outline-primary:hover {
            background-color: #ff9a00;
            border-color: #ff9a00;
        }
        .btn-warning {
            background-color: #fff159;
            border-color: #fff159;
            color: #333;
        }
        .btn-warning:hover {
            background-color: #e6d950;
            border-color: #e6d950;
            color: #333;
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
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['info'])): ?>
                <div class="alert alert-info"><?php echo $_SESSION['info']; unset($_SESSION['info']); ?></div>
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
                <a href="forgot_password.php">Esqueceu a senha?</a>
            </div>
            
            <hr class="my-4">
            
            <div class="text-center">
                <p>Ainda não tem uma conta?</p>
                <a href="register.php" class="btn btn-outline-primary">Cadastre-se</a>
                <p class="mt-3">ou</p>
                <a href="auth_mercadolivre.php" class="btn btn-warning">
                    <img src="<?php echo BASE_URL; ?>/assets/img/ml-logo.png" alt="Mercado Livre" style="height: 20px; margin-right: 8px;">
                    Entrar com Mercado Livre
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
