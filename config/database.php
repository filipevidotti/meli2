<?php
/**
 * Configuração de conexão com banco de dados MySQL
 * 
 * Este ficheiro APENAS define as variáveis de conexão.
 * A conexão real é estabelecida na função getDbConnection() em functions/functions.php.
 * A criação das tabelas foi movida para um script separado (ex: setup_database.php) 
 * ou comentada para evitar execução automática.
 */

// Informações de conexão MySQL (fornecidas pelo usuário)
$servername = "mysql.annemacedo.com.br";
$username = "annemacedo02";
$password = "Vingador13Anne";
$dbname = "annemacedo02";
$charset = "utf8mb4"; // Recomendado para suporte completo a caracteres

// Data Source Name (DSN) para PDO MySQL
$dsn = "mysql:host=$servername;dbname=$dbname;charset=$charset";

// Opções do PDO (usadas em functions.php)
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lançar exceções em erros
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,    // Retornar arrays associativos por padrão
    PDO::ATTR_EMULATE_PREPARES   => false,               // Usar prepared statements nativos
];

/*
// --- LÓGICA DE CRIAÇÃO/RECRIÇÃO DE TABELAS (MOVIDA OU COMENTADA) ---
// Esta lógica NÃO deve ser executada automaticamente a cada inclusão.
// Execute manualmente ou através de um script de setup dedicado quando necessário.

try {
    // Conectar ao banco de dados MySQL
    echo "Tentando conectar a MySQL...\n";
    $db = new PDO($dsn, $username, $password, $options);
    echo "Conexão MySQL estabelecida.\n";

    // --- Recriação das Tabelas (Ordem correta para evitar FK constraints) ---

    // 1. Drop tabelas dependentes primeiro
    echo "Removendo tabela 'vendas_processadas' (se existir)...\n";
    $db->exec("DROP TABLE IF EXISTS vendas_processadas;");
    echo "Removendo tabela 'vendas' (antiga, se existir)...\n";
    $db->exec("DROP TABLE IF EXISTS vendas;"); 

    // 2. Drop tabelas referenciadas
    echo "Removendo tabela 'produtos' (se existir)...\n";
    $db->exec("DROP TABLE IF EXISTS produtos;");
    echo "Removendo tabela 'tokens' (se existir)...\n";
    $db->exec("DROP TABLE IF EXISTS tokens;");
    echo "Removendo tabela 'configuracoes' (se existir)...\n";
    $db->exec("DROP TABLE IF EXISTS configuracoes;");
    echo "Removendo tabela 'tipos_despesa' (se existir)...\n";
    $db->exec("DROP TABLE IF EXISTS tipos_despesa;");
    echo "Removendo tabela 'despesas' (se existir)...\n";
    $db->exec("DROP TABLE IF EXISTS despesas;");

    // 3. Criar tabelas na ordem correta (referenciadas primeiro)
    echo "Recriando tabela 'configuracoes'...\n";
    $db->exec("CREATE TABLE configuracoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chave VARCHAR(100) UNIQUE NOT NULL,
        valor TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Tabela 'configuracoes' recriada com sucesso.\n";

    echo "Recriando tabela 'tokens'...\n";
    $db->exec("CREATE TABLE tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chave VARCHAR(100) UNIQUE NOT NULL, 
        valor TEXT, 
        expires_at BIGINT NULL COMMENT 'Timestamp de expiração do token',
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
    echo "Tabela 'tokens' recriada com sucesso.\n";

    echo "Recriando tabela 'produtos' (nova estrutura)...\n";
    $db->exec("CREATE TABLE produtos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sku VARCHAR(100) UNIQUE NOT NULL COMMENT 'SKU único do produto local',
        nome VARCHAR(255) NOT NULL COMMENT 'Nome do produto local',
        preco_custo DECIMAL(10, 2) NOT NULL COMMENT 'Custo unitário de aquisição/produção',
        peso DECIMAL(10, 3) NULL COMMENT 'Peso em KG',
        largura DECIMAL(10, 2) NULL COMMENT 'Largura em CM',
        altura DECIMAL(10, 2) NULL COMMENT 'Altura em CM',
        comprimento DECIMAL(10, 2) NULL COMMENT 'Comprimento em CM',
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Tabela 'produtos' (nova estrutura) recriada com sucesso.\n";

    echo "Recriando tabela 'tipos_despesa'...\n";
    $db->exec("CREATE TABLE tipos_despesa (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        categoria VARCHAR(100) NOT NULL,
        INDEX idx_categoria (categoria)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Tabela 'tipos_despesa' recriada com sucesso.\n";

    echo "Recriando tabela 'despesas'...\n";
    $db->exec("CREATE TABLE despesas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo_despesa_id INT NOT NULL,
        valor DECIMAL(10, 2) NOT NULL,
        data_despesa DATE NOT NULL,
        descricao TEXT NULL,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_data_despesa (data_despesa),
        FOREIGN KEY (tipo_despesa_id) REFERENCES tipos_despesa(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Tabela 'despesas' recriada com sucesso.\n";

    echo "Recriando tabela 'vendas_processadas'...\n";
    $db->exec("CREATE TABLE vendas_processadas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ml_order_id BIGINT UNSIGNED NOT NULL COMMENT 'ID da Venda no Mercado Livre',
        ml_item_id VARCHAR(50) NOT NULL COMMENT 'ID do Item (anúncio) no Mercado Livre',
        sku VARCHAR(100) NOT NULL COMMENT 'SKU do produto vendido (obtido do anúncio)',
        produto_id INT NULL COMMENT 'FK para a tabela produtos (local)',
        quantidade INT NOT NULL COMMENT 'Quantidade vendida deste item',
        preco_venda_unitario DECIMAL(10, 2) NOT NULL COMMENT 'Preço unitário de venda no ML',
        custo_unitario DECIMAL(10, 2) NULL COMMENT 'Custo unitário do produto (da tabela produtos)',
        taxa_ml DECIMAL(10, 2) NULL COMMENT 'Taxa cobrada pelo ML nesta venda/item',
        custo_envio DECIMAL(10, 2) NULL COMMENT 'Custo do envio cobrado do vendedor',
        lucro_total DECIMAL(10, 2) NULL COMMENT 'Lucro calculado para este item/quantidade',
        data_venda DATETIME NOT NULL COMMENT 'Data da venda no ML',
        data_processamento DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Quando este registro foi criado/atualizado',
        INDEX idx_sku (sku),
        INDEX idx_data_venda (data_venda),
        UNIQUE KEY unique_order_item (ml_order_id, ml_item_id),
        FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "Tabela 'vendas_processadas' recriada com sucesso.\n";

    // --- Inserção de Configurações Padrão --- 
    echo "Inserindo configurações padrão...\n";
    $sqlInsertConfig = "INSERT INTO configuracoes (chave, valor) VALUES
        ('ml_client_id', '2616566753532500'),
        ('ml_client_secret', '4haTLnBN8rWyOPfDQ8N1erfTMBhZaXOz'),
        ('ml_redirect_url', 'https://annemacedo.com.br/calculadora/callback.php'), 
        ('custos_adicionais_padrao', '5'),
        ('margem_lucro_minima', '15')
    ON DUPLICATE KEY UPDATE valor=VALUES(valor);";
    $db->exec($sqlInsertConfig);
    echo "Configurações padrão inseridas.\n";

    echo "\nScript de setup do banco de dados concluído com sucesso!\n";

} catch (PDOException $e) {
    // Em caso de erro na conexão ou operações
    error_log(
        "Erro no script de setup do banco de dados (MySQL): " . $e->getMessage() . 
        "\nSQLSTATE: " . $e->getCode() . 
        "\nTrace: " . $e->getTraceAsString()
    );
    die(
        "Erro fatal durante a configuração do banco de dados. Verifique os logs do servidor web para mais detalhes. " . 
        "Detalhe: " . htmlspecialchars($e->getMessage()) . 
        " (Código: " . $e->getCode() . ")"
    );
}
*/

?>
