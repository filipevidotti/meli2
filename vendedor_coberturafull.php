<?php
// Variável para controlar a inclusão das bibliotecas de exportação
$include_export_libs = true;

// Título da página
$page_title = "Relatório de Cobertura de Estoque Full - CalcMeli";

// Incluir bibliotecas extras
$include_datatables = true;

// Incluir arquivos de configuração e cabeçalho
require_once 'config.php';
require_once 'header.php';


// Buscar token válido do Mercado Livre
$ml_token = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM mercadolivre_tokens 
        WHERE usuario_id = ? AND revogado = 0 AND data_expiracao > NOW()
        ORDER BY data_expiracao DESC 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $ml_token = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erro ao buscar token: " . $e->getMessage();
}

// Verificar se tem token válido
$access_token = '';
if (!empty($ml_token) && !empty($ml_token['access_token'])) {
    $access_token = $ml_token['access_token'];
} else {
    $error_message = "Você não está conectado ao Mercado Livre ou seu token expirou. Por favor, faça a autenticação novamente.";
}

// Verificar qual usuário está usando o token
$ml_user_id = '';
$ml_nickname = '';
if (!empty($access_token)) {
    $ch = curl_init('https://api.mercadolibre.com/users/me');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($status == 200) {
        $user_data = json_decode($response, true);
        $ml_user_id = $user_data['id'] ?? 'Desconhecido';
        $ml_nickname = $user_data['nickname'] ?? 'Desconhecido';
    }
}

// Arrays para armazenar os dados dos anúncios
$anuncios_full = [];
$mensagem = '';
$tipo_mensagem = '';
$total_anuncios = 0;
$tipo_ordenacao = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'dias';

// Função para buscar os anúncios Full do usuário
function buscarAnunciosFull($access_token, $user_id) {
    $anuncios = [];
    $offset = 0;
    $limit = 50;
    $total = 0;
    
    // Adicionar logs para debug
    error_log("Iniciando busca de anúncios para o usuário: $user_id");
    
    try {
        // Buscar IDs dos anúncios do usuário
        do {
            $url = "https://api.mercadolibre.com/users/{$user_id}/items/search?limit={$limit}&offset={$offset}";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token
            ]);
            
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            error_log("Busca de IDs de anúncios: Status $status");
            
            if ($status != 200) {
                error_log("Erro ao buscar anúncios: HTTP $status - $response");
                return ['error' => 'Erro ao buscar anúncios: HTTP ' . $status];
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['results']) && is_array($data['results'])) {
                // Buscar informações detalhadas dos anúncios em lotes de 20
                $item_chunks = array_chunk($data['results'], 20);
                
                foreach ($item_chunks as $chunk) {
                    $ids = implode(',', $chunk);
                    $items_url = "https://api.mercadolibre.com/items?ids=" . $ids;
                    
                    $ch_items = curl_init($items_url);
                    curl_setopt($ch_items, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch_items, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $access_token
                    ]);
                    
                    $items_response = curl_exec($ch_items);
                    $items_status = curl_getinfo($ch_items, CURLINFO_HTTP_CODE);
                    curl_close($ch_items);
                    
                    error_log("Busca de detalhes de anúncios (lote): Status $items_status");
                    
                    if ($items_status == 200) {
                        $items_data = json_decode($items_response, true);
                        
                        foreach ($items_data as $item) {
                            if (isset($item['body']) && isset($item['body']['shipping']) && 
                                isset($item['body']['shipping']['logistic_type']) && 
                                $item['body']['shipping']['logistic_type'] === 'fulfillment') {
                                
                                $anuncio = $item['body'];
                                error_log("Processando anúncio FULL: " . $anuncio['id']);
                                
                                // Buscar as vendas dos últimos 60 dias (versão corrigida)
                                $vendas_60_dias = buscarVendasAnuncio($access_token, $anuncio['id'], 60, $user_id);
                                error_log("Vendas 60 dias para " . $anuncio['id'] . ": " . count($vendas_60_dias));
                                
                                $vendas_30_dias = array_filter($vendas_60_dias, function($venda) {
                                    return strtotime($venda['date_created']) >= strtotime('-30 days');
                                });
                                error_log("Vendas 30 dias para " . $anuncio['id'] . ": " . count($vendas_30_dias));
                                
                                // Calculando dias de cobertura de estoque
                                $vendas_30_dias_count = count($vendas_30_dias);
                                $media_vendas_diarias = $vendas_30_dias_count > 0 ? $vendas_30_dias_count / 30 : 0;
                                $dias_cobertura = $media_vendas_diarias > 0 ? $anuncio['available_quantity'] / $media_vendas_diarias : 'INF';
                                
                                $anuncios[] = [
                                    'id' => $anuncio['id'],
                                    'titulo' => $anuncio['title'],
                                    'thumbnail' => $anuncio['thumbnail'],
                                    'sku' => $anuncio['seller_custom_field'] ?? '-',
                                    'vendas_60_dias' => count($vendas_60_dias),
                                    'vendas_30_dias' => $vendas_30_dias_count,
                                    'estoque' => $anuncio['available_quantity'],
                                    'dias_cobertura' => $dias_cobertura,
                                    'preco' => $anuncio['price'],
                                    'link' => $anuncio['permalink'],
                                    'status' => $anuncio['status']
                                ];
                            }
                        }
                    }
                }
            }
            
            $total = $data['paging']['total'] ?? 0;
            $offset += $limit;
            
        } while ($offset < $total);
        
        error_log("Total de anúncios FULL encontrados: " . count($anuncios));
        return $anuncios;
        
    } catch (Exception $e) {
        error_log("Exceção ao buscar anúncios: " . $e->getMessage());
        return ['error' => 'Erro ao processar anúncios: ' . $e->getMessage()];
    }
}

