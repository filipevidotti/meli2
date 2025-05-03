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

// Verificar se o vendedor já tem configurações do Mercado Livre
$ml_config = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM mercadolivre_config WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $ml_config = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro
}

// Verificar se o vendedor já tem um token do Mercado Livre
$ml_token = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM mercadolivre_tokens 
        WHERE usuario_id = ? 
        ORDER BY data_expiracao DESC 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $ml_token = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro
}

// Verificar se o token ainda é válido
$token_valido = false;
if (!empty($ml_token) && !empty($ml_token['data_expiracao'])) {
    $data_expiracao = new DateTime($ml_token['data_expiracao']);
    $agora = new DateTime();
    $token_valido = $agora < $data_expiracao;
}

// Verificar se está tentando autorizar
if (isset($_GET['code']) && isset($_SESSION['ml_auth_state'])) {
    $authorization_code = $_GET['code'];
    $state = $_GET['state'] ?? '';
    
    // Verificar se o state corresponde ao que foi enviado
    if ($state === $_SESSION['ml_auth_state']) {
        // Limpar o state da sessão
        unset($_SESSION['ml_auth_state']);
        
        // Verificar se temos as configurações necessárias
        if (!empty($ml_config['client_id']) && !empty($ml_config['client_secret'])) {
            // Trocar o código de autorização por um token de acesso
            $client_id = $ml_config['client_id'];
            $client_secret = $ml_config['client_secret'];
            $redirect_uri = $base_url . '/vendedor_mercadolivre.php';
            
            // Preparar os dados para a requisição
            $post_data = [
                'grant_type' => 'authorization_code',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'code' => $authorization_code,
                'redirect_uri' => $redirect_uri
            ];
            
            // Iniciar o cURL
            $ch = curl_init('https://api.mercadolibre.com/oauth/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']);
            
            // Executar a requisição
            $response = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Verificar se a requisição foi bem-sucedida
            if ($status_code == 200) {
                $token_data = json_decode($response, true);
                
                if (isset($token_data['access_token']) && isset($token_data['refresh_token'])) {
                    // Calcular a data de expiração
                    $agora = new DateTime();
                    $segundos_expiracao = $token_data['expires_in'] ?? 21600; // 6 horas por padrão
                    $data_expiracao = clone $agora;
                    $data_expiracao->add(new DateInterval("PT{$segundos_expiracao}S"));
                    
                    // Salvar o token no banco de dados
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO mercadolivre_tokens 
                            (usuario_id, access_token, refresh_token, data_criacao, data_expiracao) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $usuario_id,
                            $token_data['access_token'],
                            $token_data['refresh_token'],
                            $agora->format('Y-m-d H:i:s'),
                            $data_expiracao->format('Y-m-d H:i:s')
                        ]);
                        
                        // Buscar as informações do usuário do Mercado Livre
                        $ch = curl_init('https://api.mercadolibre.com/users/me');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Authorization: Bearer ' . $token_data['access_token']
                        ]);
                        
                        $user_response = curl_exec($ch);
                        $user_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($user_status_code == 200) {
                            $user_data = json_decode($user_response, true);
                            
                            // Salvar ou atualizar as informações do usuário
                            if (!empty($user_data['id'])) {
                                // Verificar se já existe registro
                                $stmt = $pdo->prepare("
                                    SELECT usuario_id FROM mercadolivre_usuarios 
                                    WHERE usuario_id = ? AND ml_user_id = ?
                                ");
                                $stmt->execute([$usuario_id, $user_data['id']]);
                                
                                if ($stmt->rowCount() > 0) {
                                    // Atualizar
                                    $stmt = $pdo->prepare("
                                        UPDATE mercadolivre_usuarios 
                                        SET ml_nickname = ?, ml_email = ?, ml_first_name = ?, 
                                            ml_last_name = ?, ml_country_id = ?, 
                                            ml_link = ?, atualizado_em = NOW() 
                                        WHERE usuario_id = ? AND ml_user_id = ?
                                    ");
                                    $stmt->execute([
                                        $user_data['nickname'] ?? '',
                                        $user_data['email'] ?? '',
                                        $user_data['first_name'] ?? '',
                                        $user_data['last_name'] ?? '',
                                        $user_data['country_id'] ?? '',
                                        $user_data['permalink'] ?? '',
                                        $usuario_id,
                                        $user_data['id']
                                    ]);
                                } else {
                                    // Inserir
                                    $stmt = $pdo->prepare("
                                        INSERT INTO mercadolivre_usuarios 
                                        (usuario_id, ml_user_id, ml_nickname, ml_email, ml_first_name, 
                                         ml_last_name, ml_country_id, ml_link, criado_em) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                                    ");
                                    $stmt->execute([
                                        $usuario_id,
                                        $user_data['id'],
                                        $user_data['nickname'] ?? '',
                                        $user_data['email'] ?? '',
                                        $user_data['first_name'] ?? '',
                                        $user_data['last_name'] ?? '',
                                        $user_data['country_id'] ?? '',
                                        $user_data['permalink'] ?? ''
                                    ]);
                                }
                            }
                        }
                        
                        $mensagem = "Autorização concluída com sucesso!";
                        $tipo_mensagem = "success";
                        
                        // Atualizar a informação do token
                        $stmt = $pdo->prepare("
                            SELECT * FROM mercadolivre_tokens 
                            WHERE usuario_id = ? 
                            ORDER BY data_expiracao DESC 
                            LIMIT 1
                        ");
                        $stmt->execute([$usuario_id]);
                        $ml_token = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Verificar se o token é válido
                        if (!empty($ml_token) && !empty($ml_token['data_expiracao'])) {
                            $data_expiracao = new DateTime($ml_token['data_expiracao']);
                            $agora = new DateTime();
                            $token_valido = $agora < $data_expiracao;
                        }
                        
                    } catch (PDOException $e) {
                        $mensagem = "Erro ao salvar token: " . $e->getMessage();
                        $tipo_mensagem = "danger";
                    }
                } else {
                    $mensagem = "Resposta inválida do servidor do Mercado Livre.";
                    $tipo_mensagem = "danger";
                }
            } else {
                $error_data = json_decode($response, true);
                $error_message = $error_data['message'] ?? 'Erro desconhecido';
                $mensagem = "Erro ao obter token: {$error_message}";
                $tipo_mensagem = "danger";
            }
        } else {
            $mensagem = "Configurações do Mercado Livre incompletas. Configure o Client ID e Client Secret primeiro.";
            $tipo_mensagem = "warning";
        }
    } else {
        $mensagem = "Erro de validação de segurança. Tente novamente.";
        $tipo_mensagem = "danger";
    }
}

