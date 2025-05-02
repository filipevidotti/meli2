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

// Obter ID do vendedor
$vendedor_id = 0;
try {
    $stmt = $pdo->prepare("SELECT id FROM vendedores WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $vendedor = $stmt->fetch();
    
    if ($vendedor) {
        $vendedor_id = $vendedor['id'];
    } else {
        // Criar vendedor automaticamente
        $stmt = $pdo->prepare("INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)");
        $stmt->execute([$usuario_id, $usuario_nome]);
        $vendedor_id = $pdo->lastInsertId();
    }
} catch (PDOException $e) {
    error_log("Erro ao verificar vendedor: " . $e->getMessage());
}

// Verificar filtros
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // Início do mês atual
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Hoje
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Processar exclusão de venda
$mensagem = '';
$tipo_mensagem = '';

if (isset($_POST['delete_venda']) && !empty($_POST['venda_id'])) {
    try {
        $venda_id = intval($_POST['venda_id']);
        
        // Verificar se a venda pertence ao vendedor
        $stmt = $pdo->prepare("SELECT id FROM vendas WHERE id = ? AND vendedor_id = ?");
        $stmt->execute([$venda_id, $vendedor_id]);
        
        if ($stmt->fetch()) {
            // Excluir a venda
            $stmt = $pdo->prepare("DELETE FROM vendas WHERE id = ?");
            $stmt->execute([$venda_id]);
            
            $mensagem = "Venda excluída com sucesso!";
            $tipo_mensagem = "success";
        } else {
            $mensagem = "Você não tem permissão para excluir esta venda.";
            $tipo_mensagem = "danger";
        }
    } catch (PDOException $e) {
        $mensagem = "Erro ao excluir venda: " . $e->getMessage();
        $tipo_mensagem = "danger";
    }
}

// Mostrar mensagens de sessão
if (isset($_SESSION['mensagem']) && isset($_SESSION['tipo_mensagem'])) {
    $mensagem = $_SESSION['mensagem'];
    $tipo_mensagem = $_SESSION['tipo_mensagem'];
    unset($_SESSION['mensagem']);
    unset($_SESSION['tipo_mensagem']);
}

// Paginação
$pagina_atual = isset($_GET['page']) ? intval($_GET['page']) : 1;
$por_pagina = 15;
$offset = ($pagina_atual - 1) * $por_pagina;

// Buscar vendas
$vendas = [];
$total_vendas = 0;

try {
    // Consulta para contagem total
    $sql_count = "SELECT COUNT(*) FROM vendas WHERE vendedor_id = ? AND data_venda BETWEEN ? AND ?";
    $params = [$vendedor_id, $start_date, $end_date];
    
    // Adicionar filtro de busca se existir
    if (!empty($search)) {
        $sql_count .= " AND produto LIKE ?";
        $params[] = "%$search%";
    }
    
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total_vendas = $stmt_count->fetchColumn();
    
    // Calcular número total de páginas
    $total_paginas = ceil($total_vendas / $por_pagina);
    
    // Consulta principal
    $sql = "SELECT * FROM vendas WHERE vendedor_id = ? AND data_venda BETWEEN ? AND ?";
    
    // Adicionar filtro de busca se existir
    if (!empty($search)) {
        $sql .= " AND produto LIKE ?";
    }
    
    $sql .= " ORDER BY data_venda DESC, id DESC LIMIT $offset, $por_pagina";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vendas = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar vendas: " . $e->getMessage());
    $mensagem = "Erro ao buscar vendas: " . $e->getMessage();
    $tipo_mensagem = "danger";
}

// Buscar resumo do período
$resumo = [
    'total_vendas' => 0,
    'total_lucro' => 0,
    'media_margem' => 0,
    'num_vendas' => 0
];

try {
    $sql = "SELECT 
            SUM(valor_venda) as total_vendas,
            SUM(lucro) as total_lucro,
            AVG(margem_lucro) as media_margem,
            COUNT(*) as num_vendas
            FROM vendas 
            WHERE vendedor_id = ? AND data_venda BETWEEN ? AND ?";
    
    $params = [$vendedor_id, $start_date, $end_date];
    
    // Adicionar filtro de busca se existir
    if (!empty($search)) {
        $sql .= " AND produto LIKE ?";
        $params[] = "%$search%";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    if ($result) {
        $resumo = $result;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar resumo: " . $e->getMessage());
}

// Funções de formatação
function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function formatPercentage($value) {
    return number_format((float)$value, 2, ',', '.') . '%';
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendas - CalcMeli</title>
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
            bottom: