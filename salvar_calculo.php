<?php
// salvar_calculo.php
require_once('init.php');

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Método não permitido');
}

// Obter dados JSON da requisição
$data = json_decode(file_get_contents('php://input'), true);

// Verificar dados
$response = ['success' => false, 'message' => ''];

if (!$data) {
    $response['message'] = 'Dados inválidos.';
    echo json_encode($response);
    exit;
}

// Extrair dados do cálculo
$calculo_id = isset($data['calculo_id']) ? (int)$data['calculo_id'] : null;
$produto_id = isset($data['produto_id']) && $data['produto_id'] ? (int)$data['produto_id'] : null;
$anuncio_id = isset($data['anuncio_id']) && $data['anuncio_id'] ? (int)$data['anuncio_id'] : null;
$nome_produto = isset($data['nome_produto']) ? trim($data['nome_produto']) : '';
$preco_venda = isset($data['preco_venda']) ? floatval($data['preco_venda']) : 0;
$custo_produto = isset($data['custo_produto']) ? floatval($data['custo_produto']) : 0;
$despesas_extras = isset($data['despesas_extras']) ? floatval($data['despesas_extras']) : 0;
$peso = isset($data['peso']) ? floatval($data['peso']) : null;
$tipo_anuncio = isset($data['tipo_anuncio']) ? $data['tipo_anuncio'] : 'classico';
$regiao_envio = isset($data['regiao_envio']) ? $data['regiao_envio'] : 'sul_sudeste';
$produto_full = isset($data['produto_full']) ? (bool)$data['produto_full'] : false;
$categoria_especial = isset($data['categoria_especial']) ? (bool)$data['categoria_especial'] : false;

// Validar dados
if (empty($nome_produto)) {
    $response['message'] = 'O nome do produto é obrigatório.';
    echo json_encode($response);
    exit;
}

if ($preco_venda <= 0) {
    $response['message'] = 'O preço de venda deve ser maior que zero.';
    echo json_encode($response);
    exit;
}

if ($custo_produto < 0) {
    $response['message'] = 'O custo do produto não pode ser negativo.';
    echo json_encode($response);
    exit;
}

try {
    // Calcular taxa do Mercado Livre
    $taxa_percentual = ($tipo_anuncio === 'premium') ? 16 : 12;
    $taxa_ml = ($taxa_percentual / 100) * $preco_venda;
    
    // Calcular lucro
    $lucro = $preco_venda - $custo_produto - $taxa_ml - $despesas_extras;
    
    // Calcular rentabilidade (margem de lucro percentual)
    $rentabilidade = ($preco_venda > 0) ? ($lucro / $preco_venda) * 100 : 0;
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    if ($calculo_id) {
        // Verificar se o cálculo pertence ao usuário atual
        $sql = "SELECT id FROM calculos_lucro WHERE id = ? AND usuario_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$calculo_id, $_SESSION['user_id']]);
        $existente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existente) {
            $response['message'] = 'Cálculo não encontrado ou não pertence ao usuário atual.';
            echo json_encode($response);
            exit;
        }
        
        // Atualizar cálculo existente
        $sql = "UPDATE calculos_lucro SET 
                produto_id = ?,
                anuncio_id = ?,
                nome_produto = ?,
                preco_venda = ?,
                custo_produto = ?,
                despesas_extras = ?,
                peso = ?,
                tipo_anuncio = ?,
                regiao_envio = ?,
                produto_full = ?,
                categoria_especial = ?,
                taxa_ml = ?,
                lucro = ?,
                rentabilidade = ?
                WHERE id = ? AND usuario_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $produto_id,
            $anuncio_id,
            $nome_produto,
            $preco_venda,
            $custo_produto,
            $despesas_extras,
            $peso,
            $tipo_anuncio,
            $regiao_envio,
            $produto_full ? 1 : 0,
            $categoria_especial ? 1 : 0,
            $taxa_ml,
            $lucro,
            $rentabilidade,
            $calculo_id,
            $_SESSION['user_id']
        ]);
        
        $response['id'] = $calculo_id;
    } else {
        // Inserir novo cálculo
        $sql = "INSERT INTO calculos_lucro (
                usuario_id, produto_id, anuncio_id, nome_produto, preco_venda, 
                custo_produto, despesas_extras, peso, tipo_anuncio, regiao_envio, 
                produto_full, categoria_especial, taxa_ml, lucro, rentabilidade
                ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_SESSION['user_id'],
            $produto_id,
            $anuncio_id,
            $nome_produto,
            $preco_venda,
            $custo_produto,
            $despesas_extras,
            $peso,
            $tipo_anuncio,
            $regiao_envio,
            $produto_full ? 1 : 0,
            $categoria_especial ? 1 : 0,
            $taxa_ml,
            $lucro,
            $rentabilidade
        ]);
        
        $response['id'] = $pdo->lastInsertId();
    }
    
    // Confirmar transação
    $pdo->commit();
    
    // Se fornecido um produto_id, mas não um anuncio_id, verificar se existe um anúncio para vincular
    if ($produto_id && !$anuncio_id) {
        // Buscar anúncios não vinculados com título similar ao produto
        $sql = "SELECT a.id FROM anuncios_ml a 
                WHERE a.usuario_id = ? 
                AND a.produto_id IS NULL 
                AND LOWER(a.titulo) LIKE LOWER(?)
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id'], '%' . $nome_produto . '%']);
        $anuncio_match = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($anuncio_match) {
            // Vincular anúncio ao produto
            $sql = "UPDATE anuncios_ml SET produto_id = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$produto_id, $anuncio_match['id']]);
            
            // Atualizar o cálculo com o anúncio vinculado
            $sql = "UPDATE calculos_lucro SET anuncio_id = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$anuncio_match['id'], $response['id']]);
            
            $response['anuncio_vinculado'] = true;
        }
    }
    
    $response['success'] = true;
    $response['message'] = 'Cálculo salvo com sucesso!';
    
} catch (PDOException $e) {
    // Em caso de erro, reverter transação
    $pdo->rollBack();
    
    $response['message'] = 'Erro ao salvar cálculo: ' . $e->getMessage();
}

// Enviar resposta JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
