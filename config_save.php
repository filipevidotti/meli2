<?php
// Include header
include 'header.php';

// Include Mercado Livre API class
require_once 
__DIR__ . 
'/mercadolivre.php

';/ Initialize API
$ml_api = new MercadoLivreAPI();

// Process authorization code
$success = false;
$error_message = '';

if (isset($_GET['code'])) {
    try {
        $success = $ml_api->getAccessToken($_GET['code']);
        if (!$success) {
            $error_message = "Erro ao obter token de acesso.";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
} else if (isset($_GET['error'])) {
    $error_message = "Autorização negada: " . $_GET['error_description'];
} else {
    $error_message = "Parâmetro de código ausente.";
}
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h4>Autorização do Mercado Livre</h4>
            </div>
            <div class="card-body text-center">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                        <h4>Autorização concluída com sucesso!</h4>
                        <p>Sua conta do Mercado Livre foi conectada com sucesso.</p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle fa-3x mb-3"></i>
                        <h4>Erro na autorização</h4>
                        <p><?php echo $error_message; ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <a href="vendas.php" class="btn btn-primary">Ir para Vendas</a>
                    <a href="index.php" class="btn btn-secondary">Voltar para Home</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'footer.php';
?>