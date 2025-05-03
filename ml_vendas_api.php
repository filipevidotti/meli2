<?php
/**
 * Funções para buscar dados de vendas do Mercado Livre
 */

// Incluir o arquivo de configuração
require_once 'ml_config.php';

/**
 * Busca ordens de vendas do Mercado Livre
 *
 * @param string $access_token Token de acesso
 * @param string $data_inicio Data inicial (formato YYYY-MM-DD)
 * @param string $data_fim Data final (formato YYYY-MM-DD)
 * @return array Lista de ordens de vendas
 */
function buscarOrdensML($access_token, $data_inicio = null, $data_fim = null) {
    // Log inicial para depuração
    error_log("Iniciando busca de ordens. Token: " . substr($access_token, 0, 10) . "...");
    error_log("Período: " . $data_inicio . " até " . $data_fim);
    
    // Primeiro, vamos obter o ID do usuário (seller)
    $ch = curl_init('https://api.mercadolibre.com/users/me');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);
    
    $user_response = curl_exec($ch);
    $user_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($user_status != 200) {
        error_log("Erro ao obter informações do usuário: " . $user_response);
        return ['error' => 'Não foi possível identificar o vendedor'];
    }
    
    $user_data = json_decode($user_response, true);
    $seller_id = $user_data['id'];
    
    error_log("ID do vendedor identificado: " . $seller_id);
    
    // Se data_inicio não foi informada, pega os últimos 60 dias
    if (empty($data_inicio)) {
        $data_inicio = date('Y-m-d', strtotime('-60 days'));
    }
    
    // Se data_fim não foi informada, usa a data atual
    if (empty($data_fim)) {
        $data_fim = date('Y-m-d');
    }
    
    // Converter datas para o formato ISO 8601 requerido pela API
    $data_inicio_iso = date('c', strtotime($data_inicio));
    $data_fim_iso = date('c', strtotime($data_fim . ' 23:59:59'));
    
    error_log("Datas ISO: " . $data_inicio_iso . " até " . $data_fim_iso);
    
    // Parâmetros de busca
    $offset = 0;
    $limit = 50; // Limite por página
    $todas_ordens = [];
    
    try {
        // Usar a API correta para o vendedor (orders/search)
        do {
            $url = "https://api.mercadolibre.com/orders/search?";
            $url .= "seller=" . $seller_id . "&";
            $url .= "order.date_created.from=" . urlencode($data_inicio_iso) . "&";
            $url .= "order.date_created.to=" . urlencode($data_fim_iso) . "&";
            $url .= "offset=" . $offset . "&limit=" . $limit;
            
            error_log("URL da requisição: " . $url);
            
            // Inicializar cURL
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token
            ]);
            
            // Executar requisição
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            error_log("Status da resposta: " . $status);
            
            if (!empty($curl_error)) {
                error_log("Erro cURL: " . $curl_error);
                return ['error' => 'Erro de conexão: ' . $curl_error];
            }
            
            // Verificar se a requisição foi bem-sucedida
            if ($status == 200) {
                $data = json_decode($response, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("Erro ao decodificar JSON: " . json_last_error_msg());
                    return ['error' => 'Resposta inválida da API'];
                }
                
                // Adicionar ordens retornadas ao array de todas as ordens
                if (isset($data['results']) && is_array($data['results'])) {
                    $todas_ordens = array_merge($todas_ordens, $data['results']);
                    error_log("Ordens encontradas nesta página: " . count($data['results']));
                } else {
                    error_log("Nenhuma ordem encontrada ou formato inesperado na resposta");
                    error_log("Estrutura da resposta: " . json_encode(array_keys($data)));
                }
                
                // Verificar se há mais páginas
                $total = $data['paging']['total'] ?? 0;
                $offset += $limit;
                $tem_mais_paginas = $offset < $total;
                
            } else {
                // Tentar extrair mensagem de erro mais detalhada
                $error_data = json_decode($response, true);
                $error_message = isset($error_data['message']) ? $error_data['message'] : 'Status: ' . $status;
                
                error_log("Erro ao buscar ordens do ML: " . $error_message);
                error_log("Resposta completa: " . $response);
                
                return ['error' => 'Erro ao buscar ordens de vendas: ' . $error_message];
            }
            
        } while ($tem_mais_paginas);
        
        error_log("Total de ordens encontradas: " . count($todas_ordens));
        
        return $todas_ordens;
    } catch (Exception $e) {
        error_log("Exceção ao buscar ordens: " . $e->getMessage());
        return ['error' => 'Erro ao processar a requisição: ' . $e->getMessage()];
    }
}

/**
 * Método alternativo para buscar ordens de vendas
 */
