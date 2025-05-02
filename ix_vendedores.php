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
    echo "<p style='color:green'>Conexão com o banco de dados: OK</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>Erro de conexão: " . $e->getMessage() . "</p>";
    exit;
}

// Processar ação
$message = '';
$action = $_POST['action'] ?? '';

if ($action === 'create_vendedor') {
    $usuario_id = intval($_POST['usuario_id']);
    
    // Verificar se o usuário existe
    $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Verificar se já existe vendedor
        $stmt = $pdo->prepare("SELECT id FROM vendedores WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $vendedor = $stmt->fetch();
        
        if (!$vendedor) {
            // Criar vendedor
            $stmt = $pdo->prepare("INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)");
            try {
                $result = $stmt->execute([$usuario_id, $user['nome']]);
                if ($result) {
                    $message = "<div class='alert alert-success'>Vendedor criado com sucesso!</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Falha ao criar vendedor.</div>";
                }
            } catch (PDOException $e) {
                $message = "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
            }
        } else {
            $message = "<div class='alert alert-warning'>Já existe um registro de vendedor para este usuário.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Usuário não encontrado!</div>";
    }
}

// Buscar usuários sem vendedor
$sql = "SELECT u.id, u.nome, u.email, u.tipo, v.id AS vendedor_id 
        FROM usuarios u 
        LEFT JOIN vendedores v ON u.id = v.usuario_id 
        ORDER BY u.id";

$users = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corrigir Vendedores - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Corrigir Estrutura de Vendedores</h1>
        
        <?php echo $message; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Usuários do Sistema</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Tipo</th>
                            <th>Status Vendedor</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['nome']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo $user['tipo']; ?></td>
                            <td>
                                <?php if ($user['vendedor_id']): ?>
                                    <span class="badge bg-success">Configurado</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Não configurado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$user['vendedor_id']): ?>
                                <form method="post" action="" style="display: inline-block;">
                                    <input type="hidden" name="action" value="create_vendedor">
                                    <input type="hidden" name="usuario_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Criar Vendedor</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="d-flex gap-2">
            <a href="login_direct.php" class="btn btn-primary">Ir para Login</a>
            <a href="check_passwords.php" class="btn btn-secondary">Verificar Senhas</a>
            <a href="test.php" class="btn btn-info">Teste do Sistema</a>
        </div>
    </div>
</body>
</html>
