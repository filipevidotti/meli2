<?php
// Verificar se a função já existe
if (!function_exists('obterConfiguracoes')) {
    // Definimos a função diretamente aqui para evitar dependências circulares
    function obterConfiguracoes() {
        global $pdo;
        
        try {
            if (isset($pdo)) {
                $sql = "SELECT * FROM configuracoes LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            // Falha silenciosa - tabela provavelmente não existe ainda
        }
        
        // Retorna configurações padrão se não encontrar no banco de dados
        return [
            'ml_client_id' => '',
            'ml_client_secret' => '',
            'ml_redirect_url' => '',
            'custo_adicional_padrao' => 5,
            'margem_lucro_minima' => 15,
        ];
    }
}

// Configurações gerais
$site_name = "CalcMeli";
$site_description = "Sistema de Cálculo para Mercado Livre";

// Configurações de e-mail
$email_from = "contato@seu-dominio.com";
$email_name = "CalcMeli";

// Carregar configurações do banco de dados
$config = obterConfiguracoes();

// Configurações da API do Mercado Livre
$ml_client_id = $config['ml_client_id'] ?? "";
$ml_client_secret = $config['ml_client_secret'] ?? "";
$ml_redirect_url = $config['ml_redirect_url'] ?? "";

// Custos adicionais padrão (em porcentagem)
$custo_adicional_padrao = $config['custo_adicional_padrao'] ?? 5;

// Margem de lucro mínima (em porcentagem)
$margem_lucro_minima = $config['margem_lucro_minima'] ?? 15;

// Função para atualizar configurações
function updateConfig($data) {
    global $pdo;
    
    try {
        // Verificar se já existem configurações
        $sql = "SELECT COUNT(*) FROM configuracoes";
        $count = $pdo->query($sql)->fetchColumn();
        
        if ($count > 0) {
            // Atualizar configurações existentes
            $sql = "UPDATE configuracoes SET 
                    ml_client_id = :ml_client_id,
                    ml_client_secret = :ml_client_secret,
                    ml_redirect_url = :ml_redirect_url,
                    custo_adicional_padrao = :custo_adicional_padrao,
                    margem_lucro_minima = :margem_lucro_minima
                    WHERE id = 1";
        } else {
            // Inserir novas configurações
            $sql = "INSERT INTO configuracoes (
                    ml_client_id, ml_client_secret, ml_redirect_url,
                    custo_adicional_padrao, margem_lucro_minima) 
                    VALUES (
                    :ml_client_id, :ml_client_secret, :ml_redirect_url,
                    :custo_adicional_padrao, :margem_lucro_minima)";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':ml_client_id' => $data['ml_client_id'],
            ':ml_client_secret' => $data['ml_client_secret'],
            ':ml_redirect_url' => $data['ml_redirect_url'],
            ':custo_adicional_padrao' => $data['custo_adicional_padrao'],
            ':margem_lucro_minima' => $data['margem_lucro_minima']
        ]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
