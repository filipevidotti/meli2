<?php
// calcular_lucro.php
require_once('init.php');

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Método não permitido');
}

// Obter dados do formulário
$produto_id = isset($_POST['produto_id']) ? (int)$_POST['produto_id'] : null;
$anuncio_id = isset($_POST['anuncio_id']) ? (int)$_POST['anuncio_id'] : null;
$nome_produto = isset($_POST['nome_produto']) ? trim($_POST['nome_produto']) : '';
$preco_venda = isset($_POST['preco_venda']) ? floatval($_POST['preco_venda']) : 0;
$custo_produto = isset($_POST['custo_produto']) ? floatval($_POST['custo_produto']) : 0;
$despesas_extras = isset($_POST['despesas_extras']) ? floatval($_POST['despesas_extras']) : 0;
$peso = isset($_POST['peso']) ? floatval($_POST['peso']) : 0;
$tipo_anuncio = isset($_POST['tipo_anuncio']) ? $_POST['tipo_anuncio'] : 'classico';
$regiao_envio = isset($_POST['regiao_envio']) ? $_POST['regiao_envio'] : 'sul_sudeste';
$produto_full = isset($_POST['produto_full']) ? (bool)$_POST['produto_full'] : false;
$categoria_especial = isset($_POST['categoria_especial']) ? (bool)$_POST['categoria_especial'] : false;

// Validar dados
$response = ['success' => false, 'message' => ''];

if (empty($nome_produto)) {
    $response['message'] = 'O nome do produto é obrigatório.';
    echo json_encode($response);
    exit;
}

if ($preco_venda < 6) {
    $response['message'] = 'O preço de venda deve ser no mínimo R$ 6,00.';
    echo json_encode($response);
    exit;
}

if ($custo_produto < 0) {
    $response['message'] = 'O custo do produto não pode ser negativo.';
    echo json_encode($response);
    exit;
}

// Calcular taxa do Mercado Livre
$taxa_percentual = ($tipo_anuncio === 'premium') ? 16 : 12;
$taxa_ml = ($taxa_percentual / 100) * $preco_venda;

// Calcular lucro
$lucro = $preco_venda - $custo_produto - $taxa_ml - $despesas_extras;

// Calcular rentabilidade (margem de lucro percentual)
$rentabilidade = ($preco_venda > 0) ? ($lucro / $preco_venda) * 100 : 0;

// Preparar resposta
$response['success'] = true;
$response['preco_venda'] = formatCurrency($preco_venda);
$response['taxa_ml'] = formatCurrency($taxa_ml);
$response['custo_produto'] = formatCurrency($custo_produto);
$response['despesas_extras'] = formatCurrency($despesas_extras);
$response['lucro'] = formatCurrency($lucro);
$response['lucro_class'] = ($lucro >= 0) ? 'text-success' : 'text-danger';
$response['rentabilidade'] = formatPercentage($rentabilidade);
$response['rentabilidade_class'] = ($rentabilidade >= 0) ? 'text-success' : 'text-danger';

// Armazenar temporariamente o cálculo na sessão para eventual salvamento posterior
$_SESSION['ultimo_calculo'] = [
    'produto_id' => $produto_id,
    'anuncio_id' => $anuncio_id,
    'nome_produto' => $nome_produto,
    'preco_venda' => $preco_venda,
    'custo_produto' => $custo_produto,
    'despesas_extras' => $despesas_extras,
    'peso' => $peso,
    'tipo_anuncio' => $tipo_anuncio,
    'regiao_envio' => $regiao_envio,
    'produto_full' => $produto_full ? 1 : 0,
    'categoria_especial' => $categoria_especial ? 1 : 0,
    'taxa_ml' => $taxa_ml,
    'lucro' => $lucro,
    'rentabilidade' => $rentabilidade
];

// Enviar resposta JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>