function buscarOrdensML_Alternativo($access_token, $data_inicio = null, $data_fim = null) {
    error_log("Tentando método alternativo para buscar ordens...");
    
    // Se data_inicio não foi informada, pega os últimos 60 dias
    if (empty($data_inicio)) {
        $data_inicio = date('Y-m-d', strtotime('-60 days'));
    }
    
    // Se data_fim não foi informada, usa a data atual
    if (empty($data_fim)) {
        $data_fim = date('Y-m-d');
    }
    
    // Primeiro, buscar vendas concluídas (outra API)
    $ch = curl_init('https://api.mercadolibre.com/orders/search/recently_sold');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($status == 200) {
        $data = json_decode($response, true);
        
        if (isset($data['results']) && is_array($data['results'])) {
            error_log("Vendas recentes encontradas: " . count($data['results']));
            
            // Filtrar por data
            $data_inicio_timestamp = strtotime($data_inicio);
            $data_fim_timestamp = strtotime($data_fim);
            
            $ordens_filtradas = array_filter($data['results'], function($ordem) use ($data_inicio_timestamp, $data_fim_timestamp) {
                if (isset($ordem['date_created'])) {
                    $ordem_timestamp = strtotime($ordem['date_created']);
                    return ($ordem_timestamp >= $data_inicio_timestamp && $ordem_timestamp <= $data_fim_timestamp);
                }
                return false;
            });
            
            return array_values($ordens_filtradas);
        }
    }
    
    return ['error' => 'Não foi possível buscar ordens pelo método alternativo'];
}

/**
 * Processa os dados de ordens para o formato necessário para análise ABC
 *
 * @param array $ordens Lista de ordens do Mercado Livre
 * @return array Dados processados agrupados por produto
 */
function processarDadosVendas($ordens) {
    $produtos = [];
    
    foreach ($ordens as $ordem) {
        // Verificar se a ordem foi cancelada
        if (isset($ordem['status']) && $ordem['status'] === 'cancelled') {
            continue; // Ignorar ordens canceladas
        }
        
        // Verificar se há itens na ordem
        if (empty($ordem['order_items']) || !is_array($ordem['order_items'])) {
            continue;
        }
        
        foreach ($ordem['order_items'] as $item) {
            // Obter dados do item
            $produto_id = $item['item']['id'];
            $titulo = $item['item']['title'];
            $quantidade = $item['quantity'];
            $preco_unitario = $item['unit_price'];
            $valor_total = $quantidade * $preco_unitario;
            
            // Se o produto já existe na lista, atualizar valores
            if (isset($produtos[$produto_id])) {
                $produtos[$produto_id]['quantidade'] += $quantidade;
                $produtos[$produto_id]['valor_total'] += $valor_total;
            } else {
                // Senão, adicionar produto à lista
                $produtos[$produto_id] = [
                    'id' => $produto_id,
                    'titulo' => $titulo,
                    'quantidade' => $quantidade,
                    'preco_unitario' => $preco_unitario,
                    'valor_total' => $valor_total
                ];
            }
        }
    }
    
    return array_values($produtos); // Remove as chaves associativas
}

/**
 * Busca detalhes de todos os produtos vendidos
 * 
 * @param array $produtos Lista de produtos a buscar detalhes
 * @param string $access_token Token de acesso
 * @return array Lista de produtos com detalhes adicionais
 */
function buscarDetalhesProdutos($produtos, $access_token) {
    foreach ($produtos as &$produto) {
        $produto_id = $produto['id'];
        
        // Buscar detalhes do produto
        $url = "https://api.mercadolibre.com/items/{$produto_id}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status == 200) {
            $detalhe = json_decode($response, true);
            
            // Adicionar detalhes ao produto
            $produto['categoria'] = $detalhe['category_id'] ?? '';
            $produto['thumbnail'] = $detalhe['thumbnail'] ?? '';
            $produto['permalink'] = $detalhe['permalink'] ?? '';
            
            // Buscar nome da categoria
            if (!empty($produto['categoria'])) {
                $url_categoria = "https://api.mercadolibre.com/categories/{$produto['categoria']}";
                
                $ch = curl_init($url_categoria);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                
                $response_categoria = curl_exec($ch);
                $status_categoria = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($status_categoria == 200) {
                    $detalhe_categoria = json_decode($response_categoria, true);
                    $produto['categoria_nome'] = $detalhe_categoria['name'] ?? $produto['categoria'];
                } else {
                    $produto['categoria_nome'] = $produto['categoria'];
                }
            } else {
                $produto['categoria_nome'] = 'N/A';
            }
            
        } else {
            // Se não conseguir buscar detalhes, manter dados básicos
            $produto['categoria'] = '';
            $produto['categoria_nome'] = 'N/A';
            $produto['thumbnail'] = '';
            $produto['permalink'] = '';
        }
    }
    
    return $produtos;
}

/**
 * Busca todas as vendas do Mercado Livre com detalhes
 *
 * @param string $access_token Token de acesso
 * @param string $data_inicio Data inicial (formato YYYY-MM-DD)
 * @param string $data_fim Data final (formato YYYY-MM-DD)
 * @return array Dados de vendas processados
 */
function buscarVendasML($access_token, $data_inicio = null, $data_fim = null) {
    // Tentar o método principal
    $ordens = buscarOrdensML($access_token, $data_inicio, $data_fim);
    
    // Se falhar, tentar o método alternativo
    if (isset($ordens['error'])) {
        error_log("Primeiro método falhou, tentando alternativo...");
        $ordens = buscarOrdensML_Alternativo($access_token, $data_inicio, $data_fim);
    }
    
    // Verificar novamente se houve erro
    if (isset($ordens['error'])) {
        return $ordens;
    }
    
    // Processar dados das ordens
    $produtos = processarDadosVendas($ordens);
    
    // Buscar detalhes dos produtos (opcional, pode ser comentado se for muito lento)
    $produtos_com_detalhes = buscarDetalhesProdutos($produtos, $access_token);
    
    return $produtos_com_detalhes;
}
?>
