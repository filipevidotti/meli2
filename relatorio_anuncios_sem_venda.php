<?php
// Título da página
$page_title = "Relatório de Anúncios sem Venda - CalcMeli";

// Incluir bibliotecas extras
$include_datatables = true;

// CSS adicional específico para esta página
$additional_css = '
/* Estilos para cabeçalhos ordenáveis */
.sortable {
    cursor: pointer;
    position: relative;
}

.sortable i.fas {
    margin-left: 5px;
    opacity: 0.5;
    font-size: 0.8em;
}

.sorting-asc i.fa-sort-up,
.sorting-desc i.fa-sort-down {
    opacity: 1;
}

/* Melhorar a aparência dos controles do DataTables */
.dataTables_length select, 
.dataTables_filter input {
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    padding: 0.375rem 0.75rem;
    margin: 0 5px;
}

.dataTables_paginate .paginate_button {
    margin: 0 3px;
    padding: 5px 10px;
    border-radius: 3px;
}

.dataTables_paginate .paginate_button.current {
    background: #007bff !important;
    color: white !important;
    border: none;
}

.dataTables_paginate .paginate_button:hover {
    background: #e9ecef !important;
    border-color: #dee2e6 !important;
    color: #212529 !important;
}
';

// Incluir arquivos de configuração e cabeçalho
require_once 'config.php';
require_once 'header.php';
require_once 'sidebar.php';

// Função para corrigir URLs HTTP para HTTPS
function corrigirURL($url) {
    if (empty($url)) return '';
    // Substituir http:// por https:// para evitar erros de conteúdo misto
    return str_replace('http://', 'https://', $url);
}

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
$anuncios_sem_venda = [];
$mensagem = '';
$tipo_mensagem = '';
$total_anuncios = 0;

// Período para filtrar anúncios sem vendas (em dias)
$periodo_filtro = 120; // Padrão: últimos 120 dias
if (isset($_GET['periodo']) && is_numeric($_GET['periodo'])) {
    $periodo_filtro = (int)$_GET['periodo'];
}

// Função para buscar os anúncios ativos do usuário
function buscarAnunciosAtivos($access_token, $user_id) {
    $anuncios = [];
    $offset = 0;
    $limit = 50;
    $total = 0;
    
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
                    
                    if ($items_status == 200) {
                        $items_data = json_decode($items_response, true);
                        
                        foreach ($items_data as $item) {
                            // Verificar se o item tem body e está ativo
                            if (isset($item['body']) && 
                                isset($item['body']['status']) && 
                                $item['body']['status'] === 'active') {
                                
                                $anuncio = $item['body'];
                                
                                // Buscar informações adicionais do anúncio (incluindo visitas)
                                $anuncio_url = "https://api.mercadolibre.com/items/{$anuncio['id']}/visits/time_window?last=30d";
                                
                                $ch_anuncio = curl_init($anuncio_url);
                                curl_setopt($ch_anuncio, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch_anuncio, CURLOPT_HTTPHEADER, [
                                    'Authorization: Bearer ' . $access_token
                                ]);
                                
                                $anuncio_response = curl_exec($ch_anuncio);
                                $anuncio_status = curl_getinfo($ch_anuncio, CURLINFO_HTTP_CODE);
                                curl_close($ch_anuncio);
                                
                                $visitas = 0;
                                if ($anuncio_status == 200) {
                                    $anuncio_data = json_decode($anuncio_response, true);
                                    $visitas = isset($anuncio_data['total_visits']) ? $anuncio_data['total_visits'] : 0;
                                }
                                
                                $anuncios[] = [
                                    'id' => $anuncio['id'],
                                    'titulo' => $anuncio['title'],
                                    'thumbnail' => $anuncio['thumbnail'],
                                    'permalink' => $anuncio['permalink'],
                                    'preco' => $anuncio['price'],
                                    'estoque' => $anuncio['available_quantity'],
                                    'data_criacao' => $anuncio['date_created'],
                                    'visitas' => $visitas,
                                    'ultima_venda' => null, // Será preenchido depois
                                    'dias_sem_venda' => null // Será preenchido depois
                                ];
                            }
                        }
                    }
                }
            }
            
            $total = $data['paging']['total'] ?? 0;
            $offset += $limit;
            
        } while ($offset < $total);
        
        return $anuncios;
        
    } catch (Exception $e) {
        error_log("Exceção ao buscar anúncios: " . $e->getMessage());
        return ['error' => 'Erro ao processar anúncios: ' . $e->getMessage()];
    }
}

