<?php
// Ativar exibição de erros para este arquivo específico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir o arquivo de inicialização básico (apenas conexão)
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
    $db_connected = true;
} catch (PDOException $e) {
    $db_connected = false;
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico do Sistema</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Diagnóstico do Sistema CalcMeli</h1>
        <p>Hora atual: <?php echo date('Y-m-d H:i:s'); ?></p>
        
        <div class="card mb-4">
            <div class="card-header">Conexão com o Banco de Dados</div>
            <div class="card-body">
                <?php if ($db_connected): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i> Conexão estabelecida com sucesso!
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fa fa-times-circle"></i> Falha na conexão com o banco de dados:
                        <br>
                        <?php echo $db_error; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($db_connected): ?>
            <div class="card mb-4">
                <div class="card-header">Verificação de Tabelas</div>
                <div class="card-body">
                    <?php
                    $tables = ['usuarios', 'vendedores', 'vendas'];
                    foreach ($tables as $table) {
                        try {
                            $query = $pdo->query("SHOW TABLES LIKE '$table'");
                            $exists = $query && $query->rowCount() > 0;
                            
                            echo '<div class="mb-3">';
                            if ($exists) {
                                echo "<div class='alert alert-success'>Tabela '$table' existe.</div>";
                                
                                // Contar registros
                                $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                                echo "<p>Número de registros: $count</p>";
                                
                                // Mostrar estrutura
                                echo "<details><summary>Ver estrutura da tabela</summary>";
                                echo "<pre>";
                                $structure = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
                                print_r($structure);
                                echo "</pre>";
                                echo "</details>";
                            } else {
                                echo "<div class='alert alert-warning'>Tabela '$table' não existe!</div>";
                                
                                // Exibir SQL para criar a tabela
                                echo "<details><summary>SQL para criar tabela</summary><pre>";
                                if ($table == 'usuarios') {
                                    echo "CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    tipo ENUM('admin', 'vendedor') DEFAULT 'vendedor',
    status ENUM('ativo', 'inativo', 'pendente') DEFAULT 'ativo',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                                } elseif ($table == 'vendedores') {
                                    echo "CREATE TABLE vendedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    nome_fantasia VARCHAR(255) NOT NULL,
    razao_social VARCHAR(255),
    cnpj VARCHAR(20),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                                } elseif ($table == 'vendas') {
                                    echo "CREATE TABLE vendas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendedor_id INT NOT NULL,
    produto VARCHAR(255) NOT NULL,
    data_venda DATE NOT NULL,
    valor_venda DECIMAL(10,2) NOT NULL,
    custo_produto DECIMAL(10,2) NOT NULL,
    taxa_ml DECIMAL(10,2) NOT NULL,
    lucro DECIMAL(10,2) NOT NULL,
    margem_lucro DECIMAL(10,2) NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                                }
                                echo "</pre></details>";
                                
                                // Botão para criar a tabela (usar com cuidado!)
                                echo "<form method='post'>";
                                echo "<input type='hidden' name='create_table' value='$table'>";
                                echo "<button type='submit' class='btn btn-warning btn-sm'>Criar Tabela</button>";
                                echo "</form>";
                            }
                            echo '</div>';
                        } catch (PDOException $e) {
                            echo "<div class='alert alert-danger'>Erro ao verificar tabela '$table': " . $e->getMessage() . "</div>";
                        }
                    }
                    
                    // Processar criação de tabela se solicitado
                    if (isset($_POST['create_table'])) {
                        $table_to_create = $_POST['create_table'];
                        try {
                            if ($table_to_create == 'usuarios') {
                                $sql = "CREATE TABLE IF NOT EXISTS usuarios (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    nome VARCHAR(255) NOT NULL,
                                    email VARCHAR(255) NOT NULL UNIQUE,
                                    senha_hash VARCHAR(255) NOT NULL,
                                    tipo ENUM('admin', 'vendedor') DEFAULT 'vendedor',
                                    status ENUM('ativo', 'inativo', 'pendente') DEFAULT 'ativo',
                                    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                            } elseif ($table_to_create == 'vendedores') {
                                $sql = "CREATE TABLE IF NOT EXISTS vendedores (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    usuario_id INT NOT NULL UNIQUE,
                                    nome_fantasia VARCHAR(255) NOT NULL,
                                    razao_social VARCHAR(255),
                                    cnpj VARCHAR(20),
                                    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                            } elseif ($table_to_create == 'vendas') {
                                $sql = "CREATE TABLE IF NOT EXISTS vendas (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    vendedor_id INT NOT NULL,
                                    produto VARCHAR(255) NOT NULL,
                                    data_venda DATE NOT NULL,
                                    valor_venda DECIMAL(10,2) NOT NULL,
                                    custo_produto DECIMAL(10,2) NOT NULL,
                                    taxa_ml DECIMAL(10,2) NOT NULL,
                                    lucro DECIMAL(10,2) NOT NULL,
                                    margem_lucro DECIMAL(10,2) NOT NULL,
                                    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                            }
                            
                            $pdo->exec($sql);
                            echo "<div class='alert alert-success mt-3'>Tabela '$table_to_create' criada com sucesso!</div>";
                            echo "<script>setTimeout(function() { window.location.reload(); }, 2000);</script>";
                        } catch (PDOException $e) {
                            echo "<div class='alert alert-danger mt-3'>Erro ao criar tabela: " . $e->getMessage() . "</div>";
                        }
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">Verificação de Arquivos</div>
            <div class="card-body">
                <?php
                $required_files = [
                    'init.php', 
                    'config/conexao.php', 
                    'includes/header.php', 
                    'includes/footer.php'
                ];
                
                foreach ($required_files as $file) {
                    $path = __DIR__ . '/' . $file;
                    if (file_exists($path)) {
                        echo "<div class='alert alert-success mb-2'>✅ $file existe</div>";
                    } else {
                        echo "<div class='alert alert-danger mb-2'>❌ $file não encontrado!</div>";
                    }
                }
                ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">Criar Usuário Admin (Emergencial)</div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="admin_name" class="form-label">Nome</label>
                                <input type="text" class="form-control" id="admin_name" name="admin_name" value="Administrador" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="admin_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="admin_email" name="admin_email" value="admin@exemplo.com" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="admin_password" class="form-label">Senha</label>
                                <input type="password" class="form-control" id="admin_password" name="admin_password" value="admin123" required>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="create_admin" class="btn btn-warning">Criar Admin Emergencial</button>
                </form>
                
                <?php
                if (isset($_POST['create_admin']) && $db_connected) {
                    $name = $_POST['admin_name'];
                    $email = $_POST['admin_email'];
                    $password = $_POST['admin_password'];
                    
                    try {
                        // Verificar se o usuário já existe
                        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                        $stmt->execute([$email]);
                        $user = $stmt->fetch();
                        
                        if ($user) {
                            echo "<div class='alert alert-warning mt-3'>Usuário com este email já existe!</div>";
                        } else {
                            // Criar o usuário admin
                            $sql = "INSERT INTO usuarios (nome, email, senha_hash, tipo, status) VALUES (?, ?, ?, 'admin', 'ativo')";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                $name,
                                $email,
                                password_hash($password, PASSWORD_DEFAULT)
                            ]);
                            
                            echo "<div class='alert alert-success mt-3'>Usuário administrador criado com sucesso!</div>";
                        }
                    } catch (PDOException $e) {
                        echo "<div class='alert alert-danger mt-3'>Erro ao criar usuário: " . $e->getMessage() . "</div>";
                    }
                }
                ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">Informações do Sistema</div>
            <div class="card-body">
                <ul>
                    <li><strong>PHP Version:</strong> <?php echo phpversion(); ?></li>
                    <li><strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></li>
                    <li><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?></li>
                    <li><strong>Current Directory:</strong> <?php echo __DIR__; ?></li>
                </ul>
            </div>
        </div>
        
        <div class="mt-4 mb-5 text-center">
            <a href="login.php" class="btn btn-primary">Ir para Login</a>
            <a href="index.php" class="btn btn-secondary">Ir para Dashboard</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
