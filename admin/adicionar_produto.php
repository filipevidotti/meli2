<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

// Inicializar variáveis
$mensagem = '';
$tipo_mensagem = '';
$erro = '';

// Buscar todos os vendedores
$vendedores = [];
try {
    $sql = "SELECT id, nome FROM usuarios WHERE tipo = 'vendedor' OR tipo = 'admin' ORDER BY nome";
    $stmt = $pdo->query($sql);
    $vendedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silenciar erro
}

// Buscar categorias do Mercado Livre - CÓDIGO CORRIGIDO
$categorias_ml = [];
try {
    // Primeiro tentamos buscar da tabela categorias_ml
    $sql = "SHOW TABLES LIKE 'categorias_ml'";
    $result = $pdo->query($sql);
    $tabela_existe = ($result->rowCount() > 0);
    
    if ($tabela_existe) {
        $sql = "SELECT id, nome FROM categorias_ml ORDER BY nome";
        $stmt = $pdo->query($sql);
        if ($stmt->rowCount() > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $categorias_ml[$row['id']] = [
                    'id' => $row['id'],
                    'nome' => $row['nome']
                ];
            }
        } else {
            // Se não há dados, inserimos algumas categorias padrão
            $categorias_padrao = [
                ['id' => 'MLB5672', 'nome' => 'Acessórios para Veículos'],
                ['id' => 'MLB1051', 'nome' => 'Celulares e Telefones'],
                ['id' => 'MLB1648', 'nome' => 'Computadores e Informática'],
                ['id' => 'MLB1574', 'nome' => 'Eletrônicos, Áudio e Vídeo'],
                ['id' => 'MLB1499', 'nome' => 'Ferramentas e Construção'],
                ['id' => 'MLB1039', 'nome' => 'Imóveis'],
                ['id' => 'MLB1196', 'nome' => 'Livros, Revistas e Comics'],
                ['id' => 'MLB1132', 'nome' => 'Brinquedos e Hobbies'],
                ['id' => 'MLB1430', 'nome' => 'Roupas e Acessórios'],
                ['id' => 'MLB1953', 'nome' => 'Mais Categorias']
            ];
            
            foreach ($categorias_padrao as $cat) {
                $sql = "INSERT INTO categorias_ml (id, nome) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$cat['id'], $cat['nome']]);
                $categorias_ml[$cat['id']] = $cat;
            }
        }
    } else {
        // Se a tabela não existe, vamos criá-la e adicionar categorias padrão
        $sql = "CREATE TABLE categorias_ml (
            id VARCHAR(50) NOT NULL PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            parent_id VARCHAR(50) NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $pdo->exec($sql);
        
        // Inserir categorias padrão
        $categorias_padrao = [
            ['id' => 'MLB5672', 'nome' => 'Acessórios para Veículos'],
            ['id' => 'MLB1051', 'nome' => 'Celulares e Telefones'],
            ['id' => 'MLB1648', 'nome' => 'Computadores e Informática'],
            ['id' => 'MLB1574', 'nome' => 'Eletrônicos, Áudio e Vídeo'],
            ['id' => 'MLB1499', 'nome' => 'Ferramentas e Construção'],
            ['id' => 'MLB1039', 'nome' => 'Imóveis'],
            ['id' => 'MLB1196', 'nome' => 'Livros, Revistas e Comics'],
            ['id' => 'MLB1132', 'nome' => 'Brinquedos e Hobbies'],
            ['id' => 'MLB1430', 'nome' => 'Roupas e Acessórios'],
            ['id' => 'MLB1953', 'nome' => 'Mais Categorias']
        ];
        
        foreach ($categorias_padrao as $cat) {
            $sql = "INSERT INTO categorias_ml (id, nome) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cat['id'], $cat['nome']]);
            $categorias_ml[$cat['id']] = $cat;
        }
    }
} catch (PDOException $e) {
    // Em caso de erro, usar categorias fixas
    $categorias_ml = [
        'MLB5672' => ['id' => 'MLB5672', 'nome' => 'Acessórios para Veículos'],
        'MLB1051' => ['id' => 'MLB1051', 'nome' => 'Celulares e Telefones'],
        'MLB1648' => ['id' => 'MLB1648', 'nome' => 'Computadores e Informática'],
        'MLB1574' => ['id' => 'MLB1574', 'nome' => 'Eletrônicos, Áudio e Vídeo'],
        'MLB1499' => ['id' => 'MLB1499', 'nome' => 'Ferramentas e Construção'],
        'MLB1953' => ['id' => 'MLB1953', 'nome' => 'Mais Categorias']
    ];
}

