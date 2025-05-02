<?php
// api_ml.php - Gerencia a conexão e sincronização com o Mercado Livre (SaaS - Fase 1)

// Inclui funções e protege a página
require_once __DIR__ . 
'/functions/functions.php'
;
protegerPagina(); // Garante que apenas usuários logados acessem

$page_title = "Integração Mercado Livre";

// --- Inicialização de Variáveis ---
$config = obterTodasConfiguracoes(); // Carrega config do usuário logado
$ml_api = null;
$is_authenticated = false;
$authUrl = '#'; // URL de autorização padrão
$mensagem = $_SESSION[
'message'
] ?? null; // Pega mensagem da sessão se houver
$alertType = $_SESSION[
'msg_type'
] ?? null;
unset($_SESSION[
'message'
]); // Limpa mensagem da sessão
unset($_SESSION[
'msg_type'
]);

$sincronizando = false;
$sync_results = [
    'total_orders_fetched' => 0,
    'total_items_processed' => 0,
    'items_saved' => 0,
    'items_with_missing_sku' => 0,
    'items_with_missing_local_product' => 0,
    'errors' => []
];
$tokenInfo = null; // Detalhes do token para exibição
$userInfo = null; // Detalhes do usuário ML para exibição

// --- Tratamento de Erros/Sucesso de Redirecionamentos (via GET) ---
if (isset($_GET[
'error'
])) {
    $errorCode = $_GET[
'error'
];
    $errorDetail = isset($_GET[
'detail'
]) ? urldecode($_GET[
'detail'
]) : 'Sem detalhes.';
    switch ($errorCode) {
        case 'config_missing': $mensagem = "Erro crítico: Configuração da API do Mercado Livre não encontrada ou incompleta."; break;
        case 'token_exchange_failed': $mensagem = "Erro ao autenticar: Falha na troca do código por token. Detalhes: " . htmlspecialchars($errorDetail); break;
        case 'callback_exception': $mensagem = "Erro interno durante autenticação. Detalhes: " . htmlspecialchars($errorDetail); break;
        case 'ml_auth_error': $mensagem = "Erro de autorização do Mercado Livre: " . htmlspecialchars($errorDetail); break;
        case 'invalid_callback_access': $mensagem = "Acesso inválido à página de callback."; break;
        case 'api_exception': $mensagem = "Erro ao comunicar com a API do ML. Detalhes: " . htmlspecialchars($errorDetail); break;
        default: $mensagem = "Ocorreu um erro desconhecido durante a autenticação."; break;
    }
    $alertType = 'danger';
    error_log("Erro na página api_ml.php (Usuário: " . obterUsuarioIdLogado() . "): Code={$errorCode}, Detail={$errorDetail}");
} elseif (isset($_GET[
'success'
]) && $_GET[
'success'
] == 'auth_ok') {
    $mensagem = "Autenticação com Mercado Livre realizada com sucesso!";
    $alertType = 'success';
}

// --- Inicialização da API e Verificação de Autenticação ---
try {
    // Verifica se as configurações essenciais existem para o usuário
    if (empty($config[
'ml_client_id'
]) || empty($config[
'ml_client_secret'
]) || empty($config[
'ml_redirect_uri'
])) {
        throw new Exception("Configurações da API do Mercado Livre (Client ID, Secret, Redirect URI) não definidas. <a href='configuracoes.php'>Configure aqui</a>.");
    }
    
    // Instancia a API com as configurações do usuário
    $ml_api = new MercadoLivreAPI($config);
    
    // Verifica se está autenticado (tenta refresh se necessário)
    $is_authenticated = $ml_api->isAuthenticated(true); // true força a verificação/refresh

    if ($is_authenticated) {
        $tokenInfo = carregarTokensML(); // Carrega detalhes do token do DB
        $userInfo = $ml_api->getUserInfo(); // Obtém info do usuário ML
    } else {
        // Se não autenticado, obtém a URL para autorização
        $authUrl = $ml_api->getAuthorizationUrl();
        // Verifica se houve erro na tentativa de autenticação/refresh
        if ($ml_api->getLastError()) {
             $mensagem = "Não conectado ao Mercado Livre. Erro: " . htmlspecialchars($ml_api->getLastError());
             $alertType = 'warning';
        }
    }
} catch (Exception $e) {
    $mensagem = "Erro ao inicializar integração com Mercado Livre: " . $e->getMessage();
    $alertType = 'danger';
    error_log("Erro inicialização API ML (Usuário: " . obterUsuarioIdLogado() . "): " . $e->getMessage());
    $ml_api = null;
    $is_authenticated = false;
}

