<?php
// Incluir o arquivo de inicialização
require_once('init.php');

// Proteção da página - exige login
requireLogin();

// Verificar se é vendedor
if (isAdmin() && !isset($_GET['user_id'])) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

// Obter ID do usuário logado
$usuario_id = isset($_GET['user_id']) && isAdmin() ? intval($_GET['user_id']) : $_SESSION['user_id'];
$nome_usuario = $_SESSION['user_name'] ?? 'Usuário';
$email_usuario = $_SESSION['user_email'] ?? '';

// Verificar se o registro de vendedor já existe
$sql = "SELECT id FROM vendedores WHERE usuario_id = ?";
$vendedor_existente = fetchSingle($sql, [$usuario_id]);

if ($vendedor_existente) {
    // Se o vendedor já existe e não é admin a forçar a configuração, redirecionar
    if (!isAdmin() || !isset($_GET['force'])) {
        $_SESSION['info'] = "Seu perfil de vendedor já está configurado.";
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Processar o formulário quando enviado
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_fantasia = isset($_POST['nome_fantasia']) ? trim($_POST['nome_fantasia']) : '';
    $razao_social = isset($_POST['razao_social']) ? trim($_POST['razao_social']) : '';
    $cnpj = isset($_POST['cnpj']) ? trim($_POST['cnpj']) : '';
    
    // Validar dados
    if (empty($nome_fantasia)) {
        $mensagem = "O nome fantasia é obrigatório.";
        $tipo_mensagem = "danger";
    } else {
        try {
            if ($vendedor_existente) {
                // Atualizar vendedor existente
                $sql = "UPDATE vendedores SET nome_fantasia = ?, razao_social = ?, cnpj = ? WHERE usuario_id = ?";
                executeQuery($sql, [$nome_fantasia, $razao_social, $cnpj, $usuario_id]);
                $mensagem = "Perfil atualizado com sucesso!";
            } else {
                // Inserir novo vendedor
                $sql = "INSERT INTO vendedores (usuario_id, nome_fantasia, razao_social, cnpj) VALUES (?, ?, ?, ?)";
                executeQuery($sql, [$usuario_id, $nome_fantasia, $razao_social, $cnpj]);
                $mensagem = "Perfil configurado com sucesso!";
            }
            
            $tipo_mensagem = "success";
            
            // Redirecionar após alguns segundos
            header("Refresh: 2; URL=" . BASE_URL . "/index.php?welcome=1");
        } catch (PDOException $e) {
            $mensagem = "Erro ao configurar perfil: " . $e->getMessage();
            $tipo_mensagem = "danger";
            error_log("Erro na configuração do vendedor: " . $e->getMessage());
        }
    }
}

// Buscar dados do vendedor para edição
$vendedor = [];
if ($vendedor_existente) {
    $sql = "SELECT * FROM vendedores WHERE usuario_id = ?";
    $vendedor = fetchSingle($sql, [$usuario_id]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração do Vendedor - CalcMeli</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .config-container {
            max-width: 800px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="config-container">
            <div class="logo">
                <h2>CalcMeli</h2>
                <p><?php echo $vendedor_existente ? 'Atualização' : 'Configuração Inicial'; ?> do Vendedor</p>
            </div>
            
            <?php if (!empty($mensagem)): ?>
                <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensagem; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <?php echo $vendedor_existente ? 'Atualize seu perfil de vendedor conforme necessário.' : 'Para começar a usar o sistema, precisamos configurar seu perfil de vendedor.'; ?>
            </div>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="nome_fantasia" class="form-label">Nome Fantasia / Nome da Loja <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia" value="<?php echo isset($_POST['nome_fantasia']) ? htmlspecialchars($_POST['nome_fantasia']) : htmlspecialchars($vendedor['nome_fantasia'] ?? $nome_usuario); ?>" required>
                    <div class="form-text">Este será o nome exibido no sistema</div>
                </div>
                
                <div class="mb-3">
                    <label for="razao_social" class="form-label">Razão Social</label>
                    <input type="text" class="form-control" id="razao_social" name="razao_social" value="<?php echo isset($_POST['razao_social']) ? htmlspecialchars($_POST['razao_social']) : htmlspecialchars($vendedor['razao_social'] ?? ''); ?>">
                    <div class="form-text">Opcional - para fins de registro</div>
                </div>
                
                <div class="mb-3">
                    <label for="cnpj" class="form-label">CNPJ</label>
                    <input type="text" class="form-control" id="cnpj" name="cnpj" value="<?php echo isset($_POST['cnpj']) ? htmlspecialchars($_POST['cnpj']) : htmlspecialchars($vendedor['cnpj'] ?? ''); ?>">
                    <div class="form-text">Opcional - apenas para fins de registro</div>
                </div>
                
                <hr class="my-4">
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><?php echo $vendedor_existente ? 'Atualizar Perfil' : 'Configurar Perfil e Continuar'; ?></button>
                    
                    <?php if ($vendedor_existente): ?>
                    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-outline-secondary">Cancelar e Voltar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        // Script para formatar CNPJ
        $(document).ready(function() {
            $('#cnpj').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value.length > 14) value = value.substr(0, 14);
                
                if (value.length > 12) value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2}).*/, "$1.$2.$3/$4-$5");
                else if (value.length > 8) value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d*)/, "$1.$2.$3/$4");
                else if (value.length > 5) value = value.replace(/^(\d{2})(\d{3})(\d*)/, "$1.$2.$3");
                else if (value.length > 2) value = value.replace(/^(\d{2})(\d*)/, "$1.$2");
                
                $(this).val(value);
            });
        });
    </script>
</body>
</html>
