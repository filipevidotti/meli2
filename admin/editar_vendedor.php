<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . ADMIN_URL . '/vendedores.php');
    exit;
}

$id = $_GET['id'];
$mensagem = '';
$tipo_mensagem = '';

// Obter dados do vendedor
$sql = "SELECT v.*, u.nome, u.email, u.status 
        FROM vendedores v 
        JOIN usuarios u ON v.usuario_id = u.id 
        WHERE v.id = ?";
$vendedor = fetchSingle($sql, [$id]);

if (!$vendedor) {
    header('Location: ' . ADMIN_URL . '/vendedores.php');
    exit;
}

// Processar formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $status = isset($_POST['status']) ? $_POST['status'] : 'ativo';
    $nome_fantasia = isset($_POST['nome_fantasia']) ? trim($_POST['nome_fantasia']) : '';
    $cnpj = isset($_POST['cnpj']) ? trim($_POST['cnpj']) : '';
    $telefone = isset($_POST['telefone']) ? trim($_POST['telefone']) : '';
    $endereco = isset($_POST['endereco']) ? trim($_POST['endereco']) : '';
    
    // Nova senha (opcional)
    $nova_senha = isset($_POST['nova_senha']) ? $_POST['nova_senha'] : '';
    $confirmar_senha = isset($_POST['confirmar_senha']) ? $_POST['confirmar_senha'] : '';
    
    // Validação básica
    if (empty($nome) || empty($email)) {
        $mensagem = "Por favor, preencha os campos obrigatórios.";
        $tipo_mensagem = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Por favor, informe um email válido.";
        $tipo_mensagem = "danger";
    } elseif (!empty($nova_senha) && $nova_senha !== $confirmar_senha) {
        $mensagem = "As senhas não coincidem.";
        $tipo_mensagem = "danger";
    } else {
        try {
            // Iniciar transação
            $pdo->beginTransaction();
            
            // Atualizar dados do usuário
            $sql = "UPDATE usuarios SET nome = ?, email = ?, status = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $email, $status, $vendedor['usuario_id']]);
            
            // Se uma nova senha foi fornecida, atualizar a senha
            if (!empty($nova_senha)) {
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET senha_hash = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$senha_hash, $vendedor['usuario_id']]);
            }
            
            // Atualizar dados do vendedor
            $sql = "UPDATE vendedores SET 
                    nome_fantasia = ?,
                    cnpj = ?,
                    telefone = ?,
                    endereco = ?
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome_fantasia, $cnpj, $telefone, $endereco, $id]);
            
            // Confirmar transação
            $pdo->commit();
            
            $mensagem = "Vendedor atualizado com sucesso!";
            $tipo_mensagem = "success";
            
            // Recarregar os dados atualizados
            $sql = "SELECT v.*, u.nome, u.email, u.status 
                    FROM vendedores v 
                    JOIN usuarios u ON v.usuario_id = u.id 
                    WHERE v.id = ?";
            $vendedor = fetchSingle($sql, [$id]);
            
        } catch (Exception $e) {
            // Reverter em caso de erro
            $pdo->rollBack();
            $mensagem = "Erro ao atualizar vendedor: " . $e->getMessage();
            $tipo_mensagem = "danger";
        }
    }
}

// Incluir cabeçalho
$page_title = 'Editar Vendedor';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Editar Vendedor</h2>
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
                <h4 class="mb-4">Dados de Acesso</h4>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($vendedor['nome']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($vendedor['email']); ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="ativo" <?php echo $vendedor['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inativo" <?php echo $vendedor['status'] === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="nova_senha" class="form-label">Nova senha (deixe em branco para manter)</label>
                        <input type="password" class="form-control" id="nova_senha" name="nova_senha">
                    </div>
                    <div class="col-md-6">
                        <label for="confirmar_senha" class="form-label">Confirmar nova senha</label>
                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha">
                    </div>
                </div>
                
                <hr>
                
                <h4 class="mb-4">Dados do Vendedor</h4>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="nome_fantasia" class="form-label">Nome Fantasia / Empresa</label>
                        <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia" value="<?php echo htmlspecialchars($vendedor['nome_fantasia'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="cnpj" class="form-label">CNPJ</label>
                        <input type="text" class="form-control" id="cnpj" name="cnpj" value="<?php echo htmlspecialchars($vendedor['cnpj'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars($vendedor['telefone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="endereco" class="form-label">Endereço</label>
                    <textarea class="form-control" id="endereco" name="endereco" rows="3"><?php echo htmlspecialchars($vendedor['endereco'] ?? ''); ?></textarea>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Atualizar Vendedor</button>
                    <a href="<?php echo ADMIN_URL; ?>/vendedores.php" class="btn btn-secondary ms-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Script para máscara de CNPJ e telefone
document.addEventListener('DOMContentLoaded', function() {
    const cnpjInput = document.getElementById('cnpj');
    if (cnpjInput) {
        cnpjInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 14) value = value.slice(0, 14);
            
            if (value.length > 12) {
                value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, "$1.$2.$3/$4-$5");
            } else if (value.length > 8) {
                value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d+)$/, "$1.$2.$3/$4");
            } else if (value.length > 5) {
                value = value.replace(/^(\d{2})(\d{3})(\d+)$/, "$1.$2.$3");
            } else if (value.length > 2) {
                value = value.replace(/^(\d{2})(\d+)$/, "$1.$2");
            }
            
            e.target.value = value;
        });
    }
    
    const telefoneInput = document.getElementById('telefone');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            
            if (value.length > 10) {
                value = value.replace(/^(\d{2})(\d{5})(\d{4})$/, "($1) $2-$3");
            } else if (value.length > 6) {
                value = value.replace(/^(\d{2})(\d{4})(\d+)$/, "($1) $2-$3");
            } else if (value.length > 2) {
                value = value.replace(/^(\d{2})(\d+)$/, "($1) $2");
            } else if (value.length > 0) {
                value = value.replace(/^(\d+)$/, "($1");
            }
            
            e.target.value = value;
        });
    }
});
</script>

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer_admin.php');
?>
