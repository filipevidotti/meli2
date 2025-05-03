<?php
// Iniciar sessão
session_start();

// Verificar acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendedor') {
    header("Location: emergency_login.php");
    exit;
}

// Definir variáveis básicas
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

// Buscar dados do vendedor - CORRIGIDO para não usar a coluna telefone
$vendedor = [];
try {
    // Verificar se a tabela usuarios tem a coluna telefone
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'telefone'");
    $tem_coluna_telefone = $stmt->rowCount() > 0;
    
    // Modificar a consulta conforme a existência da coluna telefone
    if ($tem_coluna_telefone) {
        $stmt = $pdo->prepare("
            SELECT v.*, u.email, u.nome, u.telefone
            FROM vendedores v 
            JOIN usuarios u ON v.usuario_id = u.id
            WHERE v.usuario_id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT v.*, u.email, u.nome
            FROM vendedores v 
            JOIN usuarios u ON v.usuario_id = u.id
            WHERE v.usuario_id = ?
        ");
    }
    
    $stmt->execute([$usuario_id]);
    $vendedor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se não encontrou o vendedor, criar um registro básico
    if (!$vendedor) {
        $vendedor = [
            'usuario_id' => $usuario_id,
            'nome' => $usuario_nome,
            'email' => $_SESSION['user_email'] ?? '',
            'telefone' => '',
            'cpf_cnpj' => '',
            'razao_social' => '',
            'endereco' => '',
            'cidade' => '',
            'estado' => '',
            'cep' => ''
        ];
    }
} catch (PDOException $e) {
    $mensagem = "Erro ao buscar dados do vendedor: " . $e->getMessage();
    $tipo_mensagem = "danger";
}

// Buscar dados da integração com Mercado Livre
$ml_config = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM mercadolivre_config 
        WHERE usuario_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $ml_config = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro
}

// Buscar anúncios do Mercado Livre
$anuncios_ml = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM anuncios_ml
        WHERE usuario_id = ?
        ORDER BY data_ultima_sincronizacao DESC
        LIMIT 5
    ");
    $stmt->execute([$usuario_id]);
    $anuncios_ml = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro
}

// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_POST['tab'] ?? 'perfil';
    
    if ($tab === 'perfil') {
        // Atualizar dados de perfil
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');  // Capturar o telefone mesmo que não vá ser salvo
        $cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
        $razao_social = trim($_POST['razao_social'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $estado = trim($_POST['estado'] ?? '');
        $cep = trim($_POST['cep'] ?? '');
        
        try {
            // Atualizar na tabela de usuários (sem a coluna telefone se ela não existir)
            if ($tem_coluna_telefone) {
                $stmt = $pdo->prepare("
                    UPDATE usuarios 
                    SET nome = ?, email = ?, telefone = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome, $email, $telefone, $usuario_id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE usuarios 
                    SET nome = ?, email = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome, $email, $usuario_id]);
            }
            
            // Verificar se já existe registro na tabela de vendedores
            $stmt = $pdo->prepare("SELECT usuario_id FROM vendedores WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
            
            if ($stmt->rowCount() > 0) {
                // Atualizar na tabela de vendedores
                $stmt = $pdo->prepare("
                    UPDATE vendedores 
                    SET cpf_cnpj = ?, razao_social = ?, endereco = ?, 
                        cidade = ?, estado = ?, cep = ? 
                    WHERE usuario_id = ?
                ");
                $stmt->execute([
                    $cpf_cnpj, $razao_social, $endereco, 
                    $cidade, $estado, $cep, $usuario_id
                ]);
            } else {
                // Inserir na tabela de vendedores
                $stmt = $pdo->prepare("
                    INSERT INTO vendedores (usuario_id, cpf_cnpj, razao_social, endereco, cidade, estado, cep) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $usuario_id, $cpf_cnpj, $razao_social, $endereco, 
                    $cidade, $estado, $cep
                ]);
            }
            
            $mensagem = "Dados atualizados com sucesso!";
            $tipo_mensagem = "success";
            
            // Atualizar os dados do vendedor na sessão
            $_SESSION['user_name'] = $nome;
            $_SESSION['user_email'] = $email;
            
        } catch (PDOException $e) {
            $mensagem = "Erro ao atualizar dados: " . $e->getMessage();
            $tipo_mensagem = "danger";
        }
    } elseif ($tab === 'mercadolivre') {
        // Configurações do Mercado Livre
        $client_id = trim($_POST['client_id'] ?? '');
        $client_secret = trim($_POST['client_secret'] ?? '');
        
        try {
            // Verificar se já existe registro
            $stmt = $pdo->prepare("SELECT id FROM mercadolivre_config WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
            
            if ($stmt->rowCount() > 0) {
                // Atualizar configuração
                $stmt = $pdo->prepare("
                    UPDATE mercadolivre_config 
                    SET client_id = ?, client_secret = ?, atualizado_em = NOW() 
                    WHERE usuario_id = ?
                ");
                $stmt->execute([$client_id, $client_secret, $usuario_id]);
            } else {
                // Inserir nova configuração
                $stmt = $pdo->prepare("
                    INSERT INTO mercadolivre_config (usuario_id, client_id, client_secret, criado_em, atualizado_em) 
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$usuario_id, $client_id, $client_secret]);
            }
            
            $mensagem = "Configurações do Mercado Livre atualizadas com sucesso!";
            $tipo_mensagem = "success";
            
            // Atualizar a variável ml_config
            $stmt = $pdo->prepare("SELECT * FROM mercadolivre_config WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
            $ml_config = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $mensagem = "Erro ao atualizar configurações: " . $e->getMessage();
            $tipo_mensagem = "danger";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - CalcMeli</title>
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
        .nav-pills .nav-link.active {
            background-color: #ff9a00;
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
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/vendedor_index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/emergency_login.php?logout=1"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
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
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_index.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_vendas.php">
                        <i class="fas fa-shopping-cart"></i> Vendas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_produtos.php">
                        <i class="fas fa-box"></i> Produtos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_calculadora.php">
                        <i class="fas fa-calculator"></i> Calculadora ML
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_relatorios.php">
                        <i class="fas fa-chart-bar"></i> Relatórios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_anuncios.php">
                        <i class="fas fa-ad"></i> Anúncios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo $base_url; ?>/vendedor_config.php">
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
                <h1 class="h2">Configurações</h1>
            </div>

            <?php if (isset($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensagem); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">Menu</div>
                        <div class="card-body p-0">
                            <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
                                <button class="nav-link active" id="perfil-tab" data-bs-toggle="pill" data-bs-target="#perfil" type="button" role="tab">
                                    <i class="fas fa-user"></i> Perfil
                                </button>
                                <button class="nav-link" id="mercadolivre-tab" data-bs-toggle="pill" data-bs-target="#mercadolivre" type="button" role="tab">
                                    <i class="fas fa-store"></i> Mercado Livre
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-9">
                    <div class="tab-content" id="v-pills-tabContent">
                        <!-- Perfil -->
                        <div class="tab-pane fade show active" id="perfil" role="tabpanel" aria-labelledby="perfil-tab">
                            <div class="card shadow-sm">
                                <div class="card-header bg-white">Dados do Perfil</div>
                                <div class="card-body">
                                    <form method="post" action="">
                                        <input type="hidden" name="tab" value="perfil">
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="nome" class="form-label">Nome</label>
                                                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($vendedor['nome'] ?? ''); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="email" class="form-label">E-mail</label>
                                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($vendedor['email'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="telefone" class="form-label">Telefone</label>
                                                <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars($vendedor['telefone'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="cpf_cnpj" class="form-label">CPF/CNPJ</label>
                                                <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" value="<?php echo htmlspecialchars($vendedor['cpf_cnpj'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="razao_social" class="form-label">Razão Social / Nome Fantasia</label>
                                            <input type="text" class="form-control" id="razao_social" name="razao_social" value="<?php echo htmlspecialchars($vendedor['razao_social'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="endereco" class="form-label">Endereço</label>
                                            <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo htmlspecialchars($vendedor['endereco'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label for="cidade" class="form-label">Cidade</label>
                                                <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo htmlspecialchars($vendedor['cidade'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="estado" class="form-label">Estado</label>
                                                <select class="form-select" id="estado" name="estado">
                                                    <option value="">Selecione...</option>
                                                    <?php 
                                                    $estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                                                    foreach ($estados as $uf) {
                                                        $selected = ($vendedor['estado'] ?? '') === $uf ? 'selected' : '';
                                                        echo "<option value=\"{$uf}\" {$selected}>{$uf}</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="cep" class="form-label">CEP</label>
                                                <input type="text" class="form-control" id="cep" name="cep" value="<?php echo htmlspecialchars($vendedor['cep'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-warning">Salvar Alterações</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mercado Livre -->
                        <div class="tab-pane fade" id="mercadolivre" role="tabpanel" aria-labelledby="mercadolivre-tab">
                            <div class="card shadow-sm">
                                <div class="card-header bg-white">Configurações do Mercado Livre</div>
                                <div class="card-body">
                                    <form method="post" action="">
                                        <input type="hidden" name="tab" value="mercadolivre">
                                        
                                        <div class="mb-3">
                                            <label for="client_id" class="form-label">Client ID</label>
                                            <input type="text" class="form-control" id="client_id" name="client_id" value="<?php echo htmlspecialchars($ml_config['client_id'] ?? ''); ?>" required>
                                            <div class="form-text">ID da sua aplicação no Mercado Livre</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="client_secret" class="form-label">Client Secret</label>
                                            <input type="password" class="form-control" id="client_secret" name="client_secret" value="<?php echo htmlspecialchars($ml_config['client_secret'] ?? ''); ?>" required>
                                            <div class="form-text">Senha secreta da sua aplicação no Mercado Livre</div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-warning">Salvar Configurações</button>
                                        
                                        <?php if (!empty($ml_config)): ?>
                                            <a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php" class="btn btn-outline-primary ms-2">
                                                <i class="fas fa-plug"></i> Conectar Conta
                                            </a>
                                        <?php endif; ?>
                                    </form>
                                    
                                    <hr>
                                    
                                    <div class="mb-3">
                                        <h5>Anúncios sincronizados</h5>
                                        <?php if (empty($anuncios_ml)): ?>
                                            <div class="alert alert-info">
                                                Nenhum anúncio foi sincronizado com o Mercado Livre ainda.
                                            </div>
                                        <?php else: ?>
                                            <p>Últimos anúncios sincronizados:</p>
                                            <ul class="list-group">
                                                <?php foreach ($anuncios_ml as $anuncio): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?php echo htmlspecialchars($anuncio['titulo']); ?>
                                                        <span class="badge bg-primary rounded-pill">
                                                            <?php echo date('d/m/Y H:i', strtotime($anuncio['data_ultima_sincronizacao'] ?? $anuncio['data_criacao'])); ?>
                                                        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            
                                            <div class="mt-3">
                                                <a href="<?php echo $base_url; ?>/vendedor_anuncios.php" class="btn btn-sm btn-outline-primary">
                                                    Ver todos os anúncios
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> Para sincronizar seus anúncios do Mercado Livre, primeiro configure e conecte sua conta, depois acesse a página de <a href="<?php echo $base_url; ?>/vendedor_anuncios.php" class="alert-link">Anúncios</a>.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Máscaras para campos
            const telefoneInput = document.getElementById('telefone');
            if (telefoneInput) {
                telefoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 11) value = value.substring(0, 11);
                    
                    // Formatar (XX) XXXXX-XXXX
                    if (value.length > 2) {
                        value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                    }
                    if (value.length > 10) {
                        value = value.substring(0, 10) + '-' + value.substring(10);
                    }
                    
                    e.target.value = value;
                });
            }

            const cepInput = document.getElementById('cep');
            if (cepInput) {
                cepInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 8) value = value.substring(0, 8);
                    
                    // Formatar XXXXX-XXX
                    if (value.length > 5) {
                        value = value.substring(0, 5) + '-' + value.substring(5);
                    }
                    
                    e.target.value = value;
                });
            }
            
            // Verificar se há um hash na URL e abrir a aba correspondente
            const hash = window.location.hash;
            if (hash) {
                const tab = hash.substring(1); // Remove o # do início
                const tabElement = document.getElementById(`${tab}-tab`);
                if (tabElement) {
                    tabElement.click();
                }
            }
            
            // Atualizar a URL quando mudar de aba
            const tabs = document.querySelectorAll('[data-bs-toggle="pill"]');
            tabs.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(event) {
                    const id = event.target.id.replace('-tab', '');
                    history.pushState(null, null, `#${id}`);
                });
            });
        });
    </script>
</body>
</html>