// Processar ações
if (isset($_GET['acao'])) {
    $acao = $_GET['acao'];
    
    if ($acao === 'autorizar') {
        // Verificar se temos as configurações necessárias
        if (!empty($ml_config['client_id'])) {
            // Gerar um state aleatório para segurança
            $state = bin2hex(random_bytes(16));
            $_SESSION['ml_auth_state'] = $state;
            
            // Configurar a URL de autorização
            $client_id = $ml_config['client_id'];
            $redirect_uri = urlencode($base_url . '/vendedor_mercadolivre.php');
            $auth_url = "https://auth.mercadolivre.com.br/authorization?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uri}&state={$state}";
            
            // Redirecionar para a página de autorização
            header("Location: {$auth_url}");
            exit;
        } else {
            $mensagem = "Configurações do Mercado Livre incompletas. Configure o Client ID e Client Secret primeiro.";
            $tipo_mensagem = "warning";
        }
    } elseif ($acao === 'revogar' && !empty($ml_token['access_token'])) {
        // Revogar o token atual
        $access_token = $ml_token['access_token'];
        
        // Iniciar o cURL
        $ch = curl_init('https://api.mercadolibre.com/oauth/revoke_token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'token' => $access_token,
            'client_id' => $ml_config['client_id'],
            'client_secret' => $ml_config['client_secret']
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']);
        
        // Executar a requisição
        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Verificar se a requisição foi bem-sucedida
        if ($status_code == 200) {
            // Marcar o token como revogado no banco de dados
            try {
                $stmt = $pdo->prepare("
                    UPDATE mercadolivre_tokens 
                    SET revogado = 1, data_revogacao = NOW() 
                    WHERE usuario_id = ? AND access_token = ?
                ");
                $stmt->execute([$usuario_id, $access_token]);
                
                $mensagem = "Token revogado com sucesso!";
                $tipo_mensagem = "success";
                
                // Limpar as informações do token
                $ml_token = [];
                $token_valido = false;
                
            } catch (PDOException $e) {
                $mensagem = "Erro ao atualizar token: " . $e->getMessage();
                $tipo_mensagem = "danger";
            }
        } else {
            $error_data = json_decode($response, true);
            $error_message = $error_data['message'] ?? 'Erro desconhecido';
            $mensagem = "Erro ao revogar token: {$error_message}";
            $tipo_mensagem = "danger";
        }
    } elseif ($acao === 'sincronizar_anuncios' && $token_valido) {
        // Redirecionar para a página de sincronização
        header("Location: {$base_url}/vendedor_mercadolivre_sincronizar.php");
        exit;
    }
}

// Buscar informações do usuário do Mercado Livre
$ml_usuario = [];
if ($token_valido) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM mercadolivre_usuarios 
            WHERE usuario_id = ? 
            ORDER BY atualizado_em DESC 
            LIMIT 1
        ");
        $stmt->execute([$usuario_id]);
        $ml_usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Silenciar erro
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

// Função para formatar data/hora
function formatarDataHora($data) {
    if (empty($data)) return 'N/A';
    
    $dt = new DateTime($data);
    return $dt->format('d/m/Y H:i:s');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integração Mercado Livre - CalcMeli</title>
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
            width: 20px;
            text-align: center;
        }
        .main-content {
            margin-left: 240px;
            padding: 1.5rem;
        }
        .btn-warning, .bg-warning {
            background-color: #ff9a00 !important;
            border-color: #ff9a00;
        }
        .ml-logo {
            max-width: 200px;
            height: auto;
        }
        .integration-card {
            transition: transform 0.3s;
        }
        .integration-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">CalcMeli</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($usuario_nome); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/vendedor_config.php"><i class="fas fa-cog"></i> Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/login.php?logout=1"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_produtos.php">
                        <i class="fas fa-box"></i> Produtos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_anuncios.php">
                        <i class="fas fa-tags"></i> Anúncios ML
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_vendas.php">
                        <i class="fas fa-shopping-cart"></i> Vendas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_relatorios.php">
                        <i class="fas fa-chart-bar"></i> Relatórios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_config.php">
                        <i class="fas fa-cog"></i> Configurações
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Conteúdo principal -->
    <main class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2">Integração com Mercado Livre</h1>
            </div>

            <?php if (isset($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensagem); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm h-100 integration-card">
                        <div class="card-body text-center">
                            <img src="https://http2.mlstatic.com/frontend-assets/ui-navigation/5.19.1/mercadolibre/logo__large_plus.png" alt="Mercado Livre" class="ml-logo mb-4">
                            
                            <?php if (!$token_valido): ?>
                                <div class="d-grid gap-2">
                                    <a href="<?php echo $base_url; ?>/vendedor_config.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-cog"></i> Configurar API
                                    </a>
                                    <a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php?acao=autorizar" class="btn btn-warning">
                                        <i class="fas fa-plug"></i> Conectar com Mercado Livre
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> Conectado ao Mercado Livre
                                </div>
                                <div class="d-grid gap-2">
                                    <a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php?acao=sincronizar_anuncios" class="btn btn-warning">
                                        <i class="fas fa-sync"></i> Sincronizar Anúncios
                                    </a>
                                    <a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php?acao=revogar" class="btn btn-outline-danger" onclick="return confirm('Tem certeza que deseja desconectar sua conta do Mercado Livre?')">
                                        <i class="fas fa-unlink"></i> Desconectar
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Status da Integração</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($ml_config)): ?>
                                <div class="alert alert-warning">
                                    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Configuração Pendente</h5>
                                    <p class="mb-0">Para utilizar a integração com o Mercado Livre, você precisa configurar as credenciais da API.</p>
                                </div>
                                <p>Siga os passos abaixo para configurar sua integração:</p>
                                <ol>
                                    <li>Acesse a <a href="https://developers.mercadolivre.com.br/devcenter" target="_blank">página de desenvolvedores do Mercado Livre</a></li>
                                    <li>Faça login e crie um novo aplicativo</li>
                                    <li>Copie o Client ID e Client Secret do seu aplicativo</li>
                                    <li>Configure esses dados na <a href="<?php echo $base_url; ?>/vendedor_config.php">página de configurações</a></li>
                                </ol>
                            <?php else: ?>
                                <?php if ($token_valido && !empty($ml_usuario)): ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-muted mb-3">Informações do Usuário</h6>
                                            <table class="table table-sm">
                                                <tr>
                                                    <th>ID Mercado Livre:</th>
                                                    <td><?php echo htmlspecialchars($ml_usuario['ml_user_id'] ?? 'N/A'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Nickname:</th>
                                                    <td><?php echo htmlspecialchars($ml_usuario['ml_nickname'] ?? 'N/A'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Nome:</th>
                                                    <td><?php 
                                                        $nome_completo = trim(($ml_usuario['ml_first_name'] ?? '') . ' ' . ($ml_usuario['ml_last_name'] ?? ''));
                                                        echo htmlspecialchars($nome_completo ?: 'N/A'); 
                                                    ?></td>
                                                </tr>
                                                <tr>
                                                    <th>País:</th>
                                                    <td><?php echo htmlspecialchars($ml_usuario['ml_country_id'] ?? 'N/A'); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Perfil:</th>
                                                    <td>
                                                        <?php if (!empty($ml_usuario['ml_link'])): ?>
                                                            <a href="<?php echo htmlspecialchars($ml_usuario['ml_link']); ?>" target="_blank">
                                                                Abrir perfil <i class="fas fa-external-link-alt"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-muted mb-3">Status do Token</h6>
                                            <table class="table table-sm">
                                                <tr>
                                                    <th>Data de Criação:</th>
                                                    <td><?php echo formatarDataHora($ml_token['data_criacao'] ?? ''); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Expira em:</th>
                                                    <td><?php echo formatarDataHora($ml_token['data_expiracao'] ?? ''); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Status:</th>
                                                    <td>
                                                        <?php if ($token_valido): ?>
                                                            <span class="badge bg-success">Válido</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Expirado</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <h5 class="alert-heading"><i class="fas fa-info-circle"></i> Autorização Pendente</h5>
                                        <p>Você já configurou as credenciais da API, mas ainda precisa autorizar o aplicativo no Mercado Livre.</p>
                                        <hr>
                                        <p class="mb-0">Clique no botão "Conectar com Mercado Livre" para autorizar o acesso.</p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
       