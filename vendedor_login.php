<?php
// Reativar exibição de erros para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inicializar sessão manualmente, sem outras funcionalidades
session_start();

// Definir variáveis básicas
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/novo2'; // Ajuste conforme seu ambiente

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: ' . $base_url . '/login.php');
    exit;
}

// Se chegou aqui, o usuário está logado mas possivelmente tendo problemas
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$user_name = $_SESSION['user_name'] ?? 'Usuário';

// Tentar conexão manual com o banco
try {
    // Configurações de banco de dados - ajuste conforme necessário
    $db_host = 'localhost';
    $db_name = 'calcmeli'; // Ajuste para o nome do seu banco
    $db_user = 'root';     // Ajuste conforme necessário
    $db_pass = '';         // Ajuste conforme necessário
    
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Verificar se o vendedor existe
    $sql = "SELECT id FROM vendedores WHERE usuario_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $vendedor = $stmt->fetch();
    
    if (!$vendedor) {
        // Tentar criar vendedor se não existir
        $sql_usuario = "SELECT nome FROM usuarios WHERE id = ?";
        $stmt_usuario = $pdo->prepare($sql_usuario);
        $stmt_usuario->execute([$user_id]);
        $usuario = $stmt_usuario->fetch();
        
        if ($usuario) {
            // Verificar se a tabela existe
            $result = $pdo->query("SHOW TABLES LIKE 'vendedores'");
            if (!$result || $result->rowCount() == 0) {
                // Criar a tabela
                $sql_create = "CREATE TABLE IF NOT EXISTS vendedores (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    usuario_id INT(11) NOT NULL,
                    nome_fantasia VARCHAR(255) NOT NULL,
                    razao_social VARCHAR(255) NULL,
                    cnpj VARCHAR(20) NULL,
                    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY (usuario_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $pdo->exec($sql_create);
            }
            
            // Inserir o vendedor
            $sql_insert = "INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([$user_id, $usuario['nome']]);
            
            // Mensagem de sucesso
            $success = "Perfil de vendedor criado com sucesso!";
        } else {
            $error = "Não foi possível encontrar informações do usuário.";
        }
    } else {
        $success = "Perfil de vendedor encontrado!";
    }
} catch (PDOException $e) {
    $error = "Erro de banco de dados: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Acesso - CalcMeli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4>Diagnóstico de Acesso - CalcMeli</h4>
            </div>
            <div class="card-body">
                <h5>Olá, <?php echo htmlspecialchars($user_name); ?>!</h5>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <h5>Informações do Usuário:</h5>
                    <ul>
                        <li><strong>ID:</strong> <?php echo $user_id; ?></li>
                        <li><strong>Tipo:</strong> <?php echo $user_type; ?></li>
                        <li><strong>Nome:</strong> <?php echo htmlspecialchars($user_name); ?></li>
                    </ul>
                </div>
                
                <p>Estamos configurando seu acesso ao sistema. Por favor, tente novamente acessar o dashboard principal.</p>
                
                <div class="mt-4">
                    <a href="<?php echo $base_url; ?>/index.php" class="btn btn-primary">Tentar Acessar Dashboard</a>
                    <a href="<?php echo $base_url; ?>/logout.php" class="btn btn-secondary">Sair</a>
                    
                    <?php if ($user_type === 'admin'): ?>
                        <a href="<?php echo $base_url; ?>/diagnostico.php" class="btn btn-warning">Executar Diagnóstico Completo</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