// Função para buscar as vendas de um anúncio específico
function buscarVendasAnuncio($access_token, $item_id, $dias, $seller_id) {
    $vendas = [];
    $offset = 0;
    $limit = 50;
    
    // Formatar datas no formato ISO 8601 que a API do ML espera
    $data_inicio = date('Y-m-d\TH:i:s.000-03:00', strtotime('-' . $dias . ' days'));
    $data_atual = date('Y-m-d\TH:i:s.000-03:00');
    
    try {
        do {
            // Construir URL usando o ID do vendedor e formato de data correto
            $url = "https://api.mercadolibre.com/orders/search?" . 
                   "seller=" . $seller_id . 
                   "&item=" . urlencode($item_id) . 
                   "&order.date_created.from=" . urlencode($data_inicio) . 
                   "&order.date_created.to=" . urlencode($data_atual) . 
                   "&limit=" . $limit . 
                   "&offset=" . $offset;
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token
            ]);
            
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            // Para debug - registrar a URL e o status da resposta
            error_log("Buscando vendas: $url - Status: $status");
            if (!empty($curl_error)) {
                error_log("Erro cURL: $curl_error");
            }
            
            if ($status == 200) {
                $data = json_decode($response, true);
                
                if (isset($data['results']) && is_array($data['results'])) {
                    foreach ($data['results'] as $order) {
                        // Considerar apenas pedidos não cancelados e completados
                        if ($order['status'] !== 'cancelled') {
                            // Adicionar a venda à lista
                            $vendas[] = [
                                'date_created' => $order['date_created'],
                                'id' => $order['id'],
                                'status' => $order['status']
                            ];
                        }
                    }
                }
                
                // Verificar se há mais resultados a serem paginados
                $total = $data['paging']['total'] ?? 0;
                $offset += $limit;
                
            } else {
                // Registrar erro para debug
                error_log("Erro na API do ML ao buscar vendas. Status: $status - Resposta: $response");
                break;
            }
            
        } while ($offset < $total);
        
    } catch (Exception $e) {
        error_log("Exceção ao buscar vendas: " . $e->getMessage());
    }
    
    return $vendas;
}

// Executar a busca se o usuário estiver autenticado
if (!empty($access_token) && !empty($ml_user_id)) {
    $loading = true;
    $anuncios_full = buscarAnunciosFull($access_token, $ml_user_id);
    $loading = false;
    
    if (isset($anuncios_full['error'])) {
        $mensagem = $anuncios_full['error'];
        $tipo_mensagem = 'danger';
        $anuncios_full = [];
    } else {
        $total_anuncios = count($anuncios_full);
        
        // Ordenar os anúncios
        if ($tipo_ordenacao === 'dias') {
            // Ordenar por dias de cobertura (crescente)
            usort($anuncios_full, function($a, $b) {
                if ($a['dias_cobertura'] === 'INF' && $b['dias_cobertura'] === 'INF') return 0;
                if ($a['dias_cobertura'] === 'INF') return 1;
                if ($b['dias_cobertura'] === 'INF') return -1;
                return $a['dias_cobertura'] - $b['dias_cobertura'];
            });
        } else {
            // Ordenar por valor de venda (decrescente)
            usort($anuncios_full, function($a, $b) {
                return $b['vendas_30_dias'] - $a['vendas_30_dias'];
            });
        }
    }
}

// Função para formatar valores monetários
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}
?>
 <?php require_once 'barra.php'; ?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Relatório de Cobertura de Estoque Full - Demonstração</h1>
        <div class="text-muted small">Apresentando anúncios com maior valor de Cobertura de Estoque no Topo</div>
    </div>
    
    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
            <?php echo $mensagem; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($ml_user_id) && empty($mensagem)): ?>
        <div class="card mb-4">
            <div class="card-body">
                <!-- Botões de ação -->
                <div class="d-flex justify-content-between mb-3">
                    <div>
<a href="exportar_cobertura.php?ordenar=<?php echo $tipo_ordenacao; ?>" class="btn btn-success" target="_blank">
    <i class="fas fa-file-excel"></i> Exportar para Excel
