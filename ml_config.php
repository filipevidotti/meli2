<?php
// Configuração para integração com Mercado Livre usando PKCE

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações do Mercado Livre
$ml_client_id = "2616566753532500";
$ml_client_secret = "4haTLnBN8rWyOPfDQ8N1erfTMBhZaXOz";
$ml_redirect_uri = "https://www.annemacedo.com.br/novo2/vendedor_mercadolivre.php";

// Funções para PKCE
function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateCodeVerifier(): string {
    // 64 bytes aleatórios geram uma string de 86+ caracteres após base64url
    $verifier = random_bytes(64);
    return base64UrlEncode($verifier);
}

function getMercadoLivreAuthUrl($ml_client_id, $ml_redirect_uri): string {
    // Gera e armazena o code_verifier
    $verifier = generateCodeVerifier();
    $_SESSION['ml_code_verifier'] = $verifier;
    
    // Calcula o code_challenge
    $challenge = base64UrlEncode(hash('sha256', $verifier, true));
    
    // Lista completa de escopos para garantir acesso total
    $scopes = [
        'read',           // Acesso de leitura
        'write',          // Acesso de escrita
        'offline_access'  // Crucial para obter refresh_token
    ];
    
    // Parâmetros da URL com PKCE e escopos completos
    $params = http_build_query([
        "response_type" => "code",
        "client_id" => $ml_client_id,
        "redirect_uri" => $ml_redirect_uri,
        "code_challenge" => $challenge,
        "code_challenge_method" => "S256",
        "scope" => implode(' ', $scopes)
    ]);
    
    error_log("Code verifier gerado: " . substr($verifier, 0, 10) . "...");
    error_log("Code challenge gerado: " . substr($challenge, 0, 10) . "...");
    error_log("Escopos solicitados: " . implode(' ', $scopes));
    
    return "https://auth.mercadolivre.com.br/authorization?{$params}";
}

function getMercadoLivreToken($code, $ml_client_id, $ml_client_secret, $ml_redirect_uri): array {
    // Recupera o verifier da sessão
    $verifier = $_SESSION['ml_code_verifier'] ?? '';
    if (empty($verifier)) {
        return ["error" => "code_verifier ausente em sessão"];
    }
    
    $url = "https://api.mercadolibre.com/oauth/token";
    $data = [
        "grant_type" => "authorization_code",
        "client_id" => $ml_client_id,
        "client_secret" => $ml_client_secret,
        "code" => $code,
        "redirect_uri" => $ml_redirect_uri,
        // Parâmetro obrigatório para PKCE
        "code_verifier" => $verifier
    ];
    
    // Log para debug
    error_log("Token request data: " . json_encode($data));
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        error_log("cURL error: " . $err);
        return ["error" => "cURL error: {$err}"];
    }
    curl_close($ch);
    
    // Log para debug
    error_log("ML /oauth/token HTTP Code: {$httpCode}; resposta: {$response}");
    
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Resposta inválida (não é JSON): " . $response);
        return ["error" => "Resposta inválida (não é JSON)"];
    }
    
    return $decoded;
}
?>
