<?php
// Este arquivo processa os cálculos quando enviado via AJAX
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Recebe dados do formulário
$data = [
    'preco_custo' => floatval($_POST['preco_custo'] ?? 0),
    'custo_embalagem' => floatval($_POST['custo_embalagem'] ?? 0),
    'custo_etiquetas' => floatval($_POST['custo_etiquetas'] ?? 0),
    'frete_fornecedor' => floatval($_POST['frete_fornecedor'] ?? 0),
    'outros_custos' => floatval($_POST['outros_custos'] ?? 0),
    'preco_venda' => floatval($_POST['preco_venda'] ?? 0),
    'desconto' => floatval($_POST['desconto'] ?? 0),
    'taxa_categoria' => floatval($_POST['taxa_categoria'] ?? 0),
    'taxa_anuncio' => floatval($_POST['taxa_anuncio'] ?? 0),
    'parcelas' => intval($_POST['parcelas'] ?? 1),
    'taxa_parcelas' => floatval($_POST['taxa_parcelas'] ?? 0),
    'peso' => floatval($_POST['peso'] ?? 0),
    'custo_frete' => floatval($_POST['custo_frete'] ?? 0),
    'subsidio_frete' => floatval($_POST['subsidio_frete'] ?? 0),
];

// Cálculos
$custo_total = $data['preco_custo'] + $data['custo_embalagem'] + $data['custo_etiquetas'] + 
               $data['frete_fornecedor'] + $data['outros_custos'];

$preco_final = $data['preco_venda'] - $data['desconto'];

$taxa_venda_valor = $preco_final * ($data['taxa_categoria'] / 100);
$taxa_mp = $preco_final * 0.045; // 4.5% Mercado Pago
$taxa_parcelas_valor = $preco_final * ($data['taxa_parcelas'] / 100);

$total_taxas = $taxa_venda_valor + $data['taxa_anuncio'] + $taxa_mp + $taxa_parcelas_valor;

$custo_frete_final = $data['custo_frete'] - $data['subsidio_frete'];

$total_taxas_custos = $custo_total + $total_taxas + $custo_frete_final;

$lucro_liquido = $preco_final - $total_taxas_custos;
$margem_lucro = ($lucro_liquido / $preco_final) * 100;
$roi = ($lucro_liquido / $custo_total) * 100;

// Cálculo do ponto de equilíbrio
if (($preco_final - $total_taxas - $custo_frete_final) > 0) {
    $ponto_equilibrio = ceil($custo_total / ($preco_final - $total_taxas - $custo_frete_final));
} else {
    $ponto_equilibrio = "N/A";
}

// Avaliação da margem
$avaliacao = '';
$classe_alerta = '';

if ($margem_lucro < 0) {
    $avaliacao = 'PREJUÍZO! Você está perdendo dinheiro em cada venda.';
    $classe_alerta = 'alert-danger';
} elseif ($margem_lucro < 15) {
    $avaliacao = 'ATENÇÃO! Sua margem está muito baixa. Considere aumentar o preço ou reduzir custos.';
    $classe_alerta = 'alert-warning';
} elseif ($margem_lucro < 30) {
    $avaliacao = 'RAZOÁVEL. Sua margem está aceitável, mas há espaço para melhoria.';
    $classe_alerta = 'alert-info';
} else {
    $avaliacao = 'EXCELENTE! Sua margem está saudável. Continue com esta estratégia.';
    $classe_alerta = 'alert-success';
}

// Formata para retornar valores em formato brasileiro
function formatarMoeda($valor) {
    return number_format($valor, 2, '.', '');
}

// Resposta
$response = [
    'custo_total' => formatarMoeda($custo_total),
    'preco_final' => formatarMoeda($preco_final),
    'taxa_venda_valor' => formatarMoeda($taxa_venda_valor),
    'taxa_mp' => formatarMoeda($taxa_mp),
    'taxa_parcelas_valor' => formatarMoeda($taxa_parcelas_valor),
    'total_taxas' => formatarMoeda($total_taxas),
    'custo_frete_final' => formatarMoeda($custo_frete_final),
    'receita_bruta' => formatarMoeda($preco_final),
    'total_taxas_custos' => formatarMoeda($total_taxas_custos),
    'lucro_liquido' => formatarMoeda($lucro_liquido),
    'margem_lucro' => round($margem_lucro, 2),
    'roi' => round($roi, 2),
    'ponto_equilibrio' => $ponto_equilibrio,
    'avaliacao' => $avaliacao,
    'classe_alerta' => $classe_alerta,
];

echo json_encode($response);
exit;

?>