<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Processar formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $senha = isset($_POST['senha']) ? $_POST['senha'] : '';
    $confirmar_senha = isset($_POST['confirmar_senha']) ? $_POST['confirmar_senha'] : '';
    $tipo = 'vendedor'; // Sempre vendedor quando adicionado pelo admin
    
    // Validação básica
    if (empty($nome) || empty($email) || empty($senha)) {
        $mensagem = "Por favor, preencha todos os campos obrigatórios.";
        $tipo_mensagem = "danger";
    } elseif ($senha !== $confirmar_senha) {
        $mensagem = "As senhas não coincidem.";
        $tipo_mensagem = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Por favor, informe um email válido.";
        $tipo_mensagem = "danger";
    } else {
        // Verificar se o email já existe
        $sql = "SELECT COUNT(*) FROM usuarios WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $mensagem = "Este email já está cadastrado.";
            $tipo_mensagem = "danger";
        } else {
            try {
                // Iniciar transação
                $pdo->beginTransaction();
                
                // Gerar hash da senha
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                
                // Inserir na tabela usuarios
                $sql = "INSERT INTO usuarios (nome, email, senha_hash, tipo) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $email, $senha_hash, $tipo]);
                
                $usuario_id = $pdo->lastInsertId();
                
                // Inserir na tabela vendedores
                $sql = "INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$usuario_id, $nome]);
                
                // Confirmar transação
                $pdo->commit();
                
                $mensagem = "Vendedor adicionado com sucesso!";
                $tipo_mensagem = "success";
                
                // Limpar o formulário
                $nome = $email = '';
                
            } catch (Exception $e) {
                // Reverter em caso de erro
                $pdo->rollBack();
                $mensagem = "Erro ao adicionar vendedor: " . $e->getMessage();
                $tipo_mensagem = "danger";
            }
        }
    }
}

// Incluir cabeçalho
$page_title = 'Adicionar Vendedor';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Adicionar Novo Vendedor</h2>
        <a href="<?php echo ADMIN_URL; ?>/vendedores.php" class="btn btn-secondary">Voltar para Lista</a>
    </div>
    
    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensagem; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="nome" class="form-label">Nome completo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($nome ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="senha" class="form-label">Senha <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="senha" name="senha" required>
                </div>
                <div class="mb-3">
                    <label for="confirmar_senha" class="form-label">Confirmar Senha <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Adicionar Vendedor</button>
                    <a href="<?php echo ADMIN_URL; ?>/vendedores.php" class="btn btn-secondary ms-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer_admin.php');
?>