// Função para verificar a última venda de cada anúncio
function verificarUltimasVendas($access_token, &$anuncios, $user_id, $periodo_dias) {
    try {
        $hoje = new DateTime();
        $data_limite = (new DateTime())->sub(new DateInterval("P{$periodo_dias}D"));
        $data_limite_str = $data_limite->format('Y-m-d\TH:i:s.000-03:00');
        
        // Para cada anúncio, verificar se teve vendas
        foreach ($anuncios as &$anuncio) {
            $offset = 0;
            $limit = 1; // Precisamos apenas da última venda
            $teve_vendas = false;
            
            $url = "https://api.mercadolibre.com/orders/search?" . 
                   "seller={$user_id}" . 
                   "&item={$anuncio['id']}" . 
                   "&order.date_created.from={$data_limite_str}" . 
                   "&sort=date_desc" . // Ordenar por mais recente
                   "&limit={$limit}";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token
            ]);
            
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($status == 200) {
                $data = json_decode($response, true);
                
                if (isset($data['results']) && count($data['results']) > 0) {
                    $ultima_venda = $data['results'][0];
                    
                    // Verificar se a venda não foi cancelada
                    if ($ultima_venda['status'] !== 'cancelled') {
                        $teve_vendas = true;
                        $data_ultima_venda = new DateTime($ultima_venda['date_created']);
                        $dias_desde_ultima_venda = $hoje->diff($data_ultima_venda)->days;
                        
                        $anuncio['ultima_venda'] = $ultima_venda['date_created'];
                        $anuncio['dias_sem_venda'] = $dias_desde_ultima_venda;
                    }
                }
            }
            
            // Se não teve vendas no período, calcular dias desde a criação do anúncio
            if (!$teve_vendas) {
                $data_criacao = new DateTime($anuncio['data_criacao']);
                $dias_desde_criacao = $hoje->diff($data_criacao)->days;
                
                $anuncio['ultima_venda'] = null;
                $anuncio['dias_sem_venda'] = $dias_desde_criacao;
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Erro ao verificar vendas: " . $e->getMessage());
        return false;
    }
}

// Função para filtrar anúncios sem vendas
function filtrarAnunciosSemVendas($anuncios) {
    return array_filter($anuncios, function($anuncio) {
        return $anuncio['ultima_venda'] === null;
    });
}

// Função para calcular potencial de vendas (preço * estoque)
function calcularPotencialVendas($preco, $estoque) {
    return $preco * $estoque;
}

