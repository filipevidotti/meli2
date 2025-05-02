<?php
// Include Mercado Livre API class
require_once __DIR__ . 
'/mercadolivre.php'
;

// --- Get Configuration ---
// We need the config to initialize the API correctly
require_once __DIR__ . 
'/config/database.php'
;
require_once __DIR__ . 
'/functions/functions.php'
;
$config = obterConfiguracoes();

// Initialize API with config
try {
    $ml_api = new MercadoLivreAPI($config);
    // Redirect to Mercado Livre authorization page
    header(
'Location: ' . $ml_api->getAuthorizationUrl());
    exit;
} catch (Exception $e) {
    // Handle error if API cannot be initialized (e.g., missing config)
    error_log("Auth Mercado Livre Error: " . $e->getMessage());
    // Redirect to an error page or display a message
    // For simplicity, redirecting to api_ml page with an error
    header(
'Location: api_ml.php?error=config_missing&detail=' . urlencode($e->getMessage()));
    exit;
}
?>
