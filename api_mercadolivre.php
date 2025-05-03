<?php
/**
 * Biblioteca de funções para integração com a API do Mercado Livre
 */

/**
 * Obtém o token de acesso do Mercado Livre para um usuário
 * 
 * @param PDO $pdo Conexão com o banco de dados
 * @param int $usuario_id ID do usuário
 * @return array|null Token de acesso ou null se não encontrado/expirado
 */
function getMercadoLivreToken($pdo, $usuario_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM mercadolivre_tokens 
            WHERE usuario_id = ? AND revogado = 0
            ORDER BY data_expiracao DESC 
            LIMIT 1
        ");
        $stmt->execute([$usuario_id]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$token) {
            return null;
        }
        
        // Verificar se o token é válido
        $data_expiracao = new DateTime($token['data_expiracao']);
        $agora = new DateTime();
        
        if ($agora >= $data_expiracao) {
            // Token expirado, tentar renovar
            return renovarToken($pdo, $usuario_id, $token);
        }
        
        return $token;
    } catch (Exception $e) {
        error_log("Erro ao obter token do Mercado Livre: " . $e->getMessage());
        return null;
    }
}

/**
 * Renova o token de acesso do Mercado Livre
 * 
 * @param PDO $pdo Conexão com o banco de dados
 * @param int $usuario_id ID do usuário
 * @param array $token_atual Dados do token atual
 * @return array|null Novo token de acesso ou null se falhar
 */
function renovarToken($pdo, $usuario_id, $token_atual) {
    try {
        // Buscar configurações do Mercado Livre
        $stmt = $pdo->prepare("SELECT * FROM mercadolivre_config WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config || empty($config['client_id']) || empty($config['client_secret'])) {
            error_log("Configurações do Mercado Livre não encontradas para o usuário ID: " . $usuario_id);
            return null;
        }
        
        // Preparar requisição para renovar o token
        $refresh_token = $token_atual['refresh_token'];
        $post_data = [
            'grant_type' => 'refresh_token',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'refresh_token' => $refresh_token
        ];
        
        // Iniciar o cURL para renovar o token
        $ch = curl_init('https://api.mercadolibre.com/oauth/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json', 
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status_code == 200) {
            $token_data = json_decode($response, true);
            
            if (isset($token_data['access_token']) && isset($token_data['refresh_token'])) {
                // Calcular nova data de expiração
                $agora = new DateTime();
                $segundos_expiracao = $token_data['expires_in'] ?? 21600; // 6 horas por padrão
                $data_expiracao = clone $agora;
                $data_expiracao->add(new DateInterval("PT{$segundos_expiracao}S"));
                
                // Salvar o novo token
                $stmt = $pdo->prepare("
                    INSERT INTO mercadolivre_tokens 
                    (usuario_id, access_token, refresh_token, data_criacao, data_expiracao) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $usuario_id,
                    $token_data['access_token'],
                    $token_data['refresh_token'],
                    $agora->format('Y-m-d H:i:s'),
                    $data_expiracao->format('Y-m-d H:i:s')
                ]);
                
                // Retornar o novo token
                return [
                    'access_token' => $token_data['access_token'],
                    'refresh_token' => $token_data['refresh_token'],
                    'data_criacao' => $agora->format('Y-m-d H:i:s'),
                    'data_expiracao' => $data_expiracao->format('Y-m-d H:i:s')
                ];
            }
        }
        
        error_log("Falha ao renovar token do Mercado Livre. Código: " . $status_code);
        return null;
    } catch (Exception $e) {
        error_log("Erro ao renovar token do Mercado Livre: " . $e->getMessage());
        return null;
    }
}

/**
 * Realiza uma requisição GET para a API do Mercado Livre
 * 
 * @param string $endpoint Endpoint da API
 * @param string $access_token Token de acesso
 * @return array Resposta da API
 */
function mercadoLivreGet($endpoint, $access_token) {
    $ch = curl_init("https://api.mercadolibre.com" . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status_code' => $status_code,
        'data' => json_decode($response, true)
    ];
}

/**
 * Realiza uma requisição POST para a API do Mercado Livre
 * 
 * @param string $endpoint Endpoint da API
 * @param array $data Dados para enviar
 * @param string $access_token Token de acesso
 * @return array Resposta da API
 */
function mercadoLivrePost($endpoint, $data, $access_token) {
    $ch = curl_init("https://api.mercadolibre.com" . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status_code' => $status_code,
        'data' => json_decode($response, true)
    ];
}

/**
 * Realiza uma requisição PUT para a API do Mercado Livre
 * 
 * @param string $endpoint Endpoint da API
 * @param array $data Dados para enviar
 * @param string $access_token Token de acesso
 * @return array Resposta da API
 */
function mercadoLivrePut($endpoint, $data, $access_token) {
    $ch = curl_init("https://api.mercadolibre.com" . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status_code' => $status_code,
        'data' => json_decode($response, true)
    ];
}

/**
 * Busca os anúncios ativos de um vendedor no Mercado Livre
 * 
 * @param string $ml_user_id ID do usuário no Mercado Livre
 * @param string $access_token Token de acesso
 * @param int $offset Posição inicial
 * @param int $limit Quantidade de resultados
 * @return array Anúncios do vendedor
 */
