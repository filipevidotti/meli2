<?php
if (isset($pdo) && isset($usuario_id)) {
    $token_result = getValidToken($pdo, $usuario_id);
    if ($token_result['success']) {
        $ml_token = $token_result['token'];
        $token_valido = true;
        if ($token_result['renewed']) {
            error_log("Token renovado automaticamente durante o carregamento da página");
        }
    } else if ($token_result['needs_auth']) {
        $_SESSION['mensagem'] = "Sua conexão com o Mercado Livre expirou. Por favor, reconecte.";
        $_SESSION['tipo_mensagem'] = "warning";
        header("Location: {$base_url}/vendedor_mercadolivre.php");
        exit;
    }
}

?>