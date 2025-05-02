<?php
// Incluir o arquivo de inicialização
require_once('init.php');

// Verificar se recebeu o código de autorização
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Preparar requisição para obter o token de acesso
    $url = 'https://api.mercadolibre.com/oauth/token';
    $data = [
        'grant_type' => 'authorization_code',
        'client_id' => $ml_client_id,
        'client_secret' => $ml_client_secret,
        'code' => $code,
        'redirect_uri' => $ml_redirect_url
    ];
    
    // Inicializar cURL
    $ch = curl_init($url);
    
    // Configurar a requisição
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para ambiente de desenvolvimento
    
    // Executar a requisição
    $response = curl_exec($ch);
    
    // Verificar erros
    if (curl_errno($ch)) {
        $_SESSION['ml_error'] = 'Erro na requisição: ' . curl_error($ch);
        header('Location: index.php');
        exit;
    }
    
    curl_close($ch);
    
    // Decodificar a resposta
    $token_data = json_decode($response, true);
    
    if (isset($token_data['access_token'])) {
        // Salvar os tokens na sessão
        $_SESSION['ml_access_token'] = $token_data['access_token'];
        $_SESSION['ml_refresh_token'] = $token_data['refresh_token'];
        $_SESSION['ml_token_expires'] = time() + $token_data['expires_in'];
        
        // Obter informações do usuário do Mercado Livre
        $user_url = 'https://api.mercadolibre.com/users/me?access_token=' . $token_data['access_token'];
        $user_ch = curl_init($user_url);
        curl_setopt($user_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($user_ch, CURLOPT_SSL_VERIFYPEER, false);
        $user_response = curl_exec($user_ch);
        curl_close($user_ch);
        
        $user_data = json_decode($user_response, true);
        
        if (isset($user_data['id'])) {
            // Salvar informações do usuário do Mercado Livre
            $_SESSION['ml_user_id'] = $user_data['id'];
            $_SESSION['ml_nickname'] = $user_data['nickname'];
            $_SESSION['ml_email'] = $user_data['email'];
            
            // Verificar se este usuário do ML já está vinculado a um vendedor no sistema
            $sql = "SELECT * FROM vendedores WHERE ml_user_id = ?";
            $vendedor = fetchSingle($sql, [$user_data['id']]);
            
            if ($vendedor) {
                // Atualizar os tokens do vendedor
                $sql = "UPDATE vendedores SET 
                        ml_access_token = ?,
                        ml_refresh_token = ?,
                        ml_token_expires = ?
                        WHERE id = ?";
                executeQuery($sql, [
                    $token_data['access_token'], 
                    $token_data['refresh_token'], 
                    date('Y-m-d H:i:s', time() + $token_data['expires_in']),
                    $vendedor['id']
                ]);
                
                $_SESSION['success'] = 'Autenticação com o Mercado Livre atualizada com sucesso!';
            } else {
                // Se o usuário atual é um vendedor, vincular a conta do ML
                if (isVendedor()) {
                    $vendedor_id = $_SESSION['user_id'];
                    
                    $sql = "UPDATE vendedores SET 
                            ml_user_id = ?,
                            ml_nickname = ?,
                            ml_email = ?,
                            ml_access_token = ?,
                            ml_refresh_token = ?,
                            ml_token_expires = ?
                            WHERE id = ?";
                    
                    executeQuery($sql, [
                        $user_data['id'],
                        $user_data['nickname'],
                        $user_data['email'],
                        $token_data['access_token'], 
                        $token_data['refresh_token'], 
                        date('Y-m-d H:i:s', time() + $token_data['expires_in']),
                        $vendedor_id
                    ]);
                    
                    $_SESSION['success'] = 'Sua conta foi vinculada ao Mercado Livre com sucesso!';
                } else {
                    $_SESSION['ml_data'] = [
                        'ml_user_id' => $user_data['id'],
                        'ml_nickname' => $user_data['nickname'],
                        'ml_email' => $user_data['email'],
                        'ml_access_token' => $token_data['access_token'],
                        'ml_refresh_token' => $token_data['refresh_token'],
                        'ml_token_expires' => date('Y-m-d H:i:s', time() + $token_data['expires_in'])
                    ];
                    
                    $_SESSION['info'] = 'Autenticação com o Mercado Livre realizada com sucesso! Por favor, complete seu cadastro.';
                    header('Location: register.php');
                    exit;
                }
            }
        } else {
            $_SESSION['ml_error'] = 'Não foi possível obter informações do usuário do Mercado Livre.';
        }
    } else {
        $_SESSION['ml_error'] = 'Erro ao obter token de acesso: ' . ($token_data['error'] ?? 'Erro desconhecido');
    }
} else {
    $_SESSION['ml_error'] = 'Código de autorização não recebido.';
}

// Redirecionar para a página inicial
header('Location: index.php');
exit;
?>
