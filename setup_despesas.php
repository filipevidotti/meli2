<?php
include_once 'includes/conexao.php';

try {
    global $conn;
    
    // Criar tabela tipos_despesa
    $sql = "CREATE TABLE IF NOT EXISTS tipos_despesa (
        id INT AUTO_INCREMENT PRIMARY KEY,
        categoria VARCHAR(100) NOT NULL,
        nome VARCHAR(100) NOT NULL,
        descricao TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    
    // Criar tabela despesas
    $sql = "CREATE TABLE IF NOT EXISTS despesas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data DATE NOT NULL,
        tipo_id INT NOT NULL,
        descricao VARCHAR(255),
        valor DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tipo_id) REFERENCES tipos_despesa(id)
    )";
    $conn->exec($sql);
    
    // Inserir alguns tipos padrão se a tabela estiver vazia
    $sql = "SELECT COUNT(*) FROM tipos_despesa";
    $count = $conn->query($sql)->fetchColumn();
    
    if ($count == 0) {
        // Adicionar tipos padrão
        $tipos = [
            // Categoria Impostos
            ['Impostos', 'Simples Nacional', 'Imposto mensal sobre faturamento'],
            ['Impostos', 'ICMS', 'Imposto sobre Circulação de Mercadorias e Serviços'],
            ['Impostos', 'ISS', 'Imposto Sobre Serviços'],
            ['Impostos', 'IRPJ', 'Imposto de Renda de Pessoa Jurídica'],
            
            // Categoria Marketing
            ['Marketing', 'Anúncios no Mercado Livre', 'Investimentos em anúncios pagos no Mercado Livre'],
            ['Marketing', 'Google Ads', 'Investimentos em anúncios no Google'],
            ['Marketing', 'Facebook e Instagram Ads', 'Anúncios nas redes sociais'],
            ['Marketing', 'Agência de Marketing', 'Pagamentos a agências ou profissionais de marketing'],
            
            // Categoria Operacional
            ['Operacional', 'Embalagens', 'Custos com material de embalagem'],
            ['Operacional', 'Materiais de Escritório', 'Compra de materiais para escritório'],
            ['Operacional', 'Software e Ferramentas', 'Assinaturas de softwares e ferramentas'],
            ['Operacional', 'Equipamentos', 'Compra ou aluguel de equipamentos']
        ];
        
        $sql = "INSERT INTO tipos_despesa (categoria, nome, descricao) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        foreach ($tipos as $tipo) {
            $stmt->execute($tipo);
        }
    }
    
    echo "<div class='alert alert-success'>Tabelas de despesas criadas com sucesso!</div>";
    
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
}
?>