</a>
                    </div>
                    <div class="btn-group">
                        <a href="?ordenar=dias" class="btn <?php echo $tipo_ordenacao === 'dias' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            ordenar por DIAS de ESTOQUE
                        </a>
                        <a href="?ordenar=venda" class="btn <?php echo $tipo_ordenacao === 'venda' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            ordenar por VENDA
                        </a>
                    </div>
                </div>
                
                <?php if ($loading): ?>
                    <div class="text-center my-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2">Carregando dados de anúncios e vendas...</p>
                    </div>
                <?php elseif (empty($anuncios_full)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Nenhum anúncio Full encontrado para sua conta.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="tabelaCobertura">
                            <thead class="table-primary">
                                <tr>
                                    <th style="width: 70px;"></th>
                                    <th>Identificador do MELI / SKU</th>
                                    <th>Vendas<br>60 dias / 30 dias</th>
                                    <th>Trânsito FULL</th>
                                    <th>Estoque Total<br>do Anúncio no Full</th>
                                    <th>Dias de Cobertura<br>de Estoque</th>
                                    <th>Vendas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anuncios_full as $anuncio): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($anuncio['thumbnail'])): ?>
                                                <img src="<?php echo htmlspecialchars($anuncio['thumbnail']); ?>" alt="Produto" style="max-width: 50px; max-height: 50px;">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($anuncio['id']); ?></div>
                                            <div><?php echo htmlspecialchars($anuncio['sku']); ?></div>
                                        </td>
                                        <td><?php echo $anuncio['vendas_60_dias']; ?> / <?php echo $anuncio['vendas_30_dias']; ?></td>
                                        <td>
                                            <?php if ($anuncio['vendas_30_dias'] > 0): ?>
                                                <i class="fas fa-arrow-down text-success"></i>
                                            <?php else: ?>
                                                <i class="fas fa-circle text-danger"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $anuncio['estoque']; ?></td>
                                        <td class="<?php echo $anuncio['dias_cobertura'] === 'INF' || $anuncio['dias_cobertura'] > 60 ? 'bg-danger text-white' : ($anuncio['dias_cobertura'] > 30 ? 'bg-warning' : 'bg-success text-white'); ?>">
                                            <?php echo $anuncio['dias_cobertura'] === 'INF' ? 'INF' : round($anuncio['dias_cobertura']); ?>
                                        </td>
                                        <td><?php echo formatarMoeda($anuncio['preco'] * $anuncio['vendas_30_dias']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="alert alert-info">
            <h5><i class="fas fa-info-circle"></i> Sobre este relatório</h5>
            <p><strong>Dias de Cobertura de Estoque</strong> é calculado dividindo o estoque atual pela média diária de vendas dos últimos 30 dias.</p>
            <ul>
                <li><span class="badge bg-success">Verde:</span> Cobertura saudável (até 30 dias)</li>
                <li><span class="badge bg-warning text-dark">Amarelo:</span> Cobertura intermediária (31 a 60 dias)</li>
                <li><span class="badge bg-danger">Vermelho:</span> Cobertura excessiva (mais de 60 dias ou sem vendas)</li>
            </ul>
            <p>Recomendamos ajustar o estoque dos itens em vermelho para evitar taxas extras de armazenamento prolongado.</p>
        </div>
        
    <?php elseif (empty($access_token)): ?>
        <div class="alert alert-warning">
            <h5><i class="fas fa-exclamation-triangle"></i> Autenticação necessária</h5>
            <p>Para visualizar o relatório de cobertura de estoque, é necessário autenticar sua conta do Mercado Livre.</p>
            <a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php?acao=autorizar" class="btn btn-primary mt-2">
                <i class="fas fa-plug"></i> Conectar com o Mercado Livre
            </a>
        </div>
    <?php endif; ?>
</div>

<?php
// Scripts adicionais para esta página
$additional_scripts = '
    // Inicializar DataTables com botões de exportação
    $(document).ready(function() {
        // Inicializar DataTables com botões
        var table = $("#tabelaCobertura").DataTable({
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json",
            },
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            order: [[5, "asc"]],
            responsive: true,
            dom: "Bfrtip",
            buttons: [
                {
                    extend: "excel",
                    text: "<i class=\"fas fa-file-excel\"></i> Excel",
                    title: "Cobertura de Estoque Full - ' . date('d/m/Y') . '",
                    className: "btn btn-success btn-sm d-none", // Escondido pois usaremos botão personalizado
                    exportOptions: {
                        columns: [1, 2, 4, 5, 6]  // Exportar apenas colunas específicas
                    },
                    customize: function(xlsx) {
                        // Aqui você pode personalizar o Excel gerado se necessário
                        var sheet = xlsx.xl.worksheets["sheet1.xml"];
                        
                        // Ajustar largura das colunas
                        $("row c", sheet).each(function() {
                            // Exemplo: destacar células com valores críticos
                            // Este é apenas um exemplo básico - personalize conforme necessário
                        });
                    }
                },
                {
                    extend: "csv",
                    text: "<i class=\"fas fa-file-csv\"></i> CSV",
                    title: "Cobertura de Estoque Full - ' . date('d/m/Y') . '",
                    className: "btn btn-info btn-sm d-none", // Escondido
                }
            ]
        });
        
        // Botão personalizado para exportação Excel
        $("#btnExportarExcel").on("click", function() {
            // Disparar a exportação pelo botão interno do DataTables
            table.button(".buttons-excel").trigger();
        });
    });
';

// Incluir o rodapé
require_once 'footer.php';
?>
