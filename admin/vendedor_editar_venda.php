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
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
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

// Verificar se o ID da venda foi informado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['mensagem'] = "ID da venda não especificado.";
    $_SESSION['tipo_mensagem'] = "danger";
    header("Location: " . $base_url . "/vendedor_vendas.php");
    exit;
}

$venda_id = intval($_GET['id']);

// Buscar dados da venda
$venda = null;
try {
    $stmt = $pdo->prepare("SELECT v.*, c.nome as cliente_nome, c.email as cliente_email, c.telefone as cliente_telefone,
                         c.endereco as cliente_endereco, c.cidade as cliente_cidade, c.estado as cliente_estado, 
                         c.cep as cliente_cep
                     FROM vendas v
                     LEFT JOIN clientes c ON v.cliente_id = c.id
                     WHERE v.id = ? AND v.usuario_id = ?");
    $stmt->execute([$venda_id, $usuario_id]);
    $venda = $stmt->fetch();
    
    if (!$venda) {
        $_SESSION['mensagem'] = "Venda não encontrada ou você não tem permissão para editá-la.";
        $_SESSION['tipo_mensagem'] = "danger";
        header("Location: " . $base_url . "/vendedor_vendas.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['mensagem'] = "Erro ao buscar dados da venda: " . $e->getMessage();
    $_SESSION['tipo_mensagem'] = "danger";
    header("Location: " . $base_url . "/vendedor_vendas.php");
    exit;
}

// Buscar itens da venda
$itens_venda = [];
try {
    $stmt = $pdo->prepare("SELECT i.*, p.nome as produto_nome, p.sku as produto_sku
                         FROM itens_venda i
                         LEFT JOIN produtos p ON i.produto_id = p.id
                         WHERE i.venda_id = ?
                         ORDER BY i.id");
    $stmt->execute([$venda_id]);
    $itens_venda = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar itens da venda: " . $e->getMessage());
}

// Buscar produtos disponíveis
$produtos = [];
try {
    $stmt = $pdo->prepare("SELECT p.id, p.nome, p.sku, p.custo
                         FROM produtos p
                         WHERE p.usuario_id = ?
                         ORDER BY p.nome");
    $stmt->execute([$usuario_id]);
    $produtos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar produtos: " . $e->getMessage());
}

// Processar o formulário quando enviado
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter dados do formulário
    $data_venda = isset($_POST['data_venda']) ? $_POST['data_venda'] : null;
    $valor_total = isset($_POST['valor_total']) ? floatval(str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_total'])) : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : 'pendente';
    $forma_pagamento = isset($_POST['forma_pagamento']) ? $_POST['forma_pagamento'] : null;
    $canal_venda = isset($_POST['canal_venda']) ? $_POST['canal_venda'] : null;
    $observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : null;
    
    // Dados do cliente
    $cliente_nome = isset($_POST['cliente_nome']) ? trim($_POST['cliente_nome']) : null;
    $cliente_email = isset($_POST['cliente_email']) ? trim($_POST['cliente_email']) : null;
    $cliente_telefone = isset($_POST['cliente_telefone']) ? trim($_POST['cliente_telefone']) : null;
    $cliente_endereco = isset($_POST['cliente_endereco']) ? trim($_POST['cliente_endereco']) : null;
    $cliente_cidade = isset($_POST['cliente_cidade']) ? trim($_POST['cliente_cidade']) : null;
    $cliente_estado = isset($_POST['cliente_estado']) ? trim($_POST['cliente_estado']) : null;
    $cliente_cep = isset($_POST['cliente_cep']) ? trim($_POST['cliente_cep']) : null;
    
    try {
        // Iniciar transação
        $pdo->beginTransaction();
        
        // Atualizar ou criar cliente
        $cliente_id = null;
        if ($venda['cliente_id']) {
            // Atualizar cliente existente
            $stmt = $pdo->prepare("UPDATE clientes SET nome = ?, email = ?, telefone = ?, 
                                endereco = ?, cidade = ?, estado = ?, cep = ?
                                WHERE id = ?");
            $stmt->execute([
                $cliente_nome, 
                $cliente_email, 
                $cliente_telefone,
                $cliente_endereco,
                $cliente_cidade,
                $cliente_estado,
                $cliente_cep,
                $venda['cliente_id']
            ]);
            $cliente_id = $venda['cliente_id'];
        } else if (!empty($cliente_nome)) {
            // Criar novo cliente
            $stmt = $pdo->prepare("INSERT INTO clientes (usuario_id, nome, email, telefone, endereco, cidade, estado, cep)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $usuario_id,
                $cliente_nome,
                $cliente_email,
                $cliente_telefone,
                $cliente_endereco,
                $cliente_cidade,
                $cliente_estado,
                $cliente_cep
            ]);
            $cliente_id = $pdo->lastInsertId();
        }
        
        // Atualizar venda
        $stmt = $pdo->prepare("UPDATE vendas SET 
                            data_venda = ?, 
                            valor_total = ?, 
                            status = ?, 
                            cliente_id = ?, 
                            forma_pagamento = ?, 
                            canal_venda = ?, 
                            observacoes = ?
                            WHERE id = ? AND usuario_id = ?");
        $stmt->execute([
            $data_venda,
            $valor_total,
            $status,
            $cliente_id,
            $forma_pagamento,
            $canal_venda,
            $observacoes,
            $venda_id,
            $usuario_id
        ]);
        
        // Remover todos os itens da venda
        $stmt = $pdo->prepare("DELETE FROM itens_venda WHERE venda_id = ?");
        $stmt->execute([$venda_id]);
        
        // Inserir novos itens da venda
        if (isset($_POST['item_produto_id']) && is_array($_POST['item_produto_id'])) {
            $quantidades = $_POST['item_quantidade'] ?? [];
            $precos = $_POST['item_preco'] ?? [];
            
            for ($i = 0; $i < count($_POST['item_produto_id']); $i++) {
                $produto_id = intval($_POST['item_produto_id'][$i]);
                $quantidade = isset($quantidades[$i]) ? floatval($quantidades[$i]) : 0;
                $preco_unitario = isset($precos[$i]) ? floatval(str_replace(['R$', '.', ','], ['', '', '.'], $precos[$i])) : 0;
                
                if ($produto_id > 0 && $quantidade > 0) {
                    $stmt = $pdo->prepare("INSERT INTO itens_venda (venda_id, produto_id, quantidade, preco_unitario)
                                       VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $venda_id,
                        $produto_id,
                        $quantidade,
                        $preco_unitario
                    ]);
                }
            }
        }
        
        // Confirmar transação
        $pdo->commit();
        
        $_SESSION['mensagem'] = "Venda atualizada com sucesso!";
        $_SESSION['tipo_mensagem'] = "success";
        header("Location: " . $base_url . "/vendedor_vendas.php");
        exit;
        
    } catch (PDOException $e) {
        // Reverter transação em caso de erro
        $pdo->rollBack();
        $mensagem = "Erro ao atualizar venda: " . $e->getMessage();
        $tipo_mensagem = "danger";
    }
}

// Funções de formatação
function formatCurrency($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function formatDate($date) {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    return date('Y-m-d', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Venda - CalcMeli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
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
        .item-venda {
            background-color: #f9f9f9;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 3px solid #ff9a00;
        }
        .form-control:focus, .form-select:focus {
            border-color: #ff9a00;
            box-shadow: 0 0 0 0.25rem rgba(255, 154, 0, 0.25);
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
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_produtos.php">
                        <i class="fas fa-box"></i>
                        Produtos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo $base_url; ?>/vendedor_vendas.php">
                        <i class="fas fa-shopping-cart"></i>
                        Vendas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_anuncios.php">
                        <i class="fas fa-ad"></i>
                        Anúncios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_relatorios.php">
                        <i class="fas fa-chart-bar"></i>
                        Relatórios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>/vendedor_config.php">
                        <i class="fas fa-cog"></i>
                        Configurações
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Editar Venda #<?php echo $venda_id; ?></h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="<?php echo $base_url; ?>/vendedor_vendas.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
        
        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="form-edit-venda">
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0"><i class="fas fa-shopping-cart me-2"></i>Detalhes da Venda</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="data_venda" class="form-label">Data da Venda</label>
                                    <input type="date" class="form-control" id="data_venda" name="data_venda" value="<?php echo formatDate($venda['data_venda']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="valor_total" class="form-label">Valor Total</label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="text" class="form-control" id="valor_total" name="valor_total" value="<?php echo number_format($venda['valor_total'], 2, ',', '.'); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="pendente" <?php echo $venda['status'] == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                        <option value="pago" <?php echo $venda['status'] == 'pago' ? 'selected' : ''; ?>>Pago</option>
                                        <option value="enviado" <?php echo $venda['status'] == 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                                        <option value="entregue" <?php echo $venda['status'] == 'entregue' ? 'selected' : ''; ?>>Entregue</option>
                                        <option value="cancelado" <?php echo $venda['status'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                                    <select class="form-select" id="forma_pagamento" name="forma_pagamento">
                                        <option value="">Selecione</option>
                                        <option value="pix" <?php echo $venda['forma_pagamento'] == 'pix' ? 'selected' : ''; ?>>PIX</option>
                                        <option value="cartao" <?php echo $venda['forma_pagamento'] == 'cartao' ? 'selected' : ''; ?>>Cartão de Crédito</option>
                                        <option value="boleto" <?php echo $venda['forma_pagamento'] == 'boleto' ? 'selected' : ''; ?>>Boleto</option>
                                        <option value="transferencia" <?php echo $venda['forma_pagamento'] == 'transferencia' ? 'selected' : ''; ?>>Transferência</option>
                                        <option value="dinheiro" <?php echo $venda['forma_pagamento'] == 'dinheiro' ? 'selected' : ''; ?>>Dinheiro</option>
                                        <option value="outro" <?php echo $venda['forma_pagamento'] == 'outro' ? 'selected' : ''; ?>>Outro</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="canal_venda" class="form-label">Canal de Venda</label>
                                    <select class="form-select" id="canal_venda" name="canal_venda">
                                        <option value="">Selecione</option>
                                        <option value="mercadolivre" <?php echo $venda['canal_venda'] == 'mercadolivre' ? 'selected' : ''; ?>>Mercado Livre</option>
                                        <option value="shopee" <?php echo $venda['canal_venda'] == 'shopee' ? 'selected' : ''; ?>>Shopee</option>
                                        <option value="site" <?php echo $venda['canal_venda'] == 'site' ? 'selected' : ''; ?>>Site Próprio</option>
                                        <option value="loja" <?php echo $venda['canal_venda'] == 'loja' ? 'selected' : ''; ?>>Loja Física</option>
                                        <option value="whatsapp" <?php echo $venda['canal_venda'] == 'whatsapp' ? 'selected' : ''; ?>>WhatsApp</option>
                                        <option value="outro" <?php echo $venda['canal_venda'] == 'outro' ? 'selected' : ''; ?>>Outro</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="observacoes" class="form-label">Observações</label>
                                    <textarea class="form-control" id="observacoes" name="observacoes" rows="1"><?php echo htmlspecialchars($venda['observacoes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0"><i class="fas fa-user me-2"></i>Dados do Cliente</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="cliente_nome" class="form-label">Nome</label>
                                    <input type="text" class="form-control" id="cliente_nome" name="cliente_nome" value="<?php echo htmlspecialchars($venda['cliente_nome'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_email" class="form-label">E-mail</label>
                                    <input type="email" class="form-control" id="cliente_email" name="cliente_email" value="<?php echo htmlspecialchars($venda['cliente_email'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="cliente_telefone" class="form-label">Telefone</label>
                                    <input type="text" class="form-control" id="cliente_telefone" name="cliente_telefone" value="<?php echo htmlspecialchars($venda['cliente_telefone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_cep" class="form-label">CEP</label>
                                    <input type="text" class="form-control" id="cliente_cep" name="cliente_cep" value="<?php echo htmlspecialchars($venda['cliente_cep'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="cliente_endereco" class="form-label">Endereço</label>
                                    <input type="text" class="form-control" id="cliente_endereco" name="cliente_endereco" value="<?php echo htmlspecialchars($venda['cliente_endereco'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="cliente_cidade" class="form-label">Cidade</label>
                                    <input type="text" class="form-control" id="cliente_cidade" name="cliente_cidade" value="<?php echo htmlspecialchars($venda['cliente_cidade'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="cliente_estado" class="form-label">Estado</label>
                                    <select class="form-select" id="cliente_estado" name="cliente_estado">
                                        <option value="">Selecione</option>
                                        <?php
                                        $estados = [
                                            'AC' => 'Acre',
                                            'AL' => 'Alagoas',
                                            'AP' => 'Amapá',
                                            'AM' => 'Amazonas',
                                            'BA' => 'Bahia',
                                            'CE' => 'Ceará',
                                            'DF' => 'Distrito Federal',
                                            'ES' => 'Espírito Santo',
                                            'GO' => 'Goiás',
                                            'MA' => 'Maranhão',
                                            'MT' => 'Mato Grosso',
                                            'MS' => 'Mato Grosso do Sul',
                                            'MG' => 'Minas Gerais',
                                            'PA' => 'Pará',
                                            'PB' => 'Paraíba',
                                            'PR' => 'Paraná',
                                            'PE' => 'Pernambuco',
                                            'PI' => 'Piauí',
                                            'RJ' => 'Rio de Janeiro',
                                            'RN' => 'Rio Grande do Norte',
                                            'RS' => 'Rio Grande do Sul',
                                            'RO' => 'Rondônia',
                                            'RR' => 'Roraima',
                                            'SC' => 'Santa Catarina',
                                            'SP' => 'São Paulo',
                                            'SE' => 'Sergipe',
                                            'TO' => 'Tocantins'
                                        ];
                                        
                                        foreach ($estados as $sigla => $nome):
                                            $selected = ($venda['cliente_estado'] == $sigla) ? 'selected' : '';
                                            echo "<option value=\"$sigla\" $selected>$nome</option>";
                                        endforeach;
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>Itens da Venda</h5>
                        </div>
                        <div class="card-body">
                            <div id="itens-container">
                                <?php if (count($itens_venda) > 0): ?>
                                    <?php foreach ($itens_venda as $index => $item): ?>
                                        <div class="item-venda mb-3">
                                            <div class="row mb-2">
                                                <div class="col-12">
                                                    <label class="form-label">Produto</label>
                                                    <select class="form-select item-produto" name="item_produto_id[]" required>
                                                        <option value="">Selecione um produto</option>
                                                        <?php foreach ($produtos as $produto): ?>
                                                            <option value="<?php echo $produto['id']; ?>" 
                                                                <?php if ($produto['id'] == $item['produto_id']) echo 'selected'; ?>
                                                                data-custo="<?php echo $produto['custo']; ?>">
                                                                <?php echo htmlspecialchars($produto['nome']); ?>
                                                                <?php if (!empty($produto['sku'])): ?>
                                                                    (<?php echo htmlspecialchars($produto['sku']); ?>)
                                                                <?php endif; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-6">
                                                    <label class="form-label">Quantidade</label>
                                                    <input type="number" class="form-control item-quantidade" name="item_quantidade[]" min="1" step="1" value="<?php echo $item['quantidade']; ?>" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label">Preço Unit.</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">R$</span>
                                                        <input type="text" class="form-control item-preco" name="item_preco[]" value="<?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php if ($index > 0): ?>
                                                <div class="row mt-2">
                                                    <div class="col-12 text-end">
                                                        <button type="button" class="btn btn-sm btn-danger btn-remover-item">
                                                            <i class="fas fa-trash"></i> Remover
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="item-venda mb-3">
                                        <div class="row mb-2">
                                            <div class="col-12">
                                                <label class="form-label">Produto</label>
                                                <select class="form-select item-produto" name="item_produto_id[]" required>
                                                    <option value="">Selecione um produto</option>
                                                    <?php foreach ($produtos as $produto): ?>
                                                        <option value="<?php echo $produto['id']; ?>" data-custo="<?php echo $produto['custo']; ?>">
                                                            <?php echo htmlspecialchars($produto['nome']); ?>
                                                            <?php if (!empty($produto['sku'])): ?>
                                                                (<?php echo htmlspecialchars($produto['sku']); ?>)
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-6">
                                                <label class="form-label">Quantidade</label>
                                                <input type="number" class="form-control item-quantidade" name="item_quantidade[]" min="1" step="1" value="1" required>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">Preço Unit.</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="text" class="form-control item-preco" name="item_preco[]" value="0,00" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid gap-2 mt-3">
                                <button type="button" class="btn btn-secondary" id="adicionar-item">
                                    <i class="fas fa-plus-circle"></i> Adicionar Item
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                        <a href="<?php echo $base_url; ?>/vendedor_vendas.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Adicionar novo item
            document.getElementById('adicionar-item').addEventListener('click', function() {
                const container = document.getElementById('itens-container');
                const itemCount = container.querySelectorAll('.item-venda').length;
                
                const template = `
                    <div class="item-venda mb-3">
                        <div class="row mb-2">
                            <div class="col-12">
                                <label class="form-label">Produto</label>
                                <select class="form-select item-produto" name="item_produto_id[]" required>
                                    <option value="">Selecione um produto</option>
                                    <?php foreach ($produtos as $produto): ?>
                                        <option value="<?php echo $produto['id']; ?>" data-custo="<?php echo $produto['custo']; ?>">
                                            <?php echo htmlspecialchars($produto['nome']); ?>
                                            <?php if (!empty($produto['sku'])): ?>
                                                (<?php echo htmlspecialchars($produto['sku']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label">Quantidade</label>
                                <input type="number" class="form-control item-quantidade" name="item_quantidade[]" min="1" step="1" value="1" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Preço Unit.</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" class="form-control item-preco" name="item_preco[]" value="0,00" required>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12 text-end">
                                <button type="button" class="btn btn-sm btn-danger btn-remover-item">
                                    <i class="fas fa-trash"></i> Remover
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                container.insertAdjacentHTML('beforeend', template);
                
                // Adicionar evento aos novos botões de remover
                const removerButtons = document.querySelectorAll('.btn-remover-item');
                removerButtons.forEach(button => {
                    button.addEventListener('click', removerItem);
                });
                
                // Adicionar evento aos novos campos de produto
                const novoProdutoSelect = container.lastElementChild.querySelector('.item-produto');
                novoProdutoSelect.addEventListener('change', atualizarPrecoProduto);
                
                atualizarValorTotal();
            });
            
            // Inicializar botões de remover item
            document.querySelectorAll('.btn-remover-item').forEach(button => {
                button.addEventListener('click', removerItem);
            });
            
            // Função para remover um item
            function removerItem() {
                this.closest('.item-venda').remove();
                atualizarValorTotal();
            }
            
            // Atualizar preço quando o produto é selecionado
            document.querySelectorAll('.item-produto').forEach(select => {
                select.addEventListener('change', atualizarPrecoProduto);
            });
            
            function atualizarPrecoProduto() {
                const option = this.options[this.selectedIndex];
                if (option && option.value) {
                    const custo = parseFloat(option.dataset.custo);
                    const itemContainer = this.closest('.item-venda');
                    const precoInput = itemContainer.querySelector('.item-preco');
                    
                    // Apenas preencher o preço se for um novo item (preço = 0)
                    const precoAtual = parseFloat(precoInput.value.replace('R$', '').replace('.', '').replace(',', '.'));
                    if (precoAtual === 0 || isNaN(precoAtual)) {
                        precoInput.value = formatarMoeda(custo);
                        atualizarValorTotal();
                    }
                }
            }
            
            // Atualizar valor total quando quantidades ou preços são alterados
            document.querySelectorAll('.item-quantidade, .item-preco').forEach(input => {
                input.addEventListener('input', atualizarValorTotal);
            });
            
            // Calcular o valor total da venda
            function atualizarValorTotal() {
                let valorTotal = 0;
                const itens = document.querySelectorAll('.item-venda');
                
                itens.forEach(item => {
                    const quantidade = parseFloat(item.querySelector('.item-quantidade').value) || 0;
                    const precoTexto = item.querySelector('.item-preco').value;
                    const preco = parseFloat(precoTexto.replace('R$', '').replace('.', '').replace(',', '.')) || 0;
                    
                    valorTotal += quantidade * preco;
                });
                
                document.getElementById('valor_total').value = formatarMoeda(valorTotal);
            }
            
            // Formatar valor como moeda
            function formatarMoeda(valor) {
                return valor.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
            
            // Validar o formulário antes de enviar
            document.getElementById('form-edit-venda').addEventListener('submit', function(e) {
                let valido = true;
                const itens = document.querySelectorAll('.item-venda');
                
                if (itens.length === 0) {
                    alert('Adicione pelo menos um item à venda.');
                    e.preventDefault();
                    return false;
                }
                
                itens.forEach(item => {
                    const produtoSelect = item.querySelector('.item-produto');
                    const quantidadeInput = item.querySelector('.item-quantidade');
                    const precoInput = item.querySelector('.item-preco');
                    
                    if (!produtoSelect.value) {
                        alert('Selecione um produto para todos os itens.');
                        produtoSelect.focus();
                        valido = false;
                    }
                    
                    const quantidade = parseFloat(quantidadeInput.value);
                    if (isNaN(quantidade) || quantidade <= 0) {
                        alert('A quantidade deve ser maior que zero para todos os itens.');
                        quantidadeInput.focus();
                        valido = false;
                    }
                    
                    const precoTexto = precoInput.value;
                    const preco = parseFloat(precoTexto.replace('R$', '').replace('.', '').replace(',', '.'));
                    if (isNaN(preco) || preco <= 0) {
                        alert('O preço deve ser maior que zero para todos os itens.');
                        precoInput.focus();
                        valido = false;
                    }
                });
                
                if (!valido) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Formatar campos de preço
            document.querySelectorAll('.item-preco, #valor_total').forEach(input => {
                input.addEventListener('blur', function() {
                    let valor = this.value.replace('R$', '').replace('.', '').replace(',', '.');
                    valor = parseFloat(valor) || 0;
                    this.value = formatarMoeda(valor);
                });
            });
            
            // Inicializar valor total
            atualizarValorTotal();
        });
    </script>
</body>
</html>
