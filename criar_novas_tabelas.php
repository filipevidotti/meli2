<?php
require_once('init.php');

// Verificar se o usuário está logado e é administrador
if (!isAdmin()) {
    die("Acesso restrito. Você precisa ser um administrador para executar esta operação.");
}

// Iniciar transação
$pdo->beginTransaction();

try {
    // 1. Verificar a estrutura atual da tabela produtos
    echo "<h2>Verificando estrutura atual da tabela produtos...</h2>";
    
    $colunas_atuais = [];
    $resultado = $pdo->query("DESCRIBE produtos");
    while ($row = $resultado->fetch(PDO::FETCH_ASSOC)) {
        $colunas_atuais[$row['Field']] = $row;
        echo "Coluna encontrada: {$row['Field']} ({$row['Type']})<br>";
    }
    
    echo "<hr>";
    
    // 2. Renomear a tabela atual para backup
    echo "<h2>Criando backup da tabela produtos...</h2>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos_backup LIKE produtos");
    $pdo->exec("INSERT INTO produtos_backup SELECT * FROM produtos");
    echo "Backup criado com sucesso: produtos_backup<br>";
    
    echo "<hr>";
    
    // 3. Adicionar novas colunas (se necessário)
    echo "<h2>Atualizando a estrutura da tabela produtos...</h2>";
    
    $colunas_necessarias = [
        'sku' => "ADD COLUMN `sku` VARCHAR(100) NULL AFTER `nome`",
        'peso' => "ADD COLUMN `peso` DECIMAL(10,3) NULL AFTER `custo`",
        'dimensoes' => "ADD COLUMN `dimensoes` VARCHAR(100) NULL AFTER `peso`",
        'categoria_id' => "ADD COLUMN `categoria_id` VARCHAR(100) NULL AFTER `dimensoes`",
        'descricao' => "ADD COLUMN `descricao` TEXT NULL AFTER `categoria_id`"
    ];
    
    foreach ($colunas_necessarias as $coluna => $comando) {
        if (!isset($colunas_atuais[$coluna])) {
            $pdo->exec("ALTER TABLE produtos $comando");
            echo "Coluna adicionada: $coluna<br>";
        } else {
            echo "Coluna já existe: $coluna<br>";
        }
    }
    
    // 4. Verificar e corrigir índices
    $indices = $pdo->query("SHOW INDEX FROM produtos")->fetchAll(PDO::FETCH_ASSOC);
    $tem_idx_usuario_id = false;
    
    foreach ($indices as $indice) {
        if ($indice['Column_name'] == 'usuario_id' && $indice['Key_name'] == 'idx_usuario_id') {
            $tem_idx_usuario_id = true;
            break;
        }
    }
    
    if (!$tem_idx_usuario_id) {
        $pdo->exec("CREATE INDEX idx_usuario_id ON produtos (usuario_id)");
        echo "Índice idx_usuario_id criado<br>";
    } else {
        echo "Índice idx_usuario_id já existe<br>";
    }
    
    echo "<hr>";
    
    // 5. Verificar foreign key
    $foreign_keys = $pdo->query("SELECT * 
                              FROM information_schema.TABLE_CONSTRAINTS 
                              WHERE CONSTRAINT_TYPE = 'FOREIGN KEY' 
                              AND TABLE_NAME = 'produtos'
                              AND CONSTRAINT_NAME = 'fk_produtos_usuario'")->fetchAll();
    
    if (empty($foreign_keys)) {
        try {
            $pdo->exec("ALTER TABLE produtos 
                      ADD CONSTRAINT fk_produtos_usuario 
                      FOREIGN KEY (usuario_id) REFERENCES usuarios (id) 
                      ON DELETE CASCADE");
            echo "Foreign key fk_produtos_usuario criada<br>";
        } catch (PDOException $e) {
            echo "Aviso: Não foi possível criar a foreign key: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "Foreign key fk_produtos_usuario já existe<br>";
    }
    
    // 6. Confirmar transação
    $pdo->commit();
    
    echo "<hr>";
    echo "<h2 style='color: green;'>Atualização concluída com sucesso!</h2>";
    echo "<p>A tabela produtos foi atualizada para o novo formato e um backup foi criado.</p>";
    
} catch (PDOException $e) {
    // Em caso de erro, reverter alterações
    $pdo->rollBack();
    echo "<h2 style='color: red;'>Erro durante a atualização:</h2>";
    echo "<p>{$e->getMessage()}</p>";
    echo "<p>As alterações foram revertidas.</p>";
}
?>