// --- Tratamento de Ações (Desconectar, Sincronizar) ---
if ($ml_api) { // Só processa ações se a API foi instanciada

    // Handle Disconnect Request
    if (isset($_GET[
'disconnect'
]) && $_GET[
'disconnect'
] == 'true') {
        if ($ml_api->disconnect()) {
            $_SESSION[
'message'
] = "Desconectado do Mercado Livre com sucesso.";
            $_SESSION[
'msg_type'
] = 'success';
        } else {
            $_SESSION[
'message'
] = "Erro ao tentar desconectar do Mercado Livre: " . htmlspecialchars($ml_api->getLastError() ?? 'Erro desconhecido');
            $_SESSION[
'msg_type'
] = 'danger';
        }
        // Redireciona para limpar o parâmetro GET
        header("Location: api_ml.php");
        exit;
    }

    // Handle Sync Request (POST)
    if ($is_authenticated && $_SERVER[
'REQUEST_METHOD'
] === 'POST' && isset($_POST[
'sincronizar'
])) {
        $sincronizando = true;
        $mensagem = "Iniciando sincronização de vendas...";
        $alertType = 'info';
        
        // TODO: Permitir seleção de período na interface?
        $date_to = date('Y-m-d\TH:i:s.v\Z', time());
        $date_from = date('Y-m-d\TH:i:s.v\Z
', strtotime('-30 days')); // Aumentado para 30 dias
        $limit = 50; // Máximo por página
        $offset = 0;
        $max_orders_to_process = 500; // Limite de segurança por sincronização
        $processed_orders_count = 0;

        try {
            $mlUserId = $ml_api->getMlUserId();
            if (!$mlUserId) {
                 throw new Exception("Não foi possível obter o ID do vendedor no Mercado Livre.");
            }

            do {
                // Busca vendas do vendedor logado
                $salesResponse = $ml_api->executeApiRequest("/orders/search", 'GET', [
                    'seller' => $mlUserId,
                    'sort' => 'date_desc',
                    'limit' => $limit,
                    'offset' => $offset,
                    'order.date_created.from' => $date_from,
                    'order.date_created.to' => $date_to
                ]);
                
                if (!$salesResponse || !isset($salesResponse[
'results'
])) {
                    $sync_results[
'errors'
][] = "Falha ao buscar vendas (Offset: {$offset}). Resposta inválida ou vazia.";
                    break; // Para se a busca falhar
                }

                $vendas = $salesResponse[
'results'
];
                $paging = $salesResponse[
'paging'
] ?? ['total' => 0, 'offset' => 0, 'limit' => $limit];
                $sync_results[
'total_orders_fetched'
] += count($vendas);

                if (empty($vendas)) {
                    break; // Sem mais pedidos
                }

                foreach ($vendas as $venda) {
                    $order_id = $venda[
'id'
];
                    $order_date = date('Y-m-d H:i:s', strtotime($venda[
'date_created'
])); // Formato para DB
                    
                    // Busca detalhes do pedido (necessário para itens, taxas, envio)
                    $orderDetails = null;
                    try {
                         $orderDetails = $ml_api->executeApiRequest("/orders/{$order_id}");
                    } catch (Exception $e) {
                         $sync_results[
'errors'
][] = "Erro ao buscar detalhes da venda {$order_id}: " . $e->getMessage();
                         continue; // Pula este pedido
                    }
                    
                    if (!$orderDetails) {
                         $sync_results[
'errors'
][] = "Detalhes da venda {$order_id} não encontrados ou inválidos.";
                         continue; // Pula este pedido
                    }

                    // Custo de envio para o vendedor
                    $shipping_cost_total_order = 0;
                    if (isset($orderDetails[
'shipping'
][
'id'
])) {
                        try {
                            $shipmentDetails = $ml_api->executeApiRequest("/shipments/{$orderDetails['shipping']['id']}");
                            // O custo para o vendedor geralmente está em cost_components
                            if (isset($shipmentDetails[
'cost_components'
][
'seller_payment'
])) {
                                $shipping_cost_total_order = abs($shipmentDetails[
'cost_components'
][
'seller_payment'
]); // É negativo na API
                            }
                        } catch (Exception $e) {
                             $sync_results[
'errors'
][] = "Erro ao buscar detalhes do envio para venda {$order_id}: " . $e->getMessage();
                             // Continua sem custo de envio?
                        }
                    }

                    // Processa cada item no pedido
                    if (isset($orderDetails[
'order_items'
]) && is_array($orderDetails[
'order_items'
])) {
                        $total_items_in_order = count($orderDetails[
'order_items'
]);
                        $item_index = 0;

                        foreach ($orderDetails[
'order_items'
] as $item) {
                            $sync_results[
'total_items_processed'
]++;
                            $ml_item_id = $item[
'item'
][
'id'
];
                            $variation_id = $item[
'item'
][
'variation_id'
] ?? null;
                            $quantity = $item[
'quantity'
];
                            $unit_price = $item[
'unit_price'
];
                            $sale_fee = $item[
'sale_fee'
] ?? 0; // Comissão ML para este item
                            $sku = null;
                            $produto_local = null;
                            $custo_unitario = null;
                            $lucro_total_item = null;

                            // 1. Obter SKU do Anúncio/Variação ML
                            try {
                                // Busca detalhes do anúncio (cache pode ser útil aqui)
                                $itemDetails = $ml_api->executeApiRequest("/items/{$ml_item_id}", 'GET', ['attributes' => 'seller_custom_field,variations']);
                                
                                if ($itemDetails) {
                                    // Verifica SKU da variação primeiro
                                    if ($variation_id && !empty($itemDetails[
'variations'
])) {
                                        foreach ($itemDetails[
'variations'
] as $variation) {
                                            if ($variation[
'id'
] == $variation_id) {
                                                $sku = $variation[
'seller_custom_field'
] ?? null;
                                                break;
                                            }
                                        }
                                    }
                                    // Se não achou na variação, pega do anúncio principal
                                    if (!$sku && isset($itemDetails[
'seller_custom_field'
])) {
                                        $sku = $itemDetails[
'seller_custom_field'
];
                                    }
                                }
                            } catch (Exception $e) {
                                $sync_results[
'errors'
][] = "Erro ao buscar SKU do item {$ml_item_id} (Venda {$order_id}): " . $e->getMessage();
                            }

                            if (!$sku) {
                                $sync_results[
'items_with_missing_sku'
]++;
                                $sync_results[
'errors'
][] = "SKU não encontrado para item {$ml_item_id} (Venda {$order_id}). Verifique 'seller_custom_field' no anúncio/variação.";
                            } else {
                                // 2. Obter Custo do Produto Local pelo SKU (função já isolada por usuário)
                                $produto_local = obterProdutoPorSku($sku);
                                if (!$produto_local || !isset($produto_local[
'preco_custo'
])) {
                                    $sync_results[
'items_with_missing_local_product'
]++;
                                    $sync_results[
'errors'
][] = "Produto local SKU '{$sku}' (Item {$ml_item_id}, Venda {$order_id}) não encontrado ou sem custo.";
                                } else {
                                    $custo_unitario = $produto_local[
'preco_custo'
];
                                }
                            }

                            // 3. Calcular Lucro (se tiver custo)
                            if ($custo_unitario !== null) {
                                // Alocação do custo de envio: Atribui ao primeiro item processado do pedido
                                $item_shipping_cost = ($item_index === 0) ? $shipping_cost_total_order : 0;
                                
                                $lucro_total_item = ($unit_price * $quantity) - ($custo_unitario * $quantity) - $sale_fee - $item_shipping_cost;
                            } else {
                                $item_shipping_cost = 0; // Não pode calcular lucro sem custo
                            }

                            // 4. Salvar na tabela vendas_processadas (função já isolada por usuário)
                            $vendaProcessadaData = [
                                'ml_order_id' => $order_id,
                                'ml_item_id' => $ml_item_id,
                                'sku' => $sku ?? 'N/A',
                                'produto_id' => $produto_local[
'id'
] ?? null,
                                'quantidade' => $quantity,
                                'preco_venda_unitario' => $unit_price,
                                'custo_unitario' => $custo_unitario,
                                'taxa_ml' => $sale_fee,
                                'custo_envio' => $item_shipping_cost, // Custo alocado a este item
                                'lucro_total' => $lucro_total_item,
                                'data_venda' => $order_date
                            ];

                            if (salvarVendaProcessada($vendaProcessadaData)) {
                                $sync_results[
'items_saved'
]++;
                            } else {
                                $sync_results[
'errors'
][] = "Erro ao salvar venda processada para Ordem {$order_id} / Item {$ml_item_id}.";
                            }
                            $item_index++;
                        } // fim foreach order_items
                    } // fim if order_items
                    
                    $processed_orders_count++;
                    if ($processed_orders_count >= $max_orders_to_process) {
                         $sync_results[
'errors'
][] = "Limite máximo de {$max_orders_to_process} pedidos processados atingido.";
                         break; // Para loop de pedidos
                    }

                } // fim foreach vendas

                // Prepara para próxima página
                $offset += $limit;

            } while ($offset < $paging[
'total'
] && $processed_orders_count < $max_orders_to_process);

            // Mensagem final da sincronização
            $mensagem = "Sincronização concluída. {$sync_results['total_orders_fetched']} pedidos verificados, {$sync_results['total_items_processed']} itens processados, {$sync_results['items_saved']} itens salvos/atualizados.";
            if ($sync_results[
'items_with_missing_sku'
] > 0) {
                 $mensagem .= " <br>Atenção: {$sync_results['items_with_missing_sku']} itens não tinham SKU configurado no ML.";
            }
            if ($sync_results[
'items_with_missing_local_product'
] > 0) {
                 $mensagem .= " <br>Atenção: {$sync_results['items_with_missing_local_product']} itens com SKU não foram encontrados localmente ou não têm custo.";
            }
             if (!empty($sync_results[
'errors'
])) {
                 $mensagem .= " <br>Ocorreram erros durante a sincronização (ver logs para detalhes).";
                 $alertType = 'warning'; // Muda para warning se houve erros
             } else {
                 $alertType = 'success'; // Sucesso se não houve erros
             }
             // Log detalhado dos erros se houver
             if (!empty($sync_results[
'errors'
])) {
                 error_log("Erros Sincronização ML (Usuário: " . obterUsuarioIdLogado() . "): " . print_r($sync_results[
'errors'
], true));
             }

        } catch (Exception $e) {
            $mensagem = "Erro fatal durante a sincronização: " . $e->getMessage();
            $alertType = 'danger';
            error_log("Erro Fatal Sincronização ML (Usuário: " . obterUsuarioIdLogado() . "): " . $e->getMessage());
        }
        $sincronizando = false;
    } // fim if sincronizar

} // fim if $ml_api

// --- Preparação para Exibição ---
$status_conexao = $is_authenticated ? "Conectado" : "Não conectado";
$status_classe = $is_authenticated ? "success" : "danger";
$token_expira_em = 'N/A';
if ($is_authenticated && $tokenInfo && isset($tokenInfo[
'expires_at'
])) {
    $agora = time();
    $expira = $tokenInfo[
'expires_at'
];
    if ($expira > $agora) {
        $diff = $expira - $agora;
        $dias = floor($diff / (60*60*24));
        $horas = floor(($diff % (60*60*24)) / (60*60));
        $minutos = floor(($diff % (60*60)) / 60);
        $token_expira_em = "";
        if ($dias > 0) $token_expira_em .= "{$dias}d ";
        if ($horas > 0) $token_expira_em .= "{$horas}h ";
        if ($minutos > 0) $token_expira_em .= "{$minutos}m";
        $token_expira_em = trim($token_expira_em);
        if (empty($token_expira_em)) $token_expira_em = "Menos de 1 minuto";
    } else {
        $token_expira_em = "Expirado";
        $status_classe = 'warning'; // Avisa que expirou
    }
}

// Inclui o cabeçalho
include_once __DIR__ . 
'/header.php'
; 
?>

<div class="container mt-4">
    <h2><i class="fab fa-connectdevelop"></i> <?php echo $page_title; ?></h2>

    <?php if ($mensagem): ?>
    <div class="alert alert-<?php echo $alertType ?: 'info'; ?> alert-dismissible fade show" role="alert">
        <?php echo $mensagem; // Mensagem já deve estar segura para HTML se veio de $ml_api->getLastError() ou foi construída aqui ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            Status da Conexão
        </div>
        <div class="card-body">
            <p>Status: <span class="badge bg-<?php echo $status_classe; ?>"><?php echo $status_conexao; ?></span></p>
            <?php if ($is_authenticated): ?>
                <?php if ($userInfo): ?>
                <p>Conectado como: <strong><?php echo htmlspecialchars($userInfo[
'nickname'
] ?? 'N/A'); ?></strong> (ID: <?php echo htmlspecialchars($userInfo[
'id'
] ?? 'N/A'); ?>)</p>
                <?php endif; ?>
                <p>Token expira em: <?php echo $token_expira_em; ?></p>
                <a href="api_ml.php?disconnect=true" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja desconectar sua conta do Mercado Livre?');">Desconectar</a>
                <!-- Botão de refresh manual removido, pois o refresh é automático -->
            <?php else: ?>
                <p>Para sincronizar vendas e usar outras funcionalidades, conecte sua conta do Mercado Livre.</p>
                <?php if ($authUrl !== '#'): ?>
                    <a href="<?php echo $authUrl; ?>" class="btn btn-primary"><i class="fas fa-link"></i> Conectar ao Mercado Livre</a>
                <?php else: ?>
                     <p class="text-danger">Não foi possível gerar o link de conexão. Verifique as configurações da API e os logs de erro.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_authenticated): ?>
    <div class="card mt-4">
        <div class="card-header">
            Sincronização de Vendas
        </div>
        <div class="card-body">
            <p>Clique no botão abaixo para buscar as vendas recentes (últimos 30 dias) do Mercado Livre e salvá-las no sistema para cálculo de lucro.</p>
            <form action="api_ml.php" method="post">
                <button type="submit" name="sincronizar" class="btn btn-success" <?php echo $sincronizando ? 'disabled' : ''; ?>>
                    <?php if ($sincronizando): ?>
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        Sincronizando...
                    <?php else: ?>
                        <i class="fas fa-sync-alt"></i> Sincronizar Vendas Agora
                    <?php endif; ?>
                </button>
            </form>
            <?php if (!empty($sync_results[
'errors'
])): ?>
                <div class="mt-3 alert alert-warning">
                    <strong>Erros durante a sincronização:</strong>
                    <ul>
                        <?php foreach (array_slice($sync_results[
'errors'
], 0, 10) as $error): // Mostra os 10 primeiros erros ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                        <?php if (count($sync_results[
'errors'
]) > 10): ?>
                            <li>... e mais <?php echo count($sync_results[
'errors'
]) - 10; ?> erros (ver logs).</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
// Inclui o rodapé
include_once __DIR__ . 
'/footer.php'
; 
?>
