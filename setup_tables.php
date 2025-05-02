<?php
require_once('init.php');

// Verificar se o arquivo já foi executado
if (isset($_GET['setup']) && $_GET['setup'] == 'confirm') {
    try {
        // Array para armazenar o status de cada tabela
        $tabelas_status = [];
        
        // 1. Criar a tabela vendedores
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `vendedores` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `usuario_id` int(11) NOT NULL COMMENT 'ID do usuário relacionado',
                  `nome_fantasia` varchar(255) DEFAULT NULL,
                  `cnpj` varchar(20) DEFAULT NULL,
                  `telefone` varchar(20) DEFAULT NULL,
                  `endereco` text DEFAULT NULL,
                  `ml_user_id` varchar(50) DEFAULT NULL COMMENT 'ID do usuário no Mercado Livre',
                  `ml_nickname` varchar(100) DEFAULT NULL COMMENT 'Nickname no Mercado Livre',
                  `ml_email` varchar(255) DEFAULT NULL COMMENT 'Email no Mercado Livre',
                  `ml_access_token` text DEFAULT NULL COMMENT 'Token de acesso da API do Mercado Livre',
                  `ml_refresh_token` text DEFAULT NULL COMMENT 'Token de atualização da API do Mercado Livre',
                  `ml_token_expires` datetime DEFAULT NULL COMMENT 'Data de expiração do token',
                  `data_criacao` datetime DEFAULT current_timestamp(),
                  `data_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `usuario_id` (`usuario_id`),
                  CONSTRAINT `fk_vendedores_usuarios` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $tabelas_status['vendedores'] = 'Criada com sucesso';
        } catch (PDOException $e) {
            $tabelas_status['vendedores'] = 'Erro: ' . $e->getMessage();
        }
        
        // 2. Criar a tabela vendas
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `vendas` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `vendedor_id` int(11) NOT NULL COMMENT 'ID do vendedor relacionado',
                  `produto` varchar(255) NOT NULL COMMENT 'Nome do produto vendido',
                  `data_venda` date NOT NULL COMMENT 'Data da venda',
                  `valor_venda` decimal(10,2) NOT NULL COMMENT 'Valor total da venda',
                  `custo_produto` decimal(10,2) NOT NULL COMMENT 'Custo do produto',
                  `taxa_ml` decimal(10,2) NOT NULL COMMENT 'Taxa do Mercado Livre',
                  `custo_envio` decimal(10,2) DEFAULT 0.00 COMMENT 'Custo de envio',
                  `custos_adicionais` decimal(10,2) DEFAULT 0.00 COMMENT 'Outros custos',
                  `lucro` decimal(10,2) NOT NULL COMMENT 'Lucro calculado',
                  `margem_lucro` decimal(10,2) NOT NULL COMMENT 'Margem de lucro em porcentagem',
                  `categoria_ml` varchar(100) DEFAULT NULL COMMENT 'Categoria do produto no ML',
                  `ml_order_id` varchar(100) DEFAULT NULL COMMENT 'ID do pedido no Mercado Livre',
                  `notas` text DEFAULT NULL COMMENT 'Observações sobre a venda',
                  `data_criacao` datetime DEFAULT current_timestamp(),
                  `data_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `idx_vendedor_data` (`vendedor_id`,`data_venda`),
                  CONSTRAINT `fk_vendas_vendedor` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $tabelas_status['vendas'] = 'Criada com sucesso';
        } catch (PDOException $e) {
            $tabelas_status['vendas'] = 'Erro: ' . $e->getMessage();
        }
        
        // Verificar e criar usuários vendedores na tabela vendedores
        $sql = "SELECT * FROM usuarios WHERE tipo = 'vendedor' AND id NOT IN (SELECT usuario_id FROM vendedores)";
        $stmt = $pdo->query($sql);
        $vendedores_criados = 0;
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sql = "INSERT INTO vendedores (usuario_id, nome_fantasia) VALUES (?, ?)";
            $insert = $pdo->prepare($sql);
            $insert->execute([$row['id'], $row['nome']]);
            $vendedores_criados++;
        }
        
        echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
        echo "<h2 style='color: #28a745;'>Configuração realizada</h2>";
        
        echo "<div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>Resumo das operações:</h3>";
        echo "<ul>";
        foreach ($tabelas_status as $tabela => $status) {
            $icone = (strpos($status, 'Erro') === false) ? "✅" : "❌";
            echo "<li><strong>Tabela {$tabela}:</strong> {$icone} {$status}</li>";
        }
        echo "<li><strong>Novos registros de vendedores:</strong> {$vendedores_criados}</li>";
        echo "</ul>";
        echo "</div>";
        
        // Verificar se existe pelo menos um usuário admin
        $sql = "SELECT COUNT(*) FROM usuarios WHERE tipo = 'admin'";
        $count = $pdo->query($sql)->fetchColumn();
        
        if ($count == 0) {
            echo "<div style='background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>Aviso:</h3>";
            echo "<p>Não foi encontrado nenhum usuário administrador. É recomendado criar um.</p>";
            echo "<a href='create_admin.php' style='display: inline-block; padding: 10px 15px; background-color: #ffc107; color: #212529; text-decoration: none; border-radius: 4px;'>Criar Administrador</a>";
            echo "</div>";
        }
        
        echo "<a href='login.php' style='display: inline-block; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px;'>Ir para Login</a>";
        echo "<p style='margin-top: 20px; color: #dc3545; font-weight: bold;'>ATENÇÃO: Por segurança, remova este arquivo após o uso!</p>";
        echo "</div>";
        
    } catch (PDOException $e) {
        echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
        echo "<h2 style='color: #dc3545;'>Erro</h2>";
        echo "<p>Ocorreu um erro ao configurar o banco de dados:</p>";
        echo "<pre style='background-color: #f8f9fa; padding: 10px; border-radius: 5px; overflow: auto;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<a href='?setup=confirm' style='display: inline-block; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px;'>Tentar novamente</a>";
        echo "</div>";
    }
} else {
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
    echo "<h2>Configurar Tabelas do Sistema</h2>";
    echo "<p>Este script criará as tabelas essenciais necessárias para o funcionamento do sistema, incluindo:</p>";
    echo "<ul>";
    echo "<li><strong>vendedores</strong>: Armazena informações específicas dos vendedores</li>";
    echo "<li><strong>vendas</strong>: Registra as vendas realizadas pelos vendedores</li>";
    echo "</ul>";
    echo "<div style='background-color: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>Observação:</h4>";
    echo "<p>Este script é seguro para executar mesmo se as tabelas já existirem. Os dados existentes serão preservados.</p>";
    echo "</div>";
    echo "<p>Para continuar com a configuração, clique no botão abaixo:</p>";
    echo "<a href='?setup=confirm' style='display: inline-block; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Configurar Tabelas</a>";
    echo "<p style='margin-top: 20px; color: #dc3545;'><strong>ATENÇÃO:</strong> Por segurança, remova este arquivo após a configuração!</p>";
    echo "</div>";
}
?>
