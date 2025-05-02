<?php
// Aqui incluímos apenas funções específicas de negócio

// Função para calcular a taxa do Mercado Livre
function calcularTaxaML($categoria, $valor) {
    global $categorias_ml;
    
    if (isset($categorias_ml[$categoria])) {
        $taxa_percentual = $categorias_ml[$categoria]['taxa'];
        return ($taxa_percentual / 100) * $valor;
    }
    
    // Se categoria não for encontrada, usar uma taxa padrão de 13%
    return 0.13 * $valor;
}

// Função para calcular o lucro
function calcularLucro($valor_venda, $custo_produto, $taxa_ml, $custos_adicionais = 0) {
    return $valor_venda - $custo_produto - $taxa_ml - $custos_adicionais;
}

// Função para calcular margem de lucro
function calcularMargemLucro($lucro, $valor_venda) {
    if ($valor_venda <= 0) return 0;
    return ($lucro / $valor_venda) * 100;
}

// Função para calcular custos adicionais
function calcularCustosAdicionais($valor_venda, $percentual_custos_adicionais) {
    global $custo_adicional_padrao;
    
    if ($percentual_custos_adicionais === null) {
        $percentual_custos_adicionais = $custo_adicional_padrao;
    }
    
    return ($percentual_custos_adicionais / 100) * $valor_venda;
}

// Função para calcular o preço de venda recomendado
function calcularPrecoRecomendado($custo_produto, $categoria, $margem_desejada = null) {
    global $margem_lucro_minima;
    
    if ($margem_desejada === null) {
        $margem_desejada = $margem_lucro_minima;
    }
    
    // Transformar margem de porcentagem para decimal
    $margem_decimal = $margem_desejada / 100;
    
    // Estimar a taxa do ML para esta categoria
    $taxa_estimada = (calcularTaxaML($categoria, 100)) / 100; // Taxa para cada R$ 100
    
    // Estimar custos adicionais
    $custos_adicionais_estimados = calcularCustosAdicionais(100, null) / 100; // Para cada R$ 100
    
    // Calcular preço recomendado
    // Fórmula: preço = custo / (1 - (taxa + custos_adicionais + margem))
    $denominador = 1 - ($taxa_estimada + $custos_adicionais_estimados + $margem_decimal);
    
    // Evitar divisão por zero
    if ($denominador <= 0) {
        return $custo_produto * 2; // Retorna o dobro do custo como fallback
    }
    
    return $custo_produto / $denominador;
}

// Função para sanitizar input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>