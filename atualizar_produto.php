<?php
header('Content-Type: application/json');
include_once 'includes/conexao.php';
include_once 'includes/tabela_fretes.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    // Conectar ao banco de dados
    global $conn;
    
    // Receber dados do formulário
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        throw new Exception("ID de produto inválido");
    }
    
    // Dados básicos
    $nome = $_POST['nome_produto'] ?? '';
    $sku = $_POST['sku'] ?? '';
    $preco_venda = floatval($_POST['preco_venda'] ?? 0);
    $custo = floatval($_POST['preco_custo'] ?? 0);
    $categoria_id = $_POST['categoria'] ?? '';
    $categoria_nome = $_POST['categoria_nome'] ?? '';
    $taxa_categoria = floatval($_POST['taxa_categoria'] ?? 0);
    $tipo_anuncio = $_POST['tipo_anuncio'] ?? 'classico';
    $is_supermercado = isset($_POST['is_supermercado']) ? 1 : 0;
    $is_categoria_especial = isset($_POST['is_categoria_especial']) ? 1 : 0;
    $is_full = isset($_POST['is_full']) ? 1 : 0;
    $peso = floatval($_POST['peso'] ?? 0);
    $regiao_origem = $_POST['regiao_origem'] ?? 'sul_sudeste';
    $estado_produto = $_POST['estado_produto'] ?? 'novo';
    $notas = $_POST['notas'] ?? '';
    
    // Calcular custo fixo
    $custo_fixo = 0;
    if ($is_supermercado) {
        if ($preco_venda < 20) {
            $custo_fixo = 3.49;
        } else if ($preco_venda < 40) {
            $custo_fixo = 4.49;
        } else if ($preco_venda < 60) {
            $custo_fixo = 5.49;
        } else if ($preco_venda < 80) {
            $custo_fixo = 7.49;
        } else if ($preco_venda < 120) {
            $custo_fixo = 10.49;
        } else if ($preco_venda < 200) {
            $custo_fixo = 17.49;
        } else {
            $custo_fixo = 35.49;
        }
    } else {
        if ($preco_venda < 29) {
            $custo_fixo = 6.25;
        } else if ($preco_venda < 50) {
            $custo_fixo = 6.50;
        } else if ($preco_venda < 79) {
            $custo_fixo = 6.75;
        }
    }
    
    // Calcular valor do frete
    $valor_frete = calcularFrete($peso, $regiao_origem, $preco_venda, $is_full, $is_categoria_especial, $estado_produto);
    
    // Calcular taxas
    $taxa_venda = $preco_venda * ($taxa_categoria / 100);
    $taxa_mp = $preco_venda * 0.045; // 4.5% Mercado Pago
    $taxa_anuncio = ($tipo_anuncio == 'premium') ? 5 : 0;
    
    $total_taxas = $taxa_venda + $taxa_mp + $taxa_anuncio + $custo_fixo;
    
    // Calcular lucro e margens
    $total_custos = $custo + $total_taxas + $valor_frete;
    $lucro = $preco_venda - $total_custos;
    $margem = ($preco_venda > 0) ? ($lucro / $preco_venda) * 100 : 0;
    $roi = ($custo > 0) ? ($lucro / $custo) * 100 : 0;
    
    // Atualizar registro no banco de dados
    $sql = "UPDATE produtos SET 
            nome = :nome,
            sku = :sku,
            preco_venda = :preco_venda,
            custo = :custo,
            categoria_id = :categoria_id,
            categoria_nome = :categoria_nome,
            taxa_categoria = :taxa_categoria,
            tipo_anuncio = :tipo_anuncio,
            is_supermercado = :is_supermercado,
            is_categoria_especial = :is_categoria_especial,
            is_full = :is_full,
            peso = :peso,
            regiao_origem = :regiao_origem,
            estado_produto = :estado_produto,
            custo_fixo = :custo_fixo,
            valor_frete = :valor_frete,
            total_taxas = :total_taxas,
            lucro = :lucro,
            margem = :margem,
            roi = :roi,
            notas = :notas
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':sku', $sku);
    $stmt->bindParam(':preco_venda', $preco_venda);
    $stmt->bindParam(':custo', $custo);
    $stmt->bindParam(':categoria_id', $categoria_id);
    $stmt->bindParam(':categoria_nome', $categoria_nome);
    $stmt->bindParam(':taxa_categoria', $taxa_categoria);
    $stmt->bindParam(':tipo_anuncio', $tipo_anuncio);
    $stmt->bindParam(':is_supermercado', $is_supermercado);
    $stmt->bindParam(':is_categoria_especial', $is_categoria_especial);
    $stmt->bindParam(':is_full', $is_full);
    $stmt->bindParam(':peso', $peso);
    $stmt->bindParam(':regiao_origem', $regiao_origem);
    $stmt->bindParam(':estado_produto', $estado_produto);
    $stmt->bindParam(':custo_fixo', $custo_fixo);
    $stmt->bindParam(':valor_frete', $valor_frete);
    $stmt->bindParam(':total_taxas', $total_taxas);
    $stmt->bindParam(':lucro', $lucro);
    $stmt->bindParam(':margem', $margem);
    $stmt->bindParam(':roi', $roi);
    $stmt->bindParam(':notas', $notas);
    $stmt->bindParam(':id', $id);
    
    $stmt->execute();
    
    // Recalcular faturamento baseado nas unidades vendidas
    $sql = "SELECT unidades_vendidas FROM produtos WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $produto = $stmt->fetch();
    
    if ($produto) {
        $faturado = $produto['unidades_vendidas'] * $preco_venda;
        
        $sql = "UPDATE produtos SET faturado = :faturado WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':faturado', $faturado);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Produto atualizado com sucesso!'
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>