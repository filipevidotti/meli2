<?php
// Incluir arquivos necessários
require_once 'ml_config.php';
require_once 'ml_vendas_api.php';
require_once 'ml_curva_abc.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Location: login.php");
    exit;
}

// Dados básicos
$base_url = 'https://www.annemacedo.com.br/novo2';
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

// Buscar token válido
$ml_token = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM mercadolivre_tokens 
        WHERE usuario_id = ? AND revogado = 0 AND data_expiracao > NOW()
        ORDER BY data_expiracao DESC 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $ml_token = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erro ao buscar token: " . $e->getMessage();
}

// Verificar se tem token válido
if (empty($ml_token) || empty($ml_token['access_token'])) {
    $error_message = "Você não está conectado ao Mercado Livre ou seu token expirou. Por favor, faça a autenticação novamente.";
}

// Inicializar variáveis
$access_token = $ml_token['access_token'] ?? '';
$produtos_abc = [];
$estatisticas = [];
$valor_total_vendas = 0;

// Definir data inicial e final padrão
$data_hoje = date('Y-m-d');
$data_inicio = date('Y-m-d', strtotime('-30 days'));
$data_fim = $data_hoje;

// Processar formulário de filtro
if (isset($_POST['filtrar'])) {
    $data_inicio = $_POST['data_inicio'] ?? $data_inicio;
    $data_fim = $_POST['data_fim'] ?? $data_fim;
    
    // Validar datas
    if (strtotime($data_inicio) > strtotime($data_fim)) {
        $error_message = "Data inicial não pode ser maior que a data final.";
    } else {
        // Buscar vendas e gerar análise ABC
        try {
            // Exibir mensagem de carregamento
            $loading_message = "Buscando dados de vendas do Mercado Livre...";
            
            // Buscar vendas
            $produtos = buscarVendasML($access_token, $data_inicio, $data_fim);
            
            // Verificar se houve erro
            if (isset($produtos['error'])) {
                $error_message = $produtos['error'];
            } else {
                // Fazer análise ABC
                $resultado_abc = analiseCurvaABC($produtos);
                $produtos_abc = $resultado_abc['produtos'];
                $estatisticas = $resultado_abc['estatisticas'];
                $valor_total_vendas = $resultado_abc['valor_total_vendas'];
                
                $success_message = "Análise ABC gerada com sucesso!";
            }
        } catch (Exception $e) {
            $error_message = "Erro ao processar dados: " . $e->getMessage();
        }
    }
}

// Função auxiliar para formatar valor monetário
function formatarValor($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise de Curva ABC - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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
            border-left: 4px solid #ff9<span class="cursor">█</span>