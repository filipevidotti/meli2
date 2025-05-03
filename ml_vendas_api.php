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
    
    // Parâmetros de busca
    $offset = 0;
    $limit = 50; // Limite por página
    $todas_ordens = [];
    
    do {
        // Montar URL com filtro de data
        $url = "https://api.mercadolibre.com/orders/search?";
        $url .= "seller=me&";
        $url .= "order.date_created.from={$data_inicio_iso}&";
        $url .= "order.date_created.to={$data_fim_iso}&";
        $url .= "offset={$offset}&limit={$limit}";
        
        // Inicializar cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        
        // Executar requisição
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Verificar se a requisição foi bem-sucedida
        if ($status == 200) {
            $data = json_decode($response, true);
            
            // Adicionar ordens retornadas ao array de todas as ordens
            if (isset($data['results']) && is_array($data['results'])) {
                $todas_ordens = array_merge($todas_ordens, $data['results']);
            }
            
            // Verificar se há mais páginas
            $total = $data['paging']['total'] ?? 0;
            $offset += $limit;
            $tem_mais_paginas = $offset < $total;
            
        } else {
            // Log de erro em caso de falha
            error_log("Erro ao buscar ordens do ML: Status {$status}, Resposta: {$response}");
            return ['error' => 'Erro ao buscar ordens de vendas'];
        }
        
    } while ($tem_mais_paginas);
    
    return $todas_ordens;
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
    // Buscar ordens
    $ordens = buscarOrdensML($access_token, $data_inicio, $data_fim);
    
    // Verificar se houve erro
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
