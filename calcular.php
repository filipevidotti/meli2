<?php
// Incluindo o sistema de configuração e funções
require_once __DIR__ . '/config/database.php'; // Corrected path
require_once __DIR__ . '/functions/functions.php'; // Corrected path

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Corrected condition
    // Receber dados do formulário
    $produtoId = isset($_POST['produto_id']) ? (int)$_POST['produto_id'] : null; // Corrected array access
    $nomeProduto = $_POST['nome_produto']; // Corrected array access
    $precoVenda = (float)$_POST['preco_venda']; // Corrected array access
    $custoAquisicao = (float)$_POST['custo_aquisicao']; // Corrected array access
    $custoExtra = isset($_POST['custo_extra']) ? (float)$_POST['custo_extra'] : 0; // Corrected array access
    $peso = (float)$_POST['peso']; // Corrected array access
    $tipoAnuncio = $_POST['tipo_anuncio']; // Corrected array access
    $categoriaEspecial = isset($_POST['categoria_especial']) ? 1 : 0; // Corrected array access
    $tipoSupermercado = isset($_POST['tipo_supermercado']) ? 1 : 0; // Corrected array access
    $tipoEnvio = $_POST['tipo_envio']; // Corrected array access
    $regiao = $_POST['regiao']; // Corrected array access

    // --- Cálculo do Custo Fixo ML (Lógica Corrigida) ---
    $custoFixo = 0;
    if ($tipoSupermercado) {
        // Regras para Supermercado (Preço mínimo R$ 8)
        if ($precoVenda >= 8 && $precoVenda < 30) {
            $custoFixo = 1.00;
        } elseif ($precoVenda >= 30 && $precoVenda < 50) {
            $custoFixo = 2.00;
        } elseif ($precoVenda >= 50 && $precoVenda < 100) {
            $custoFixo = 4.00;
        } elseif ($precoVenda >= 100 && $precoVenda < 199) {
            $custoFixo = 6.00;
        }
        // Para >= 199, custo fixo é 0
    } else {
        // Regras para Produtos Normais
        if ($precoVenda < 29) {
            $custoFixo = 6.25;
        } elseif ($precoVenda >= 29 && $precoVenda < 50) {
            $custoFixo = 6.50;
        } elseif ($precoVenda >= 50 && $precoVenda < 79) {
            $custoFixo = 6.75;
        }
        // Para >= 79, custo fixo é 0 (já inicializado)
    }
    // --- Fim do Cálculo do Custo Fixo ML ---

    // Cálculo da taxa do Mercado Livre
    $taxaML = ($tipoAnuncio === 'classico') ? $precoVenda * 0.12 : $precoVenda * 0.17; // Corrected string comparison

    // Cálculo do custo de frete
    // Nota: A lógica de frete pode precisar de revisão/confirmação
    if ($tipoSupermercado) {
        // Frete Supermercado: Grátis acima de R$ 199 (para o vendedor?)
        // A função calcularFreteSupermercado precisa refletir as regras exatas
        $custoFrete = calcularFreteSupermercado($peso, $regiao, $precoVenda, $categoriaEspecial, $tipoEnvio);
    } else {
        // Frete Normal: Grátis acima de R$ 79 (para o vendedor?)
        // A função calcularFreteNormal precisa refletir as regras exatas
        $custoFrete = calcularFreteNormal($peso, $regiao, $precoVenda, $categoriaEspecial, $tipoEnvio);
    }

    // Cálculo dos resultados finais
    $custoTotal = $custoAquisicao + $custoFixo + $taxaML + $custoFrete + $custoExtra;
    $lucro = $precoVenda - $custoTotal;
    $margemLucro = ($precoVenda > 0) ? ($lucro / $precoVenda) * 100 : 0; // Evitar divisão por zero

    // Preparar dados para salvar (ajustar se a tabela produtos mudou)
    $produtoData = [
        'nome' => $nomeProduto, // Corrected array key
        // Os campos abaixo podem não existir mais na tabela 'produtos'
        // É melhor salvar os resultados do cálculo em outra tabela ou retornar via JSON
        /*
        'preco_venda' => $precoVenda,
        'custo_aquisicao' => $custoAquisicao,
        'custo_extra' => $custoExtra,
        'peso' => $peso,
        'tipo_anuncio' => $tipoAnuncio,
        'categoria_especial' => $categoriaEspecial,
        'tipo_supermercado' => $tipoSupermercado,
        'tipo_envio' => $tipoEnvio,
        'regiao' => $regiao,
        'custo_fixo' => $custoFixo,
        'taxa_ml' => $taxaML,
        'custo_frete' => $custoFrete,
        'custo_total' => $custoTotal,
        'lucro' => $lucro,
        'margem_lucro' => $margemLucro
        */
        // Campos relevantes da tabela 'produtos' (exemplo)
        'preco_custo' => $custoAquisicao,
        'peso' => $peso,
        // Adicionar outros campos se necessário (largura, altura, comprimento)
    ];

    // Salvar ou atualizar o produto (na tabela 'produtos')
    // A função salvarProduto/atualizarProduto precisa ser ajustada para a nova tabela 'produtos'
    /*
    if ($produtoId) {
        atualizarProduto($produtoId, $produtoData); // Função precisa ser adaptada
    } else {
        // Precisa do SKU para salvar um novo produto
        // $produtoId = salvarProduto($produtoData); // Função precisa ser adaptada
    }
    */

    // Em vez de salvar e redirecionar, vamos retornar os resultados como JSON
    // para serem exibidos na página da calculadora via JavaScript
    header('Content-Type: application/json'); // Corrected header call
    echo json_encode([
        'success' => true,
        'custoFixo' => $custoFixo,
        'taxaML' => $taxaML,
        'custoFrete' => $custoFrete,
        'custoTotal' => $custoTotal,
        'lucro' => $lucro,
        'margemLucro' => $margemLucro
    ]);
    exit;

    // Redirecionamento antigo (comentado)
    // header('Location: produtos_salvos.php');
    // exit;

} elseif (isset($_GET['produto_id'])) { // Corrected array access
    // Se for uma solicitação GET com ID do produto, redireciona para a calculadora com esse produto
    $produtoId = (int)$_GET['produto_id']; // Corrected array access
    header("Location: calculadora.php?produto_id=$produtoId"); // Corrected header call
    exit;
} else {
    // Acesso direto ou método inválido
    header('HTTP/1.1 405 Method Not Allowed'); // Corrected header call
    echo json_encode(['success' => false, 'message' => 'Método não permitido ou acesso direto inválido.']);
    exit;
}

