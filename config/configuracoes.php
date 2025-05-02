<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Processar formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ml_client_id = isset($_POST['ml_client_id']) ? trim($_POST['ml_client_id']) : '';
    $ml_client_secret = isset($_POST['ml_client_secret']) ? trim($_POST['ml_client_secret']) : '';
    $ml_redirect_url = isset($_POST['ml_redirect_url']) ? trim($_POST['ml_redirect_url']) : '';
    $custo_adicional_padrao = isset($_POST['custo_adicional_padrao']) ? floatval($_POST['custo_adicional_padrao']) : 5;
    $margem_lucro_minima = isset($_POST['margem_lucro_minima']) ? floatval($_POST['margem_lucro_minima']) : 15;
    
    try {
        // Verificar se a tabela existe
        $check = $pdo->query("SHOW TABLES LIKE 'configuracoes'");
        if ($check->rowCount() == 0) {
            // Criar a tabela se não existir
            $pdo->exec("CREATE TABLE IF NOT EXISTS `configuracoes` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `chave` varchar(100) NOT NULL,
                `valor` text NULL,
                `data_criacao` datetime DEFAULT current_timestamp(),
                `data_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `chave_unique` (`chave`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Salvar as configurações
        $configs = [
            'ml_client_id' => $ml_client_id,
            'ml_client_secret' => $ml_client_secret,
            'ml_redirect_url' => $ml_redirect_url,
            'custo_adicional_padrao' => $custo_adicional_padrao,
            'margem_lucro_minima' => $margem_lucro_minima
        ];
        
        foreach ($configs as $chave => $valor) {
            // Verificar se já existe essa chave
            $sql = "SELECT COUNT(*) FROM configuracoes WHERE chave = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$chave]);
            $existe = $stmt->fetchColumn();
            
            if ($existe) {
                // Atualizar configuração existente
                $sql = "UPDATE configuracoes SET valor = ? WHERE chave = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$valor, $chave]);
            } else {
                // Inserir nova configuração
                $sql = "INSERT INTO configuracoes (chave, valor) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$chave, $valor]);
            }
        }
        
        $mensagem = "Configurações salvas com sucesso!";
        $tipo_mensagem = "success";
    } catch (Exception $e) {
        $mensagem = "Erro ao salvar configurações: " . $e->getMessage();
        $tipo_mensagem = "danger";
    }
}

// Carregar configurações atuais
$configs = [
    'ml_client_id' => '',
    'ml_client_secret' => '',
    'ml_redirect_url' => '',
    'custo_adicional_padrao' => '5',
    'margem_lucro_minima' => '15'
];

try {
    $sql = "SELECT chave, valor FROM configuracoes";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $configs[$row['chave']] = $row['valor'];
    }
} catch (Exception $e) {
    // Silenciar erro - usará os valores padrão
}

// Incluir cabeçalho
$page_title = 'Configurações do Sistema';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Configurações do Sistema</h2>
    </div>
    
    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensagem; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5>Integração com Mercado Livre</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="ml_client_id" class="form-label">Client ID</label>
                        <input type="text" class="form-control" id="ml_client_id" name="ml_client_id" 
                               value="<?php echo htmlspecialchars($configs['ml_client_id']); ?>">
                        <div class="form-text">ID da aplicação no Mercado Livre</div>
                    </div>
                    <div class="col-md-6">
                        <label for="ml_client_secret" class="form-label">Client Secret</label>
                        <input type="text" class="form-control" id="ml_client_secret" name="ml_client_secret" 
                               value="<?php echo htmlspecialchars($configs['ml_client_secret']); ?>">
                        <div class="form-text">Chave secreta da aplicação no Mercado Livre</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="ml_redirect_url" class="form-label">URL de Redirecionamento</label>
                    <input type="text" class="form-control" id="ml_redirect_url" name="ml_redirect_url" 
                           value="<?php echo htmlspecialchars($configs['ml_redirect_url']); ?>">
                    <div class="form-text">URL de callback para autenticação OAuth</div>
                </div>
                
                <hr>
                
                <h5 class="mb-3">Configurações de Cálculo</h5>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="custo_adicional_padrao" class="form-label">Custos Adicionais Padrão (%)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="custo_adicional_padrao" name="custo_adicional_padrao" 
                               value="<?php echo htmlspecialchars($configs['custo_adicional_padrao']); ?>">
                        <div class="form-text">Percentual padrão de custos adicionais (embalagem, frete, etc.)</div>
                    </div>
                    <div class="col-md-6">
                        <label for="margem_lucro_minima" class="form-label">Margem de Lucro Mínima (%)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="margem_lucro_minima" name="margem_lucro_minima" 
                               value="<?php echo htmlspecialchars($configs['margem_lucro_minima']); ?>">
                        <div class="form-text">Margem de lucro mínima aceitável</div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer_admin.php');
?>