// Resto do código permanece o mesmo...
// Processar o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter dados do formulário
    $vendedor_id = isset($_POST['vendedor_id']) ? intval($_POST['vendedor_id']) : 0;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $sku = isset($_POST['sku']) ? trim($_POST['sku']) : null;
    $custo = isset($_POST['custo']) ? floatval($_POST['custo']) : 0;
    $peso = isset($_POST['peso']) ? floatval($_POST['peso']) : null;
    $dimensoes = isset($_POST['dimensoes']) ? trim($_POST['dimensoes']) : null;
    $categoria_id = isset($_POST['categoria_id']) ? trim($_POST['categoria_id']) : null;
    $descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : null;
    
    // Validar dados
    if (empty($nome)) {
        $erro = "O nome do produto é obrigatório.";
    } elseif ($custo < 0) {
        $erro = "O custo do produto não pode ser negativo.";
    } elseif ($vendedor_id <= 0) {
        $erro = "É obrigatório selecionar um vendedor.";
    } else {
        try {
            // Verificar se o SKU já existe para este vendedor
            if (!empty($sku)) {
                $sql = "SELECT id FROM produtos WHERE sku = ? AND usuario_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$sku, $vendedor_id]);
                if ($stmt->fetch()) {
                    $erro = "Este SKU já está em uso em outro produto deste vendedor.";
                }
            }
            
            if (empty($erro)) {
                // Verificar se a tabela e colunas existem antes de inserir
                try {
                    $check_table = "SHOW COLUMNS FROM produtos LIKE 'custo'";
                    $check_stmt = $pdo->query($check_table);
                    $column_exists = $check_stmt->rowCount() > 0;
                    
                    if (!$column_exists) {
                        // Tentar criar a coluna se não existir
                        $add_column = "ALTER TABLE produtos ADD COLUMN custo DECIMAL(10,2) NOT NULL DEFAULT 0";
                        $pdo->exec($add_column);
                    }
                } catch (PDOException $e) {
                    // Verificar se a tabela existe
                    $check_table = "SHOW TABLES LIKE 'produtos'";
                    $check_stmt = $pdo->query($check_table);
                    $table_exists = $check_stmt->rowCount() > 0;
                    
                    if (!$table_exists) {
                        // Criar a tabela se não existir
                        $create_table = "CREATE TABLE produtos (
                            id INT(11) NOT NULL AUTO_INCREMENT,
                            usuario_id INT(11) NOT NULL,
                            nome VARCHAR(255) NOT NULL,
                            sku VARCHAR(50) NULL,
                            custo DECIMAL(10,2) NOT NULL DEFAULT 0,
                            peso DECIMAL(10,3) NULL,
                            dimensoes VARCHAR(50) NULL,
                            categoria_id VARCHAR(50) NULL,
                            descricao TEXT NULL,
                            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (id),
                            KEY idx_usuario_id (usuario_id),
                            KEY idx_sku (sku)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                        $pdo->exec($create_table);
                    }
                }
                
                // Inserir o produto no banco de dados vinculado ao vendedor
                $sql = "INSERT INTO produtos (usuario_id, nome, sku, custo, peso, dimensoes, categoria_id, descricao) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $vendedor_id, // Usar o ID do vendedor selecionado
                    $nome,
                    $sku,
                    $custo,
                    $peso,
                    $dimensoes,
                    $categoria_id,
                    $descricao
                ]);
                
                $produto_id = $pdo->lastInsertId();
                
                // Verificar se há anúncios para vincular
                if (isset($_POST['vincular_anuncios']) && is_array($_POST['vincular_anuncios'])) {
                    foreach ($_POST['vincular_anuncios'] as $anuncio_id) {
                        // Garantir que o anúncio pertence ao mesmo vendedor
                        $sql = "UPDATE anuncios_ml SET produto_id = ? WHERE id = ? AND usuario_id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$produto_id, $anuncio_id, $vendedor_id]);
                    }
                }
                
                // Redirecionar para a página de produtos com mensagem de sucesso
                $_SESSION['mensagem'] = "Produto adicionado com sucesso!";
                $_SESSION['tipo_mensagem'] = "success";
                header("Location: " . BASE_URL . "/admin/produtos.php");
                exit;
            }
        } catch (PDOException $e) {
            $erro = "Erro ao adicionar produto: " . $e->getMessage();
        }
    }
    
    // Se chegou aqui, houve erro
    $mensagem = $erro;
    $tipo_mensagem = "danger";
}

// Buscar anúncios não vinculados para sugerir vinculação (apenas se um vendedor for selecionado)
$anuncios_nao_vinculados = [];
$vendedor_selecionado = isset($_POST['vendedor_id']) ? intval($_POST['vendedor_id']) : 0;

if ($vendedor_selecionado > 0) {
    try {
        $sql = "SELECT id, titulo, preco FROM anuncios_ml WHERE usuario_id = ? AND produto_id IS NULL ORDER BY titulo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$vendedor_selecionado]);
        $anuncios_nao_vinculados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Silenciar erro
    }
}

