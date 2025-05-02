<?php
// api_mercadolivre.php

function obterTokenML() {
    global $pdo;
    
    // Verificar se há um token válido armazenado
    $sql = "SELECT * FROM tokens WHERE chave = 'ml_token' AND usuario_id = ? AND expires_at > ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id'], time()]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($token) {
        // Token ainda é válido
        $dados = json_decode($token['valor'], true);
        return $dados['access_token'];
    } else {
        // Token expirado ou não existe, precisa autenticar novamente
        return null;
    }
}

function atualizarTokenML($code) {
    global $pdo, $ml_client_id, $ml_client_secret, $ml_redirect_url;
    
    // URL para obter token
    $url = 'https://api.mercadolibre.com/oauth/token';
    
    // Dados para a requisição
    $data = [
        'grant_type' => 'authorization_code',
        'client_id' => $ml_client_id,
        'client_secret' => $ml_client_secret,
        'code' => $code,
        'redirect_uri' => $ml_redirect_url
    ];
    
    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']);
    
    // Executar requisição
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Erro na requisição: " . $error);
    }
    
    $token_data = json_decode($response, true);
    
    if (isset($token_data['error'])) {
        throw new Exception("Erro ao obter token: " . $token_data['error'] . ' - ' . ($token_data['message'] ?? ''));
    }
    
    if (isset($token_data['access_token'])) {
        // Calcular quando o token expira
        $expires_at = time() + $token_data['expires_in'];
        
        // Salvar token no banco de dados
        $sql = "INSERT INTO tokens (usuario_id, chave, valor, expires_at) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                valor = VALUES(valor), 
                expires_at = VALUES(expires_at)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_SESSION['user_id'],
            'ml_token',
            json_encode($token_data),
            $expires_at
        ]);
        
        return $token_data['access_token'];
    } else {
        throw new Exception("Resposta inválida da API");
    }
}

function renovarTokenML() {
    global $pdo, $ml_client_id, $ml_client_secret;
    
    // Obter refresh token
    $sql = "SELECT * FROM tokens WHERE chave = 'ml_token' AND usuario_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
    $token_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$token_row) {
        throw new Exception("Token não encontrado");
    }
    
    $token_data = json_decode($token_row['valor'], true);
    $refresh_token = $token_data['refresh_token'] ?? null;
    
    if (!$refresh_token) {
        throw new Exception("Refresh token não encontrado");
    }
    
    // URL para renovar token
    $url = 'https://api.mercadolibre.com/oauth/token';
    
    // Dados para a requisição
    $data = [
        'grant_type' => 'refresh_token',
        'client_id' => $ml_client_id,
        'client_secret' => $ml_client_secret,
        'refresh_token' => $refresh_token
    ];
    
    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']);
    
    // Executar requisição
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Erro na requisição: " . $error);
    }
    
    $new_token_data = json_decode($response, true);
    
    if (isset($new_token_data['access_token'])) {
        // Calcular quando o token expira
        $expires_at = time() + $new_token_data['expires_in'];
        
        // Atualizar token no banco de dados
        $sql = "UPDATE tokens 
                SET valor = ?, expires_at = ? 
                WHERE chave = 'ml_token' AND usuario_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            json_encode($new_token_data),
            $expires_at,
            $_SESSION['user_id']
        ]);
        
        return $new_token_data['access_token'];
    } else {
        throw new Exception("Erro ao renovar token: " . ($new_token_data['message'] ?? 'Erro desconhecido'));
    }
}

function fazerRequisicaoML($endpoint, $method = 'GET', $data = null, $tentativas = 0) {
    $token = obterTokenML();
    
    if (!$token) {
        throw new Exception("Token do Mercado Livre não encontrado. Por favor, autentique-se novamente.");
    }
    
    $url = 'https://api.mercadolibre.com' . $endpoint;
    
    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    
    if ($method == 'POST' || $method == 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
        }
    } elseif ($method != 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }
    
    // Executar requisição
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Erro na requisição: " . $error);
    }
    
    // Se receber 401 (Unauthorized), tentar renovar o token e refazer a requisição
    if ($http_code == 401 && $tentativas < 1) {
        renovarTokenML();
        return fazerRequisicaoML($endpoint, $method, $data, $tentativas + 1);
    }
    
    return json_decode($response, true);
}