// Executar a busca se o usuário estiver autenticado
if (!empty($access_token) && !empty($ml_user_id)) {
    $loading = true;
    
    // Buscar todos os anúncios ativos
    $anuncios = buscarAnunciosAtivos($access_token, $ml_user_id);
    
    if (isset($anuncios['error'])) {
        $mensagem = $anuncios['error'];
        $tipo_mensagem = 'danger';
        $loading = false;
    } else {
        // Verificar quais anúncios não tiveram vendas no período
        verificarUltimasVendas($access_token, $anuncios, $ml_user_id, $periodo_filtro);
        
        // Filtrar apenas anúncios sem vendas
        $anuncios_sem_venda = filtrarAnunciosSemVendas($anuncios);
        
        // Ordenar por dias sem venda (decrescente)
        usort($anuncios_sem_venda, function($a, $b) {
            return $b['dias_sem_venda'] - $a['dias_sem_venda'];
        });
        
        $total_anuncios = count($anuncios_sem_venda);
        $loading = false;
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Anúncios sem Venda Ativos - Demonstração</h1>
        <div class="text-muted small">Apresentando anúncios com maior estoque no Top</div>
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
                <!-- Filtros e botões -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text">Há quanto tempo sem venda?</span>
                            <select id="periodoFiltro" class="form-select" onchange="window.location.href='?periodo='+this.value">
                                <option value="30" <?php echo $periodo_filtro == 30 ? 'selected' : ''; ?>>Últimos 30 dias</option>
                                <option value="60" <?php echo $periodo_filtro == 60 ? 'selected' : ''; ?>>Últimos 60 dias</option>
                                <option value="90" <?php echo $periodo_filtro == 90 ? 'selected' : ''; ?>>Últimos 90 dias</option>
                                <option value="120" <?php echo $periodo_filtro == 120 ? 'selected' : ''; ?>>Últimos 120 dias</option>
                                <option value="180" <?php echo $periodo_filtro == 180 ? 'selected' : ''; ?>>Últimos 180 dias</option>
                                <option value="365" <?php echo $periodo_filtro == 365 ? 'selected' : ''; ?>>Último ano</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="exportar_anuncios_sem_venda.php?periodo=<?php echo $periodo_filtro; ?>" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Exportar para Excel
                        </a>
                    </div>
                </div>
                
                <?php if ($loading): ?>
                    <div class="text-center my-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2">Carregando dados de anúncios e verificando vendas...</p>
                    </div>
                <?php elseif (empty($anuncios_sem_venda)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Todos os seus anúncios ativos têm vendas no período selecionado.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="tabelaAnuncios">
                            <thead class="table-primary">
                                <tr>
                                    <th class="sortable">Anúncio <i class="fas fa-sort"></i></th>
                                    <th class="sortable">Preço / Promoção <i class="fas fa-sort"></i></th>
                                    <th class="sortable">Estoque Disponível <i class="fas fa-sort"></i></th>
                                    <th class="sortable">Dias sem Venda <i class="fas fa-sort"></i></th>
                                    <th class="sortable">Potencial de Vendas <i class="fas fa-sort"></i></th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anuncios_sem_venda as $anuncio): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($anuncio['thumbnail'])): ?>
                                                    <img src="<?php echo htmlspecialchars(corrigirURL($anuncio['thumbnail'])); ?>" alt="Produto" style="max-width: 50px; max-height: 50px; margin-right: 10px;">
                                                <?php endif; ?>
                                                <div>
                                                    <div><strong><?php echo htmlspecialchars($anuncio['id']); ?></strong></div>
                                                    <a href="<?php echo htmlspecialchars(corrigirURL($anuncio['permalink'])); ?>" target="_blank" class="small text-primary">
                                                        editar anúncio
                                                    </a>
                                                    <div class="small text-success">active</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>Preço do Anúncio:</div>
                                            <div><strong>R$<?php echo number_format($anuncio['preco'], 2, ',', '.'); ?></strong></div>
                                        </td>
                                        <td><?php echo $anuncio['estoque']; ?></td>
                                        <td><?php echo $anuncio['dias_sem_venda']; ?></td>
                                        <td>R$<?php echo number_format(calcularPotencialVendas($anuncio['preco'], $anuncio['estoque']), 2, ',', '.'); ?></td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-outline-primary" onclick="checarPromocoes('<?php echo $anuncio['id']; ?>'); return false;">
                                                <i class="fas fa-tag"></i> Checar promoções
                                            </a>
                                        </td>
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
            <p>Este relatório mostra todos os anúncios ativos que não tiveram vendas no período selecionado. Recomendamos revisitar a estratégia para estes anúncios:</p>
            <ul>
                <li>Verificar se o preço está competitivo</li>
                <li>Melhorar fotos e descrição</li>
                <li>Considerar criar promoções</li>
                <li>Analisar se vale a pena continuar com este anúncio ou substituí-lo por outro produto</li>
            </ul>
        </div>
        
    <?php elseif (empty($access_token)): ?>
        <div class="alert alert-warning">
            <h5><i class="fas fa-exclamation-triangle"></i> Autenticação necessária</h5>
            <p>Para visualizar o relatório de anúncios sem venda, é necessário autenticar sua conta do Mercado Livre.</p>
            <a href="<?php echo $base_url; ?>/vendedor_mercadolivre.php?acao=autorizar" class="btn btn-primary mt-2">
                <i class="fas fa-plug"></i> Conectar com o Mercado Livre
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Modal para checar promoções -->
<div class="modal fade" id="promocoesModal" tabindex="-1" aria-labelledby="promocoesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="promocoesModalLabel">Promoções Disponíveis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="promocoesContent">
                    <p>Carregando promoções disponíveis...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php
// Scripts adicionais para esta página
$additional_scripts = '
    // Inicializar DataTables com ordenação
    $(document).ready(function() {
        // Verificar se a tabela existe
        if ($("#tabelaAnuncios").length === 0) {
            console.log("Tabela #tabelaAnuncios não encontrada");
            return; // Sai da função se a tabela não existir
        }

        try {
            var table = $("#tabelaAnuncios").DataTable({
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json",
                    search: "Pesquisar:",
                    lengthMenu: "Exibir _MENU_ resultados por página",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                    infoEmpty: "Mostrando 0 a 0 de 0 registros",
                    infoFiltered: "(filtrado de _MAX_ registros no total)",
                    zeroRecords: "Nenhum registro encontrado"
                },
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                order: [[3, "desc"]], // Ordenar por dias sem venda (decrescente)
                columnDefs: [
                    { orderable: false, targets: 5 } // Desabilita ordenação na coluna de Ações
                ],
                responsive: true
            });
            
            // Adicionar evento somente se a tabela for inicializada com sucesso
            if (table) {
                $("#tabelaAnuncios").on("order.dt", function() {
                    try {
                        // Atualizar ícones de ordenação
                        $("#tabelaAnuncios thead th i.fas").each(function() {
                            $(this).removeClass("fa-sort-up fa-sort-down").addClass("fa-sort");
                        });
                        
                        // Obter informação de ordenação atual
                        var orderInfo = table.order()[0];
                        if (orderInfo) {
                            var columnIdx = orderInfo[0];
                            var direction = orderInfo[1];
                            
                            // Atualizar ícone da coluna ordenada
                            var icon = $($("#tabelaAnuncios thead th i.fas").get(columnIdx));
                            if (icon.length) {
                                icon.removeClass("fa-sort");
                                icon.addClass(direction === "asc" ? "fa-sort-up" : "fa-sort-down");
                            }
                        }
                    } catch (e) {
                        console.error("Erro ao atualizar ícones de ordenação:", e);
                    }
                });
                
                // Aplicar ordenação inicial
                table.order([3, "desc"]).draw();
            }
        } catch (e) {
            console.error("Erro ao inicializar DataTables:", e);
        }
    });
    
    // Função para checar promoções disponíveis
	
	 // Função para checar promoções disponíveis
    function checarPromocoes(itemId) {
        // Mostrar o modal usando Bootstrap 5
        var modalElement = document.getElementById("promocoesModal");
        if (modalElement) {
            var modal = new bootstrap.Modal(modalElement);
            modal.show();
            
            // Atualizar conteúdo do modal
            document.getElementById("promocoesContent").innerHTML = "<div class=\"text-center\"><div class=\"spinner-border text-primary\" role=\"status\"></div><p class=\"mt-2\">Carregando promoções...</p></div>";
            
            // Aqui você poderia fazer uma chamada AJAX para obter promoções disponíveis
            // Por enquanto, apenas simulamos uma resposta após um pequeno delay
            setTimeout(function() {
                var html = "<div class=\"alert alert-info\">Este é um recurso demonstrativo. Em uma implementação real, seriam exibidas as promoções disponíveis para o anúncio.</div>";
                html += "<ul class=\"list-group\">";
                html += "<li class=\"list-group-item d-flex justify-content-between align-items-center\"><span><strong>Desconto sazonal</strong><br>Pode aplicar até 15% de desconto</span> <a href=\"#\" class=\"btn btn-sm btn-outline-success\">Aplicar</a></li>";
                html += "<li class=\"list-group-item d-flex justify-content-between align-items-center\"><span><strong>Frete grátis</strong><br>Disponível para este produto</span> <a href=\"#\" class=\"btn btn-sm btn-outline-success\">Aplicar</a></li>";
                html += "<li class=\"list-group-item d-flex justify-content-between align-items-center\"><span><strong>Desconto por quantidade</strong><br>Configurar a partir de 2 unidades</span> <a href=\"#\" class=\"btn btn-sm btn-outline-success\">Aplicar</a></li>";
                html += "</ul>";
                
                var contentElement = document.getElementById("promocoesContent");
                if (contentElement) {
                    contentElement.innerHTML = html;
                }
            }, 1000);
        }
    }
';

// Incluir o rodapé
require_once 'footer.php';
?>