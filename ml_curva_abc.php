<?php
/**
 * Funções para análise de Curva ABC
 */

/**
 * Classifica produtos segundo a metodologia de curva ABC
 *
 * @param array $produtos Lista de produtos com suas vendas
 * @return array Produtos classificados com categorias A, B e C
 */
function analiseCurvaABC($produtos) {
    // Verificar se existem produtos
    if (empty($produtos)) {
        return [
            'produtos' => [],
            'estatisticas' => [],
            'valor_total_vendas' => 0
        ];
    }
    
    // 1. Ordenar produtos por valor total (do maior para o menor)
    usort($produtos, function($a, $b) {
        return $b['valor_total'] <=> $a['valor_total'];
    });
    
    // 2. Calcular o valor total de todas as vendas
    $valor_total_vendas = array_sum(array_column($produtos, 'valor_total'));
    
    // 3. Calcular o percentual de cada produto em relação ao total
    foreach ($produtos as &$produto) {
        $produto['percentual'] = ($produto['valor_total'] / $valor_total_vendas) * 100;
    }
    
    // 4. Calcular o percentual acumulado
    $percentual_acumulado = 0;
    foreach ($produtos as &$produto) {
        $percentual_acumulado += $produto['percentual'];
        $produto['percentual_acumulado'] = $percentual_acumulado;
    }
    
    // 5. Classificação ABC
    foreach ($produtos as &$produto) {
        // Categoria A: Representa até 80% do valor acumulado
        if ($produto['percentual_acumulado'] <= 80) {
            $produto['classificacao'] = 'A';
        }
        // Categoria B: Representa de 80% até 95% do valor acumulado
        elseif ($produto['percentual_acumulado'] <= 95) {
            $produto['classificacao'] = 'B';
        }
        // Categoria C: Representa os 5% finais do valor acumulado
        else {
            $produto['classificacao'] = 'C';
        }
    }
    
    // 6. Calcular estatísticas para cada categoria
    $estatisticas = calcularEstatisticasCurvaABC($produtos, $valor_total_vendas);
    
    return [
        'produtos' => $produtos,
        'estatisticas' => $estatisticas,
        'valor_total_vendas' => $valor_total_vendas
    ];
}

/**
 * Calcula estatísticas das categorias A, B e C
 *
 * @param array $produtos Lista de produtos classificados
 * @param float $valor_total_vendas Valor total de todas as vendas
 * @return array Estatísticas por categoria
 */
function calcularEstatisticasCurvaABC($produtos, $valor_total_vendas) {
    $categorias = ['A', 'B', 'C'];
    $estatisticas = [];
    
    // Total de produtos
    $total_produtos = count($produtos);
    
    foreach ($categorias as $categoria) {
        // Filtrar produtos da categoria atual
        $produtos_categoria = array_filter($produtos, function($produto) use ($categoria) {
            return $produto['classificacao'] === $categoria;
        });
        
        // Contagem de produtos
        $quantidade_produtos = count($produtos_categoria);
        
        // Calcular valor total da categoria
        $valor_total_categoria = 0;
        foreach ($produtos_categoria as $produto) {
            $valor_total_categoria += $produto['valor_total'];
        }
        
        // Calcular percentuais usando variáveis escalares (não arrays)
        $percentual_quantidade = ($total_produtos > 0) ? ($quantidade_produtos / $total_produtos) * 100 : 0;
        $percentual_valor = ($valor_total_vendas > 0) ? ($valor_total_categoria / $valor_total_vendas) * 100 : 0;
        
        // Armazenar estatísticas com valores escalares
        $estatisticas[$categoria] = [
            'quantidade_produtos' => $quantidade_produtos,
            'valor_total' => $valor_total_categoria,
            'percentual_quantidade' => $percentual_quantidade,   // Número, não array
            'percentual_valor' => $percentual_valor              // Número, não array
        ];
    }
    
    return $estatisticas;
}
?>
