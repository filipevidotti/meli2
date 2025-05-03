<?php
// Configuração para integração com Mercado Livre usando PKCE e refresh token

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
    
    // Lista completa de escopos - CRUCIAL incluir offline_access para renovação automática
    $scopes = [
        'read',            // Acesso de leitura
        'write',           // Acesso de escrita
        'offline_access'   // Crucial para obter refresh_token - permite renovação sem reautenticação
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

// NOVA FUNÇÃO: Renovar token usando refresh_token
function refreshMercadoLivreToken($refresh_token, $ml_client_id, $ml_client_secret): array {
    $url = "https://api.mercadolibre.com/oauth/token";
    $data = [
        "grant_type" => "refresh_token",
        "client_id" => $ml_client_id,
        "client_secret" => $ml_client_secret,
        "refresh_token" => $refresh_token
    ];
    
    error_log("Solicitando renovação de token com refresh_token: " . substr($refresh_token, 0, 10) . "...");
    
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
        error_log("cURL error na renovação: " . $err);
        return ["error" => "cURL error: {$err}"];
    }
    curl_close($ch);
    
    error_log("ML refresh token HTTP Code: {$httpCode}; resposta: {$response}");
    
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Resposta inválida na renovação (não é JSON): " . $response);
        return ["error" => "Resposta inválida (não é JSON)"];
    }
    
    return $decoded;
}

// NOVA FUNÇÃO: Verificar se o token atual está válido e, se não estiver, renovar automaticamente
function checkAndRefreshToken($pdo, $usuario_id, $ml_client_id, $ml_client_secret) {
    try {
        // Buscar token atual
        $stmt = $pdo->prepare("
            SELECT id, access_token, refresh_token, data_expiracao
            FROM mercadolivre_tokens 
            WHERE usuario_id = ? AND revogado = 0
            ORDER BY data_expiracao DESC 
            LIMIT 1
        ");
        $stmt->execute([$usuario_id]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$token) {
            error_log("Nenhum token encontrado para o usuário {$usuario_id}");
            return false;
        }
        
        // Verificar expiração
        $data_expiracao = new DateTime($token['data_expiracao']);
        $agora = new DateTime();
        $is_valid = $agora < $data_expiracao;
        
        // Se token está a 1 hora ou menos de expirar e temos refresh_token, renovar
        $expira_em_breve = $data_expiracao->getTimestamp() - $agora->getTimestamp() <= 3600; // 1 hora
        
        if (!$is_valid || $expira_em_breve) {
            if (empty($token['refresh_token'])) {
                error_log("Token expirado/prestes a expirar e não há refresh_token disponível");
                return false;
            }
            
            error_log("Token " . ($is_valid ? "expirará em breve" : "expirou") . " - tentando renovar");
            
            // Renovar token
            $refresh_result = refreshMercadoLivreToken(
                $token['refresh_token'], 
                $ml_client_id, 
                $ml_client_secret
            );
            
            if (isset($refresh_result['error'])) {
                error_log("Erro ao renovar token: " . $refresh_result['error']);
                return false;
            }
            
            if (!isset($refresh_result['access_token'])) {
                error_log("Renovação falhou: token não retornado");
                return false;
            }
            
            $access_token = $refresh_result['access_token'];
            $refresh_token = $refresh_result['refresh_token'] ?? '';
            $expires_in = $refresh_result['expires_in'] ?? 21600;
            
            // Calcular nova data de expiração
            $agora = new DateTime();
            $nova_expiracao = clone $agora;
            $nova_expiracao->add(new DateInterval("PT{$expires_in}S"));
            
            // Marcar token antigo como revogado
            $stmt = $pdo->prepare("
                UPDATE mercadolivre_tokens
                SET revogado = 1, data_revogacao = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$token['id']]);
            
            // Salvar novo token
            $stmt = $pdo->prepare("
                INSERT INTO mercadolivre_tokens
                (usuario_id, access_token, refresh_token, data_criacao, data_expiracao, revogado)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([
                $usuario_id,
                $access_token,
                $refresh_token,
                $agora->format('Y-m-d H:i:s'),
                $nova_expiracao->format('Y-m-d H:i:s')
            ]);
            
            error_log("Token renovado com sucesso! Nova expiração: " . $nova_expiracao->format('Y-m-d H:i:s'));
            return $access_token;
        }
        
        return $token['access_token'];
    } catch (PDOException $e) {
        error_log("Erro ao verificar/renovar token: " . $e->getMessage());
        return false;
    }
}

// NOVA FUNÇÃO: Obter token válido para o usuário (renova automaticamente se necessário)
function getValidAccessToken($pdo, $usuario_id, $ml_client_id, $ml_client_secret) {
    return checkAndRefreshToken($pdo, $usuario_id, $ml_client_id, $ml_client_secret);
}
?>
