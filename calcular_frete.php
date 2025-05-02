<?php
// Arquivo para cálculo de frete via AJAX
header('Content-Type: application/json');
include_once 'includes/tabela_fretes.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

// Recebe dados do formulário
$peso = floatval($_POST['peso'] ?? 0);
$regiao_origem = $_POST['regiao_origem'] ?? 'sul_sudeste';
$preco_final = floatval($_POST['preco_final'] ?? 0);
$is_full = intval($_POST['is_full'] ?? 0) == 1;
$is_categoria_especial = intval($_POST['is_categoria_especial'] ?? 0) == 1;
$estado_produto = $_POST['estado_produto'] ?? 'novo';

// Calcula o frete com base nos parâmetros
$valor_frete = calcularFrete($peso, $regiao_origem, $preco_final, $is_full, $is_categoria_especial, $estado_produto);

// Prepara o texto de detalhes do frete
$detalhes = 'Frete calculado para: ';
$detalhes .= $regiao_origem == 'sul_sudeste' ? 'Sul/Sudeste/Centro-Oeste' : 'Norte/Nordeste';
$detalhes .= ', Produto ' . ($estado_produto == 'novo' ? 'novo' : 'usado');
$detalhes .= ', ' . ($is_full ? 'Mercado Full' : 'Envio normal');
$detalhes .= ', Faixa de preço: ' . ($preco_final < 79 || $estado_produto == 'usado' ? 'Abaixo de R$79' : 'Acima de R$79');
if ($is_categoria_especial && $estado_produto == 'novo') {
    $detalhes = 'Frete calculado para Categoria Especial, ' . ($is_full ? 'Mercado Full' : 'Envio normal');
}

// Resposta
echo json_encode([
    'success' => true,
    'frete' => $valor_frete,
    'detalhes' => $detalhes
]);
exit;
?>