<?php
// Iniciar sessão
session_start();

// Verificar acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Location: login.php");
    exit;
}

// Dados básicos
$base_url = 'http://www.annemacedo.com.br/novo2';
$usuario_id = $_SESSION['user_id'];
$usuario_nome = $_SESSION['user_name'] ?? 'Vendedor';

// Conectar ao banco de dados
$db_host = 'mysql.annemacedo.com.br';
$db_name = 'annemacedo02';
$db_user = 'annemacedo02';
$db_pass = 'Vingador13Anne';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Processar exclusão de produto se solicitado
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $produto_id = (int)$_GET['excluir'];
    
    try {
        // Verificar se o produto pertence ao vendedor
        $stmt = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$produto_id, $usuario_id]);
        
        if ($stmt->rowCount() > 0) {
            // Remover vinculações com anúncios
            $stmt = $pdo->prepare("UPDATE anuncios_ml SET produto_id = NULL WHERE produto_id = ?");
            $stmt->execute([$produto_id]);
            
            // Excluir o produto
            $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
            $stmt->execute([$produto_id]);
            
            $mensagem = "Produto excluído com sucesso!";
            $tipo_mensagem = "success";
        } else {
            $mensagem = "Produto não encontrado ou você não tem permissão para excluí-lo.";
            $tipo_mensagem = "danger";
        }
    } catch (PDOException $e) {
        $mensagem = "Erro ao excluir produto: " . $e->getMessage();
        $tipo_mensagem = "danger";
    }
}

// Exibir mensagem de sessão, se existir
if (isset($_SESSION['mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'] ?? 'info';
    
    // Limpar a mensagem da sessão
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}

// Preparar paginação
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$itens_por_pagina = 10;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Preparar filtros de busca
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$filtro_categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';

// Construir a consulta SQL com os filtros
$sql_where = "WHERE usuario_id = ?";
$parametros = [$usuario_id];

if (!empty($busca)) {
    $sql_where .= " AND (nome LIKE ? OR sku LIKE ?)";
    $parametros[] = "%{$busca}%";
    $parametros[] = "%{$busca}%";
}

if (!empty($filtro_categoria)) {
    $sql_where .= " AND categoria_id = ?";
    $parametros[] = $filtro_categoria;
}

// Contar total de registros para paginação
$sql_count = "SELECT COUNT(*) FROM produtos {$sql_where}";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($parametros);
$total_registros = $stmt->fetchColumn();

$total_paginas = ceil($total_registros / $itens_por_pagina);

// Buscar produtos do vendedor
$sql = "SELECT p.*, 
               (SELECT COUNT(*) FROM anuncios_ml a WHERE a.produto_id = p.id) as total_anuncios,
               (SELECT SUM(quantidade) FROM vendas v WHERE v.produto_id = p.id) as total_vendas
        FROM produtos p
        {$sql_where}
        ORDER BY p.nome
        LIMIT {$itens_por_pagina} OFFSET {$offset}";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar todas as categorias para o filtro
$categorias = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT categoria_id, 
                           (SELECT nome FROM categorias_ml WHERE id = p.categoria_id) as nome
                         FROM produtos p
                         WHERE p.usuario_id = {$usuario_id} AND p.categoria_id IS NOT NULL
                         ORDER BY nome");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro, usar array vazio
}

function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Produtos - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding-top: 56px;
        }
        .sidebar {
            position: fixed;
            top: 56px;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #343a40;
            width: 240px;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 56px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            font-weight: 500;
            color: #ced4da;
            padding: .75rem 1rem;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255,255,255,.1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,.1);
            border-left: 4px solid #ff9a00;
        }
        .sidebar .nav-link i {
            margin-right: 10px;