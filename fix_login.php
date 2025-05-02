<?php
// Exibir erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p>Conexão estabelecida com sucesso!</p>";
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Verificar parâmetros
$action = $_GET['action'] ?? '';
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Iniciar sessão
session_start();

if ($action === 'logout') {
    // Destruir sessão
    session_destroy();
    echo "<p>Sessão encerrada!</p>";
    echo "<p><a href='fix_login.php'>Voltar</a></p>";
    exit;
}

// Exibir status da sessão
echo "<h1>Fix Login - CalcMeli</h1>";
echo "<h2>Status da Sessão</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Tratar ações específicas
if ($action === 'fix_vendedor' && $user_id > 0) {
    // Verificar se usuário existe
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "<p>Erro: Usuário não encontrado!</p>";
    } else {
        // Verificar se já existe vendedor para este usuário
        $stmt = $pdo->prepare("SELECT * FROM vendedores WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        $vendedor = $stmt->fetch();
        
        if ($vendedor) {
            echo "<p>Vendedor já existe para este usuário:</p>";
            echo "<pre>";
            print_r($vendedor);
            echo "</pre>";
        } else {
            // Criar vendedor
            try {
                $stmt = $pdo->prepare("INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)");
                $stmt->execute([$user_id, $user['nome']]);
                
                echo "<p style='color:green'>Vendedor criado com sucesso!</p>";
                
                // Buscar o vendedor criado
                $stmt = $pdo->prepare("SELECT * FROM vendedores WHERE usuario_id = ?");
                $stmt->execute([$user_id]);
                $vendedor = $stmt->fetch();
                
                echo "<pre>";
                print_r($vendedor);
                echo "</pre>";
            } catch (PDOException $e) {
                echo "<p>Erro ao criar vendedor: " . $e->getMessage() . "</p>";
            }
        }
    }
}

// Listar usuários
echo "<h2>Usuários no Sistema</h2>";
$users = $pdo->query("SELECT * FROM usuarios ORDER BY id")->fetchAll();
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Tipo</th><th>Status</th><th>Ações</th></tr>";

foreach ($users as $user) {
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['nome']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td>{$user['tipo']}</td>";
    echo "<td>{$user['status']}</td>";
    echo "<td>";
    
    // Verificar se tem vendedor
    $stmt = $pdo->prepare("SELECT id FROM vendedores WHERE usuario_id = ?");
    $stmt->execute([$user['id']]);
    $vendedor = $stmt->fetch();
    
    if ($vendedor) {
        echo "<span style='color:green'>✓ Vendedor configurado (ID: {$vendedor['id']})</span>";
    } else {
        echo "<a href='fix_login.php?action=fix_vendedor&user_id={$user['id']}' style='color:red'>⚠️ Criar vendedor</a>";
    }
    
    echo "</td>";
    echo "</tr>";
}
echo "</table>";

// Links úteis
echo "<h2>Links Úteis</h2>";
echo "<ul>";
echo "<li><a href='fix_login.php?action=logout'>Encerrar Sessão</a></li>";
echo "<li><a href='diagnostico.php'>Executar Diagnóstico</a></li>";
echo "<li><a href='login.php'>Página de Login</a></li>";
echo "<li><a href='index.php'>Dashboard</a></li>";
echo "</ul>";
?>