// --- Funções de Cálculo de Frete (Manter ou Mover para functions.php) ---
// ATENÇÃO: A lógica de frete aqui pode estar desatualizada ou incorreta.
// As regras de frete grátis (>= R$ 79 normal, >= R$ 199 supermercado)
// e os descontos precisam ser aplicados corretamente.

function calcularFreteNormal($peso, $regiao, $precoVenda, $categoriaEspecial, $tipoEnvio) {
    // Tabela de fretes baseada nas informações fornecidas
    // ... (tabelas de frete como antes) ...
    $tabelaFreteSul = [
        '0.3' => ['normal' => 39.90, 'desc50' => 19.95, 'desc25' => 29.93], // Corrected array keys
        '0.5' => ['normal' => 42.90, 'desc50' => 21.45, 'desc25' => 32.18],
        '1' => ['normal' => 44.90, 'desc50' => 22.45, 'desc25' => 33.68],
        '2' => ['normal' => 46.90, 'desc50' => 23.45, 'desc25' => 35.18],
        '3' => ['normal' => 49.90, 'desc50' => 24.95, 'desc25' => 37.43],
        '4' => ['normal' => 53.90, 'desc50' => 26.95, 'desc25' => 40.43],
        '5' => ['normal' => 56.90, 'desc50' => 28.45, 'desc25' => 42.68],
        '9' => ['normal' => 88.90, 'desc50' => 44.45, 'desc25' => 66.68],
        '13' => ['normal' => 131.90, 'desc50' => 65.95, 'desc25' => 98.93],
        '17' => ['normal' => 146.90, 'desc50' => 73.45, 'desc25' => 110.18],
        '23' => ['normal' => 171.90, 'desc50' => 85.95, 'desc25' => 128.93],
        '30' => ['normal' => 197.90, 'desc50' => 98.95, 'desc25' => 148.43],
        '40' => ['normal' => 203.90, 'desc50' => 101.95, 'desc25' => 152.93],
        '50' => ['normal' => 210.90, 'desc50' => 105.45, 'desc25' => 158.18],
        '60' => ['normal' => 224.90, 'desc50' => 112.45, 'desc25' => 168.68],
        '70' => ['normal' => 240.90, 'desc50' => 120.45, 'desc25' => 180.68],
        '80' => ['normal' => 251.90, 'desc50' => 125.95, 'desc25' => 188.93],
        '90' => ['normal' => 279.90, 'desc50' => 139.95, 'desc25' => 209.93],
        '100' => ['normal' => 319.90, 'desc50' => 159.95, 'desc25' => 239.93],
        '125' => ['normal' => 357.90, 'desc50' => 178.95, 'desc25' => 268.43],
        '150' => ['normal' => 379.90, 'desc50' => 189.95, 'desc25' => 284.93],
        '151+' => ['normal' => 498.90, 'desc50' => 249.45, 'desc25' => 374.18]
    ];

    $tabelaFreteNordeste = [
        '0.3' => ['normal' => 62.90, 'desc50' => 31.45, 'desc25' => 47.18], // Corrected array keys
        '0.5' => ['normal' => 68.10, 'desc50' => 34.05, 'desc25' => 51.08],
        '1' => ['normal' => 72.10, 'desc50' => 36.05, 'desc25' => 54.08],
        '2' => ['normal' => 85.70, 'desc50' => 42.85, 'desc25' => 64.28],
        '3' => ['normal' => 110.80, 'desc50' => 55.40, 'desc25' => 83.10],
        '4' => ['normal' => 118.40, 'desc50' => 59.20, 'desc25' => 88.80],
        '5' => ['normal' => 123.60, 'desc50' => 61.80, 'desc25' => 92.70],
        '9' => ['normal' => 138.30, 'desc50' => 69.15, 'desc25' => 103.73],
        '13' => ['normal' => 189.80, 'desc50' => 94.90, 'desc25' => 142.35],
        '17' => ['normal' => 250.10, 'desc50' => 125.05, 'desc25' => 187.58],
        '23' => ['normal' => 281.10, 'desc50' => 140.55, 'desc25' => 210.83],
        '30' => ['normal' => 293.40, 'desc50' => 146.70, 'desc25' => 220.05],
        '40' => ['normal' => 294.90, 'desc50' => 147.45, 'desc25' => 221.18],
        '50' => ['normal' => 296.90, 'desc50' => 148.45, 'desc25' => 222.68],
        '60' => ['normal' => 300.90, 'desc50' => 150.45, 'desc25' => 225.68],
        '70' => ['normal' => 308.90, 'desc50' => 154.45, 'desc25' => 231.68],
        '80' => ['normal' => 311.90, 'desc50' => 155.95, 'desc25' => 233.93],
        '90' => ['normal' => 332.90, 'desc50' => 166.45, 'desc25' => 249.68],
        '100' => ['normal' => 364.90, 'desc50' => 182.45, 'desc25' => 273.68],
        '125' => ['normal' => 390.90, 'desc50' => 195.45, 'desc25' => 293.18],
        '150' => ['normal' => 416.90, 'desc50' => 208.45, 'desc25' => 312.68],
        '151+' => ['normal' => 546.10, 'desc50' => 273.05, 'desc25' => 409.58]
    ];

    // Determinar qual tabela usar com base na região
    $tabelaFrete = $regiao === 'sul' ? $tabelaFreteSul : $tabelaFreteNordeste; // Corrected string comparison

    // Determinar qual faixa de peso usar
    $faixaPeso = '151+'; // Valor padrão para pesos maiores
    $pesoKeys = array_keys($tabelaFrete);
    sort($pesoKeys, SORT_NUMERIC);

    foreach ($pesoKeys as $key) {
        if ($peso <= (float)$key) {
            $faixaPeso = $key;
            break;
        }
    }

    // Determinar coluna de preço com base no valor do produto e categoria
    // Esta lógica precisa ser revisada para refletir as regras de frete grátis
    $coluna = 'normal'; // Padrão para produtos < 79

    if ($precoVenda >= 79) {
        $coluna = 'desc50'; // 50% de desconto para produtos >= 79
    }

    if ($categoriaEspecial) {
        $coluna = 'desc25'; // 25% de desconto para categorias especiais
    }

    // Retorna o custo do frete para o vendedor
    // Se o frete é grátis para o comprador (>= R$ 79), o vendedor paga o valor com desconto (coluna 'desc50' ou 'desc25')
    // Se o frete NÃO é grátis para o comprador (< R$ 79), GERALMENTE o comprador paga o custo total.
    // NESTE CASO, o custo para o VENDEDOR seria 0.
    // ATENÇÃO: Esta lógica assume o cenário padrão. Se o vendedor oferece frete grátis
    // manualmente em anúncios abaixo de R$ 79, esta função precisaria ser ajustada
    // para buscar essa informação específica do anúncio ou receber um parâmetro adicional.
    if ($precoVenda >= 79) {
        // Frete Grátis Obrigatório (ou por reputação): Vendedor paga com desconto
        // Check if the weight key exists before accessing it
        if (isset($tabelaFrete[$faixaPeso][$coluna])) {
            return $tabelaFrete[$faixaPeso][$coluna];
        } else {
            // Handle cases where the weight key might not exist (e.g., '151+')
            error_log("calcularFreteNormal: Faixa de peso '{$faixaPeso}' ou coluna '{$coluna}' não encontrada na tabela de frete para preço >= 79.");
            return 0; // Retorna 0 em caso de erro na tabela, mas loga o problema.
        }
    } else {
        // Frete NÃO é grátis para o comprador (preço < R$ 79)
        // User reported error here: "Custo do Frete: para produtos abaixo de 79"
        // A lógica atual retorna 0, assumindo que o comprador paga 100% do frete e o vendedor 0.
        // Se as regras mudaram ou o vendedor oferece frete grátis opcionalmente,
        // esta parte precisa ser modificada. Por ora, mantemos 0.
        return 0; // Custo para o VENDEDOR é 0 neste cenário padrão.
    }
}

function calcularFreteSupermercado($peso, $regiao, $precoVenda, $categoriaEspecial, $tipoEnvio) {
    // Implementação simplificada para produtos de supermercado
    // Precisa verificar as regras exatas de frete para supermercado
    $valorBase = calcularFreteNormal($peso, $regiao, $precoVenda, $categoriaEspecial, $tipoEnvio);

    // Para supermercado, se for abaixo de 199, cobra frete normal? Ou o vendedor paga?
    // Se for acima, frete grátis (valor zero para o vendedor?)
    // Esta lógica precisa ser confirmada!
    if ($precoVenda >= 199) {
        return 0; // Assumindo frete grátis para o vendedor
    } else {
        // Abaixo de 199, o vendedor paga o frete normal?
        return $valorBase; // Precisa confirmar!
    }
}
?>
