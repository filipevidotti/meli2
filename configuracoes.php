<?php
// configuracoes.php

// Inclui funções e protege a página
require_once 'functions/functions.php';
protegerPagina();

$page_title = "Configurações";
$success_message = null;
$error_message = null;

// Obter ID do usuário logado
$usuario_id = obterUsuarioIdLogado();

// Processar formulário de salvamento
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $configs_to_save = [
            'taxa_imposto' => $_POST['taxa_imposto'] ?? null,
            'custo_publicidade' => $_POST['custo_publicidade'] ?? null,
            'outras_despesas' => $_POST['outras_despesas'] ?? null,
            'ml_client_id' => $_POST['ml_client_id'] ?? null,
            'ml_client_secret' => $_POST['ml_client_secret'] ?? null,
            'ml_redirect_uri' => $_POST['ml_redirect_uri'] ?? null,
        ];

        $all_saved = true;
        foreach ($configs_to_save as $chave => $valor) {
            // Permite salvar string vazia, mas não null se o POST falhar
            if ($valor !== null) { 
                if (!salvarConfiguracao($chave, $valor)) {
                    $all_saved = false;
                    // A função salvarConfiguracao já loga o erro específico
                    error_log("Erro ao salvar configuração '{$chave}' para usuário {$usuario_id}");
                }
            }
        }

        if ($all_saved) {
            $_SESSION['message'] = "Configurações salvas com sucesso!";
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['message'] = "Erro ao salvar uma ou mais configurações. Verifique os logs.";
            $_SESSION['msg_type'] = 'danger';
        }

    } catch (Exception $e) {
        error_log("Erro geral ao salvar configurações para usuário {$usuario_id}: " . $e->getMessage());
        $_SESSION['message'] = "Ocorreu um erro inesperado ao salvar as configurações.";
        $_SESSION['msg_type'] = 'danger';
    }
    
    // Redireciona para evitar reenvio do formulário
    header("Location: configuracoes.php");
    exit;
}

// Carregar configurações atuais do usuário
$config = obterTodasConfiguracoes(); // Função já adaptada para multi-usuário

// Valores padrão se não existirem
$defaults = [
    'taxa_imposto' => 9.0,
    'custo_publicidade' => 5.0,
    'outras_despesas' => 0.0,
    'ml_client_id' => '',
    'ml_client_secret' => '',
    'ml_redirect_uri' => '', // Sugerir um padrão pode ser útil
];

foreach ($defaults as $key => $value) {
    if (!isset($config[$key])) {
        $config[$key] = $value;
    }
}

// Inclui o cabeçalho
include_once __DIR__ . 
'/header.php'
; 
?>

<div class="container mt-4">
    <h2><i class="fas fa-cog"></i> <?php echo $page_title; ?></h2>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
        <?php 
            echo $_SESSION['message']; 
            unset($_SESSION['message']);
            unset($_SESSION['msg_type']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <form action="configuracoes.php" method="post">
        <div class="row">
            <!-- Coluna Configurações Gerais -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Configurações Gerais</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="taxa_imposto" class="form-label">Taxa de Imposto (%)</label>
                            <input type="number" class="form-control" id="taxa_imposto" name="taxa_imposto" value="<?php echo htmlspecialchars($config['taxa_imposto']); ?>" step="0.01" required>
                            <small class="text-muted">Taxa de imposto que será descontada do lucro.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="custo_publicidade" class="form-label">Custo de Publicidade (%)</label>
                            <input type="number" class="form-control" id="custo_publicidade" name="custo_publicidade" value="<?php echo htmlspecialchars($config['custo_publicidade']); ?>" step="0.01" required>
                            <small class="text-muted">Percentual do valor de venda destinado à publicidade.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="outras_despesas" class="form-label">Outras Despesas Fixas (R$)</label>
                            <input type="number" class="form-control" id="outras_despesas" name="outras_despesas" value="<?php echo htmlspecialchars($config['outras_despesas']); ?>" step="0.01" required>
                            <small class="text-muted">Valor fixo de outras despesas por produto/venda.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna Configurações API Mercado Livre -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Configurações API Mercado Livre</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="ml_client_id" class="form-label">Client ID</label>
                            <input type="text" class="form-control" id="ml_client_id" name="ml_client_id" value="<?php echo htmlspecialchars($config['ml_client_id']); ?>" required>
                            <small class="text-muted">Seu App ID no Mercado Livre.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ml_client_secret" class="form-label">Chave Secreta (Client Secret)</label>
                            <input type="password" class="form-control" id="ml_client_secret" name="ml_client_secret" value="<?php echo htmlspecialchars($config['ml_client_secret']); ?>" required>
                             <small class="text-muted">Sua chave secreta no Mercado Livre.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ml_redirect_uri" class="form-label">URI de Redirecionamento</label>
                            <input type="url" class="form-control" id="ml_redirect_uri" name="ml_redirect_uri" value="<?php echo htmlspecialchars($config['ml_redirect_uri']); ?>" required>
                            <small class="text-muted">URL de Callback configurado na sua aplicação ML (ex: https://seu-site.com/calculadora/callback.php).</small>
                        </div>
                         <div class="mb-3">
                             <a href="api_ml.php" class="btn btn-primary">Verificar/Conectar ao Mercado Livre</a>
                             <small class="d-block mt-1">Salve as configurações antes de conectar.</small>
                         </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-12 text-center">
                 <button type="submit" class="btn btn-success btn-lg">Salvar Todas as Configurações</button>
            </div>
        </div>
    </form>
</div>

<?php
// Inclui o rodapé
include_once __DIR__ . 
'/footer.php'
; 
?>