// Incluir cabeçalho
$page_title = 'Adicionar Produto';
include(BASE_PATH . '/includes/header_admin.php');

// Verificar se a função formatCurrency existe, caso contrário, defini-la
if (!function_exists('formatCurrency')) {
    function formatCurrency($value) {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Adicionar Produto</h2>
        <a href="<?php echo BASE_URL; ?>/admin/produtos.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar para Lista
        </a>
    </div>
    
    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensagem; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="" id="produtoForm">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="vendedor_id" class="form-label">Vendedor <span class="text-danger">*</span></label>
                        <select class="form-select" id="vendedor_id" name="vendedor_id" required>
                            <option value="">Selecione um vendedor...</option>
                            <?php foreach ($vendedores as $vendedor): ?>
                                <option value="<?php echo $vendedor['id']; ?>" <?php echo (isset($_POST['vendedor_id']) && $_POST['vendedor_id'] == $vendedor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vendedor['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Selecione o vendedor que será proprietário deste produto</div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome do Produto <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="sku" class="form-label">SKU</label>
                        <input type="text" class="form-control" id="sku" name="sku" value="<?php echo isset($_POST['sku']) ? htmlspecialchars($_POST['sku']) : ''; ?>">
                        <div class="form-text">Código único para identificar seu produto</div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="custo" class="form-label">Custo (R$) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control" id="custo" name="custo" value="<?php echo isset($_POST['custo']) ? htmlspecialchars($_POST['custo']) : ''; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="peso" class="form-label">Peso (kg)</label>
                        <input type="number" step="0.001" min="0" class="form-control" id="peso" name="peso" value="<?php echo isset($_POST['peso']) ? htmlspecialchars($_POST['peso']) : ''; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="dimensoes" class="form-label">Dimensões (cm)</label>
                        <input type="text" class="form-control" id="dimensoes" name="dimensoes" placeholder="Ex: 10x20x30" value="<?php echo isset($_POST['dimensoes']) ? htmlspecialchars($_POST['dimensoes']) : ''; ?>">
                    </div>
                </div>
                
           <div class="mb-3">
    <label for="categoria_id" class="form-label">Categoria</label>
    <select class="form-select" id="categoria_id" name="categoria_id">
        <option value="">Selecione uma categoria...</option>
        <?php 
        // Verificar se $categorias_ml está disponível
        if (!empty($categorias_ml)):
            foreach ($categorias_ml as $key => $categoria):
                $selected = (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $key) ? 'selected' : '';
        ?>
                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $selected; ?>>
                    <?php echo htmlspecialchars($categoria['nome']); ?>
                </option>
        <?php 
            endforeach;
        endif;
        ?>
    </select>
    <div class="form-text">Categoria do produto no Mercado Livre</div>
</div>

                
                <div class="mb-3">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php echo isset($_POST['descricao']) ? htmlspecialchars($_POST['descricao']) : ''; ?></textarea>
                </div>
                
                <div id="anuncios-container">
                    <?php if (!empty($anuncios_nao_vinculados)): ?>
                    <div class="mb-3">
                        <label class="form-label">Vincular a Anúncios</label>
                        <div class="card">
                            <div class="card-body">
                                <p class="text-muted">Selecione os anúncios que correspondem a este produto:</p>
                                <?php foreach ($anuncios_nao_vinculados as $anuncio): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="vincular_anuncios[]" value="<?php echo $anuncio['id']; ?>" id="anuncio_<?php echo $anuncio['id']; ?>">
                                    <label class="form-check-label" for="anuncio_<?php echo $anuncio['id']; ?>">
                                        <?php echo htmlspecialchars($anuncio['titulo']); ?> (<?php echo formatCurrency($anuncio['preco']); ?>)
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($vendedor_selecionado > 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Este vendedor não possui anúncios não vinculados disponíveis.
                    </div>
                    <?php endif; ?>
                </div>
                
                <div>
                    <button type="submit" class="btn btn-primary">Adicionar Produto</button>
                    <a href="<?php echo BASE_URL; ?>/admin/produtos.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Atualizar anúncios disponíveis quando o vendedor mudar
    const vendedorSelect = document.getElementById('vendedor_id');
    if (vendedorSelect) {
        vendedorSelect.addEventListener('change', function() {
            const vendedorId = this.value;
            if (vendedorId) {
                // Submeter o formulário com um campo hidden adicional para indicar que é só para atualizar os anúncios
                const form = document.getElementById('produtoForm');
                
                // Criar um input hidden temporário
                const tempInput = document.createElement('input');
                tempInput.type = 'hidden';
                tempInput.name = 'atualizar_anuncios';
                tempInput.value = '1';
                form.appendChild(tempInput);
                
                form.submit();
            }
        });
    }
});
</script>

<?php
// Incluir rodapé
include(BASE_PATH . '/includes/footer_admin.php');
?>
