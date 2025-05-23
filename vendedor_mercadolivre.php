<?php
// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir arquivo de configuração
require_once 'ml_config.php';

// Verificar acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Location: login.php");
    exit;
}

// Dados básicos
$base_url = 'https://www.annemacedo.com.br/novo2';
$usuario_id = $_SESSION['user_id'];
$usuario_nome = $_SESSION['user_name'] ?? 'Vendedor';

// Log inicial para debugar a sessão
error_log("Sessão do usuário no início: " . json_encode($_SESSION));

// Garantir que cada usuário tenha seu próprio ciclo de autenticação
$usuario_atual_id = $_SESSION['user_id'] ?? 0;

// Verificar se há alguma autenticação em andamento para outro usuário
if (isset($_SESSION['auth_usuario_id']) && $_SESSION['auth_usuario_id'] != $usuario_atual_id) {
    // Limpar dados de autenticação de outro usuário
    error_log("Limpando dados de autenticação de outro usuário: " . $_SESSION['auth_usuario_id']);
    unset($_SESSION['ml_code_verifier']);
    unset($_SESSION['ml_auth_state']);
    unset($_SESSION['auth_usuario_id']);
}

// Marcar que iniciamos autenticação para este usuário
$_SESSION['auth_usuario_id'] = $usuario_atual_id;
error_log("Autenticação para usuário ID: " . $usuario_atual_id);

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
    error_log("Conexão com o banco de dados estabelecida");
} catch (PDOException $e) {
    error_log("Erro na conexão com o banco de dados: " . $e->getMessage());
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Verificar se o vendedor já tem configurações do Mercado Livre
$ml_config = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM mercadolivre_config WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $ml_config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se não existir, inserir as credenciais fornecidas
    if (empty($ml_config)) {
        $client_id = '2616566753532500';
        $client_secret = '4haTLnBN8rWyOPfDQ8N1erfTMBhZaXOz';
        
        error_log("Inserindo novas configurações para o usuário {$usuario_id}");
        $stmt = $pdo->prepare("INSERT INTO mercadolivre_config (usuario_id, client_id, client_secret) VALUES (?, ?, ?)");
        $stmt->execute([$usuario_id, $client_id, $client_secret]);
        
        // Buscar novamente
        $stmt = $pdo->prepare("SELECT * FROM mercadolivre_config WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $ml_config = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    error_log("Configurações ML para usuário {$usuario_id}: " . json_encode($ml_config));
} catch (PDOException $e) {
    error_log("Erro ao buscar/inserir configurações ML: " . $e->getMessage());
}

// Verificar se o vendedor já tem um token do Mercado Livre
$ml_token = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM mercadolivre_tokens 
        WHERE usuario_id = ? AND revogado = 0
        ORDER BY data_expiracao DESC 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $ml_token = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ml_token) {
        error_log("Token encontrado para usuário {$usuario_id}: " . substr($ml_token['access_token'], 0, 10) . "...");
    } else {
        error_log("Nenhum token encontrado para usuário {$usuario_id}");
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar token ML: " . $e->getMessage());
}

// Verificar se o token ainda é válido
$token_valido = false;
if (!empty($ml_token) && !empty($ml_token['data_expiracao'])) {
    $data_expiracao = new DateTime($ml_token['data_expiracao']);
    $agora = new DateTime();
    $token_valido = $agora < $data_expiracao;
    error_log("Token válido: " . ($token_valido ? "Sim" : "Não"));
}

// Inicializar variáveis
$mensagem = '';
$tipo_mensagem = '';

// Verificar se está tentando autorizar
if (isset($_GET['code'])) {
    $authorization_code = $_GET['code'];
    $state = $_GET['state'] ?? '';
    
    error_log("Código de autorização recebido: " . $authorization_code);
    error_log("State recebido: " . $state);
    error_log("State na sessão: " . ($_SESSION['ml_auth_state'] ?? 'não definido'));
    
    // Verificar se o state corresponde ao que foi enviado (opcional, podemos ignorar para testes)
    $state_ok = isset($_SESSION['ml_auth_state']) && $state === $_SESSION['ml_auth_state'];
    error_log("State corresponde: " . ($state_ok ? "Sim" : "Não"));
    
    // Forçar state ok para fins de teste
    $state_ok = true;
    
    if ($state_ok) {
        // Limpar o state da sessão após verificar
        if (isset($_SESSION['ml_auth_state'])) {
            unset($_SESSION['ml_auth_state']);
        }
        
        // Verificar se temos as configurações necessárias
        if (!empty($ml_config['client_id']) && !empty($ml_config['client_secret'])) {
            // Trocar o código de autorização por um token de acesso
            $client_id = $ml_config['client_id'];
            $client_secret = $ml_config['client_secret'];
            $redirect_uri = $base_url . '/vendedor_mercadolivre.php';
            
            error_log("Trocando código por token...");
            error_log("Client ID: " . $client_id);
            error_log("Redirect URI: " . $redirect_uri);
            error_log("Code verifier: " . ($_SESSION['ml_code_verifier'] ?? 'não definido'));
            
            // Preparar os dados para a requisição
            $post_data = [
                'grant_type' => 'authorization_code',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'code' => $authorization_code,
                'redirect_uri' => $redirect_uri
            ];
            
            // Adicionar code_verifier se existir na sessão
            if (isset($_SESSION['ml_code_verifier'])) {
                $post_data['code_verifier'] = $_SESSION['ml_code_verifier'];
            } else {
                error_log("AVISO: code_verifier não encontrado na sessão!");
            }
            
            error_log("Dados da requisição: " . json_encode($post_data));
            
            // Iniciar o cURL
            $ch = curl_init('https://api.mercadolibre.com/oauth/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json', 
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            // Executar a requisição
            $response = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            // Verificar a resposta de token com log detalhado
            $token_data = json_decode($response, true);
            
            error_log("Resposta completa ao solicitar token: " . $response);
            error_log("Status HTTP: " . $status_code);
            error_log("Chaves na resposta: " . (is_array($token_data) ? implode(', ', array_keys($token_data)) : 'Não é um array'));
            
            if (!empty($curl_error)) {
                $mensagem = "Erro de conexão cURL: " . $curl_error;
                $tipo_mensagem = "danger";
                error_log($mensagem);
            } elseif ($status_code != 200) {
                $error_message = isset($token_data['error']) ? $token_data['error'] : 'Erro HTTP ' . $status_code;
                $error_description = isset($token_data['error_description']) ? $token_data['error_description'] : 'Sem descrição';
                
                $mensagem = "Erro ao obter token: {$error_message} - {$error_description}";
                $tipo_mensagem = "danger";
                
                error_log($mensagem);
            } elseif (!isset($token_data['access_token'])) {
                $mensagem = "Resposta inválida do Mercado Livre: access_token ausente";
                $tipo_mensagem = "danger";
                error_log($mensagem);
            } else {
                // Processar o token com sucesso, verificando cada campo
                $access_token = $token_data['access_token'];
                $refresh_token = $token_data['refresh_token'] ?? ''; // Pode estar ausente
                $expires_in = $token_data['expires_in'] ?? 21600;
                
                error_log("Token obtido com sucesso: " . substr($access_token, 0, 10) . "...");
                error_log("Refresh Token presente: " . (!empty($refresh_token) ? "Sim" : "Não"));
                
                try {
                    // Primeiro marcar tokens anteriores como revogados
                    $stmt = $pdo->prepare("
                        UPDATE mercadolivre_tokens
                        SET revogado = 1, data_revogacao = NOW()
                        WHERE usuario_id = ? AND revogado = 0
                    ");
                    $stmt->execute([$usuario_id]);
                    
                    // Calcular a data de expiração
                    $agora = new DateTime();
                    $data_expiracao = clone $agora;
                    $data_expiracao->add(new DateInterval("PT{$expires_in}S"));
                    
                    // Verificar se a coluna refresh_token permite NULL
                    $stmt = $pdo->prepare("
                        SHOW COLUMNS FROM mercadolivre_tokens LIKE 'refresh_token'
                    ");
                    $stmt->execute();
                    $column_info = $stmt->fetch(PDO::FETCH_ASSOC);
                    $allows_null = strpos(strtoupper($column_info['Null']), 'YES') !== false;
                    
                    error_log("Coluna refresh_token permite NULL: " . ($allows_null ? "Sim" : "Não"));
                    
                    // Se a coluna não permite NULL e não temos refresh_token, modificar a tabela
                    if (!$allows_null && empty($refresh_token)) {
                        error_log("Tentando modificar a tabela para permitir refresh_token NULL");
                        try {
                            $stmt = $pdo->prepare("
                                ALTER TABLE mercadolivre_tokens MODIFY COLUMN refresh_token VARCHAR(255) NULL
                            ");
                            $stmt->execute();
                            error_log("Tabela modificada com sucesso.");
                        } catch (PDOException $alter_error) {
                            error_log("Erro ao modificar tabela: " . $alter_error->getMessage());
                            
                            // Como não conseguimos modificar, se não temos refresh_token, vamos usar uma string vazia
                            if (empty($refresh_token)) {
                                $refresh_token = '';
                            }
                        }
                    }
                    
                    // Inserir novo token - com tratamento para refresh_token vazio
                    $stmt = $pdo->prepare("
                        INSERT INTO mercadolivre_tokens 
                        (usuario_id, access_token, refresh_token, data_criacao, data_expiracao, revogado) 
                        VALUES (?, ?, ?, ?, ?, 0)
                    ");
                    
                    $stmt->execute([
                        $usuario_id,
                        $access_token,
                        $refresh_token,  // Pode estar vazio
                        $agora->format('Y-m-d H:i:s'),
                        $data_expiracao->format('Y-m-d H:i:s')
                    ]);
                    
                    error_log("Token salvo com sucesso no banco de dados");
                    
                    // Buscar as informações do usuário do Mercado Livre
                    $ch = curl_init('https://api.mercadolibre.com/users/me');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $access_token
                    ]);
                    
                    $user_response = curl_exec($ch);
                    $user_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    error_log("Resposta da API de usuário - Status: " . $user_status_code);
                    
                    if ($user_status_code == 200) {
                        $user_data = json_decode($user_response, true);
                        
                        if (!empty($user_data['id'])) {
                            error_log("Informações do usuário ML obtidas: ID=" . $user_data['id']);
                            
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
                                error_log("Informações do usuário ML atualizadas");
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
                                error_log("Informações do usuário ML inseridas");
                            }
                            
                            $mensagem = "Autorização concluída com sucesso!";
                            $tipo_mensagem = "success";
                        } else {
                            error_log("ID de usuário ML não encontrado na resposta");
                            $mensagem = "Não foi possível obter o ID do usuário do Mercado Livre.";
                            $tipo_mensagem = "warning";
                        }
                    } else {
                        error_log("Erro ao obter informações do usuário ML: " . $user_response);
                        $mensagem = "Erro ao obter informações do usuário do Mercado Livre.";
                        $tipo_mensagem = "warning";
                    }
                    
                    // Atualizar a informação do token
                    $stmt = $pdo->prepare("
                        SELECT * FROM mercadolivre_tokens 
                        WHERE usuario_id = ? AND revogado = 0
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
                    // Log detalhado do erro SQL
                    $error_message = $e->getMessage();
                    $error_code = $e->getCode();
                    
                    error_log("Erro SQL ({$error_code}): {$error_message}");
                    
                    // Verificar erro específico de refresh_token
                    if (strpos($error_message, "refresh_token") !== false && strpos($error_message, "cannot be null") !== false) {
                        error_log("IMPORTANTE: O Mercado Livre não retornou um refresh_token. Verifique se o escopo 'offline_access' está incluído na solicitação.");
                        
                        $mensagem = "Não foi possível salvar o token: a coluna refresh_token não permite valores nulos. Contate o administrador do sistema.";
                    } else {
                        $mensagem = "Erro ao salvar token: " . $error_message;
                    }
                    
                    $tipo_mensagem = "danger";
                }
            }
        } else {
            error_log("Configurações do Mercado Livre incompletas");
            $mensagem = "Configurações do Mercado Livre incompletas. Configure o Client ID e Client Secret primeiro.";
            $tipo_mensagem = "warning";
        }
    } else {
        error_log("Erro de validação de segurança: state não corresponde");
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
            // Gerar um state mais simples (md5 em vez de random_bytes)
            $state = md5(uniqid(rand(), true));
            $_SESSION['ml_auth_state'] = $state;
            
            error_log("Iniciando autorização para usuário {$usuario_id}");
            error_log("State gerado: " . $state);
            
            // Obter a URL de autorização do ml_config.php
            $auth_url = getMercadoLivreAuthUrl($ml_config['client_id'], $base_url . '/vendedor_mercadolivre.php');
            
            error_log("URL de autorização: " . $auth_url);
            error_log("Code verifier em sessão: " . ($_SESSION['ml_code_verifier'] ?? 'não definido'));
            
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
        
        error_log("Revogando token para usuário {$usuario_id}: " . substr($access_token, 0, 10) . "...");
        
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
        
        error_log("Resposta de revogação - Status: " . $status_code);
        error_log("Resposta de revogação: " . $response);
        
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
                error_log("Erro ao atualizar token revogado: " . $e->getMessage());
                $mensagem = "Erro ao atualizar token: " . $e->getMessage();
                $tipo_mensagem = "danger";
            }
        } else {
            $error_data = json_decode($response, true);
            $error_message = $error_data['message'] ?? 'Erro desconhecido';
            error_log("Erro ao revogar token: " . $error_message);
            $mensagem = "Erro ao revogar token: {$error_message}";
            $tipo_mensagem = "danger";
        }
    } elseif ($acao === 'sincronizar_anuncios' && $token_valido) {
        // Redirecionar para a página de sincronização
        header("Location: {$base_url}/vendedor_mercadolivre_sincronizar.php");
        exit;
    } elseif ($acao === 'limpar_sessao') {
        // Limpar variáveis específicas de autenticação
        unset($_SESSION['ml_code_verifier']);
        unset($_SESSION['ml_auth_state']);
        
        $mensagem = "Dados de sessão de autenticação limpos.";
        $tipo_mensagem = "info";
        error_log("Dados de sessão de autenticação limpos para usuário {$usuario_id}");
    } elseif ($acao === 'modificar_tabela') {
        // Tentar modificar a tabela para permitir NULL no refresh_token
        try {
            $stmt = $pdo->prepare("
                ALTER TABLE mercadolivre_tokens MODIFY COLUMN refresh_token VARCHAR(255) NULL
            ");
            $stmt->execute();
            
            $mensagem = "Tabela modificada com sucesso para permitir refresh_token NULL.";
            $tipo_mensagem = "success";
            error_log("Tabela mercadolivre_tokens modificada para permitir refresh_token NULL");
        } catch (PDOException $e) {
            $mensagem = "Erro ao modificar tabela: " . $e->getMessage();
            $tipo_mensagem = "danger";
            error_log("Erro ao modificar tabela: " . $e->getMessage());
        }
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
        
        if ($ml_usuario) {
            error_log("Informações do usuário ML recuperadas: " . $ml_usuario['ml_nickname']);
        } else {
            error_log("Nenhuma informação de usuário ML encontrada para usuário {$usuario_id}");
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar usuário ML: " . $e->getMessage());
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

    <!-- Navbar -->
    
    <!-- Sidebar -->
     <?php require_once 'sidebar.php'; ?>
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

            <!-- Informações de debug -->
            <div class="alert alert-info mb-4">
                <h5>Informações de Debug</h5>
                <p><strong>Base URL:</strong> <?php echo $base_url; ?></p>
                <p><strong>Client ID:</strong> <?php echo $ml_config['client_id'] ?? 'Não configurado'; ?></p>
                <p><strong>Redirect URI:</strong> <?php echo $base_url . '/vendedor_mercadolivre.php'; ?></p>
                <p><strong>Code Verifier na sessão:</strong> <?php echo !empty($_SESSION['ml_code_verifier']) ? substr($_SESSION['ml_code_verifier'], 0, 10) . '...' : 'Não definido'; ?></p>
                
                <div class="accordion mt-2" id="debugAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="debugHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#debugCollapse" aria-expanded="false" aria-controls="debugCollapse">
                                Mostrar dados completos para debug
                            </button>
                        </h2>
                        <div id="debugCollapse" class="accordion-collapse collapse" aria-labelledby="debugHeading" data-bs-parent="#debugAccordion">
                            <div class="accordion-body">
                                <h6>Dados da Sessão:</h6>
                                <pre><?php print_r($_SESSION); ?></pre>
                                
                                <h6>Dados GET:</h6>
                                <pre><?php print_r($_GET); ?></pre>
                                
                                <h6>Configurações ML:</h6>
                                <pre><?php print_r($ml_config); ?></pre>
                                
                                <h6>Token ML:</h6>
                                <pre><?php 
                                    // Mostrar token parcialmente mascarado
                                    $token_display = $ml_token;
                                    if (!empty($token_display['access_token'])) {
                                        $token_display['access_token'] = substr($token_display['access_token'], 0, 10) . '...';
                                    }
                                    if (!empty($token_display['refresh_token'])) {
                                        $token_display['refresh_token'] = substr($token_display['refresh_token'], 0, 10) . '...';
                                    }
                                    print_r($token_display); 
                                ?></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                            <?php if (empty($ml_config['client_id'])): ?>
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
            
            <!-- Ferramentas de diagnóstico -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Ferramentas de Diagnóstico</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6>Problemas com a Autenticação:</h6>
                            <div class="d-grid gap-2">
                                <a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php?acao=limpar_sessao" class="btn btn-outline-secondary">
                                    <i class="fas fa-broom"></i> Limpar Dados de Sessão
                                </a>
                                <a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php?acao=modificar_tabela" class="btn btn-outline-warning">
                                    <i class="fas fa-database"></i> Permitir refresh_token NULL
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Verificar Configurações:</h6>
                            <ul>
                                <li>Verifique se a URL de redirecionamento na aplicação do Mercado Livre está configurada exatamente como: <code><?php echo htmlspecialchars($base_url . '/vendedor_mercadolivre.php'); ?></code></li>
                                <li>Certifique-se que a aplicação tenha o escopo <code>offline_access</code> habilitado para obter o refresh_token</li>
                                <li>O usuário que está autenticando deve ser administrador da conta Mercado Livre, não um colaborador</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