function buscarAnunciosVendedor($ml_user_id, $access_token, $offset = 0, $limit = 50) {
    $endpoint = "/users/{$ml_user_id}/items/search?status=active&limit={$limit}&offset={$offset}";
    $response = mercadoLivreGet($endpoint, $access_token);
    
    if ($response['status_code'] == 200 && isset($response['data']['results'])) {
        $anuncios = [];
        
        foreach ($response['data']['results'] as $item_id) {
            $item_response = mercadoLivreGet("/items/{$item_id}", $access_token);
            
            if ($item_response['status_code'] == 200) {
                $anuncios[] = $item_response['data'];
            }
        }
        
        return [
            'total' => $response['data']['paging']['total'] ?? 0,
            'anuncios' => $anuncios
        ];
    }
    
    return [
        'total' => 0,
        'anuncios' => []
    ];
}

/**
 * Atualiza o preço de um anúncio no Mercado Livre
 * 
 * @param string $item_id ID do item no Mercado Livre
 * @param float $preco Novo preço
 * @param string $access_token Token de acesso
 * @return bool true se atualizado com sucesso, false caso contrário
 */
function atualizarPrecoAnuncio($item_id, $preco, $access_token) {
    $data = ['price' => $preco];
    $response = mercadoLivrePut("/items/{$item_id}", $data, $access_token);
    
    return $response['status_code'] == 200;
}

/**
 * Atualiza a quantidade disponível de um anúncio no Mercado Livre
 * 
 * @param string $item_id ID do item no Mercado Livre
 * @param int $quantidade Nova quantidade disponível
 * @param string $access_token Token de acesso
 * @return bool true se atualizado com sucesso, false caso contrário
 */
function atualizarQuantidadeAnuncio($item_id, $quantidade, $access_token) {
    $data = ['available_quantity' => $quantidade];
    $response = mercadoLivrePut("/items/{$item_id}", $data, $access_token);
    
    return $response['status_code'] == 200;
}

/**
 * Busca as ordens/vendas de um vendedor no Mercado Livre
 * 
 * @param string $seller_id ID do vendedor no Mercado Livre
 * @param string $access_token Token de acesso
 * @param string $status Status das ordens (opcional)
 * @param string $data_inicio Data de início (opcional, formato: YYYY-MM-DD)
 * @param string $data_fim Data de fim (opcional, formato: YYYY-MM-DD)
 * @param int $offset Posição inicial
 * @param int $limit Quantidade de resultados
 * @return array Ordens/vendas do vendedor
 */
function buscarOrdensVendedor($seller_id, $access_token, $status = null, $data_inicio = null, $data_fim = null, $offset = 0, $limit = 50) {
    $endpoint = "/orders/search?seller={$seller_id}&limit={$limit}&offset={$offset}";
    
    if ($status) {
        $endpoint .= "&order.status={$status}";
    }
    
    if ($data_inicio && $data_fim) {
        $endpoint .= "&order.date_created.from={$data_inicio}T00:00:00.000-00:00";
        $endpoint .= "&order.date_created.to={$data_fim}T23:59:59.999-00:00";
    }
    
    $response = mercadoLivreGet($endpoint, $access_token);
    
    if ($response['status_code'] == 200 && isset($response['data']['results'])) {
        return [
            'total' => $response['data']['paging']['total'] ?? 0,
            'ordens' => $response['data']['results']
        ];
    }
    
    return [
        'total' => 0,
        'ordens' => []
    ];
}

/**
 * Busca os detalhes de uma ordem específica no Mercado Livre
 * 
 * @param string $order_id ID da ordem no Mercado Livre
 * @param string $access_token Token de acesso
 * @return array|null Detalhes da ordem ou null se não encontrada
 */
function buscarDetalhesOrdem($order_id, $access_token) {
    $response = mercadoLivreGet("/orders/{$order_id}", $access_token);
    
    if ($response['status_code'] == 200) {
        return $response['data'];
    }
    
    return null;
}

/**
 * Busca as categorias do Mercado Livre (principais)
 * 
 * @param string $site_id ID do site (ex: MLB para Brasil)
 * @param string $access_token Token de acesso
 * @return array Categorias do Mercado Livre
 */
function buscarCategorias($site_id, $access_token) {
    $response = mercadoLivreGet("/sites/{$site_id}/categories", $access_token);
    
    if ($response['status_code'] == 200) {
        return $response['data'];
    }
    
    return [];
}

/**
 * Busca as subcategorias de uma categoria do Mercado Livre
 * 
 * @param string $categoria_id ID da categoria
 * @param string $access_token Token de acesso
 * @return array Subcategorias da categoria
 */
function buscarSubcategorias($categoria_id, $access_token) {
    $response = mercadoLivreGet("/categories/{$categoria_id}", $access_token);
    
    if ($response['status_code'] == 200 && isset($response['data']['children_categories'])) {
        return $response['data']['children_categories'];
    }
    
    return [];
}

?>