function obterAnunciosML($offset = 0, $limit = 50) {
    $usuario_id = $_SESSION['user_id'];
    
    // Obter o ID do usuário no Mercado Livre
    $sql = "SELECT ml_user_id FROM vendedores WHERE usuario_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario_id]);
    $vendedor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendedor || !$vendedor['ml_user_id']) {
        throw new Exception("ID do usuário no Mercado Livre não encontrado.");
    }
    
    $ml_user_id = $vendedor['ml_user_id'];
    
    // Obter anúncios da API do ML
    $endpoint = "/users/{$ml_user_id}/items/search?limit={$limit}&offset={$offset}";
    $result = fazerRequisicaoML($endpoint);
    
    if (!isset($result['results']) || !is_array($result['results'])) {
        throw new Exception("Formato de resposta inválido.");
    }
    
    $item_ids = $result['results'];
    $anuncios = [];
    
    if (count($item_ids) > 0) {
        // Obter detalhes dos anúncios
        $ids_string = implode(',', $item_ids);
        $endpoint = "/items?ids={$ids_string}";
        $items = fazerRequisicaoML($endpoint);
        
        foreach ($items as $item) {
            if (isset($item['body']) && $item['status'] == 200) {
                $anuncio = $item['body'];
                $anuncios[] = [
                    'ml_item_id' => $anuncio['id'],
                    'titulo' => $anuncio['title'],
                    'preco' => $anuncio['price'],
                    'permalink' => $anuncio['permalink'],
                    'categoria_id' => $anuncio['category_id'],
                    'tipo_anuncio' => $anuncio['listing_type_id'],
                    'status' => $anuncio['status'],
                    'thumbnail' => $anuncio['thumbnail']
                ];
            }
        }
    }
    
    return [
        'anuncios' => $anuncios,
        'total' => $result['paging']['total'],
        'offset' => $offset,
        'limit' => $limit
    ];
}

function salvarAnunciosML($anuncios) {
    global $pdo;
    $usuario_id = $_SESSION['user_id'];
    $salvos = 0;
    
    foreach ($anuncios as $anuncio) {
        // Verificar se o anúncio já existe
        $sql = "SELECT id FROM anuncios_ml WHERE usuario_id = ? AND ml_item_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $anuncio['ml_item_id']]);
        $existente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existente) {
            // Atualizar anúncio existente
            $sql = "UPDATE anuncios_ml SET 
                    titulo = ?,
                    preco = ?,
                    permalink = ?,
                    categoria_id = ?,
                    tipo_anuncio = ?,
                    status = ?,
                    thumbnail = ?,
                    data_ultima_sincronizacao = NOW()
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $anuncio['titulo'],
                $anuncio['preco'],
                $anuncio['permalink'],
                $anuncio['categoria_id'],
                $anuncio['tipo_anuncio'],
                $anuncio['status'],
                $anuncio['thumbnail'],
                $existente['id']
            ]);
        } else {
            // Inserir novo anúncio
            $sql = "INSERT INTO anuncios_ml 
                    (usuario_id, ml_item_id, titulo, preco, permalink, categoria_id, tipo_anuncio, status, thumbnail, data_ultima_sincronizacao) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $usuario_id,
                $anuncio['ml_item_id'],
                $anuncio['titulo'],
                $anuncio['preco'],
                $anuncio['permalink'],
                $anuncio['categoria_id'],
                $anuncio['tipo_anuncio'],
                $anuncio['status'],
                $anuncio['thumbnail']
            ]);
        }
        
        $salvos++;
    }
    
    return $salvos;
}

function obterCategoriasML($categoria_id = null) {
    if ($categoria_id) {
        // Obter detalhes de uma categoria específica
        $endpoint = "/categories/{$categoria_id}";
        return fazerRequisicaoML($endpoint);
    } else {
        // Obter categorias principais
        $endpoint = "/sites/MLB/categories";
        return fazerRequisicaoML($endpoint);
    }
}

function obterVendasML($offset = 0, $limit = 50) {
    // Obter vendas recentes
    $endpoint = "/orders/search?seller=me&offset={$offset}&limit={$limit}";
    $result = fazerRequisicaoML($endpoint);
    
    return $result;
}

function vincularAnuncioProduto($anuncio_id, $produto_id) {
    global $pdo;
    $usuario_id = $_SESSION['user_id'];
    
    // Verificar se o anúncio pertence ao usuário
    $sql = "SELECT id FROM anuncios_ml WHERE id = ? AND usuario_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$anuncio_id, $usuario_id]);
    $anuncio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$anuncio) {
        throw new Exception("Anúncio não encontrado ou não pertence ao usuário.");
    }
    
    // Verificar se o produto pertence ao usuário
    $sql = "SELECT id FROM produtos WHERE id = ? AND usuario_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$produto_id, $usuario_id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produto) {
        throw new Exception("Produto não encontrado ou não pertence ao usuário.");
    }
    
    // Vincular anúncio ao produto
    $sql = "UPDATE anuncios_ml SET produto_id = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$produto_id, $anuncio_id]);
    
    return true;
}
?>