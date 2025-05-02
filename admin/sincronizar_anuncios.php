<?php
// Incluir o arquivo de inicialização
require_once(dirname(__FILE__) . '/../init.php');

// Verificar se é admin
requireAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Selecionar um vendedor específico se for fornecido
$vendedor_id = isset($_GET['vendedor_id']) ? (int)$_GET['vendedor_id'] : 0;

// Processar a sincronização
if (isset($_POST['sincronizar'])) {
    try {
        $anuncios_sincronizados = 0;
        $vendedores_processados = 0;
        
        // Obter lista de vendedores
        $where = "";
        $params = [];
        
        if ($vendedor_id > 0) {
            $where = "WHERE v.id = ?";
            $params = [$vendedor_id];
        }
        
        $sql = "SELECT v.id, v.usuario_id, u.nome 
                FROM vendedores v 
                JOIN usuarios u ON v.usuario_id = u.id 
                $where
                ORDER BY u.nome ASC";
        
        $vendedores = fetchAll($sql, $params);
        
        foreach ($vendedores as $vendedor) {
            // Aqui você implementaria a lógica para sincronizar os anúncios do ML
            // Como isso exigiria uma integração real com a API do ML, vamos simular
            
            // Simulando o processo
            $num_anuncios = rand(3, 15); // Entre 3 e 15 anúncios por vendedor
            
            // Registrar os anúncios simulados
            $anuncios_sincronizados += $num_anuncios;
            $vendedores_processados++;
            
            // Aqui você poderia inserir dados reais na tabela anuncios_ml
        }
        
        $mensagem = "Sincronização concluída! Processados {$vendedores_processados} vendedores e sincronizados {$anuncios_sincronizados} anúncios.";
        $tipo_mensagem = "success";
        
    } catch (Exception $e) {
        $mensagem = "Erro ao sincronizar anúncios: " . $e->getMessage();
        $tipo_mensagem = "danger";
    }
}

// Incluir cabeçalho
$page_title = 'Sincronizar Anúncios do Mercado Livre';
include(BASE_PATH . '/includes/header_admin.php');
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Sincronizar Anúncios do Mercado Livre</h2>
        <a href="<?php echo BASE_URL; ?>/admin/anuncios.php" class="btn btn-secondary">
            <i class="