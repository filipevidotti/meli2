<?php
// vendas.php - Exibe o histórico de vendas processadas (SaaS - Fase 1)

// Inclui funções e protege a página
require_once __DIR__ . 
'/functions/functions.php'
;
protegerPagina(); // Garante que apenas usuários logados acessem

$page_title = "Histórico de Vendas Processadas";

// --- Paginação ---
$registros_por_pagina = 25;
$pagina_atual = isset($_GET[
'page'
]) ? (int)$_GET[
'page'
] : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// --- Obter Vendas ---
$dados_vendas = obterVendasProcessadas($registros_por_pagina, $offset);
$vendas = $dados_vendas[
'vendas'
];
$total_registros = $dados_vendas[
'total'
];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Inclui o cabeçalho
include_once __DIR__ . 
'/header.php'
;
?>

<div class="container mt-4">
    <h2><i class="fas fa-history"></i> <?php echo $page_title; ?></h2>

    <p>Aqui você pode ver o histórico das vendas sincronizadas do Mercado Livre que foram processadas e salvas no sistema.</p>

    <?php if (empty($vendas)): ?>
        <div class="alert alert-info" role="alert">
            Nenhuma venda processada encontrada. Sincronize suas vendas na página de <a href="api_ml.php">Integração Mercado Livre</a>.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Data Venda</th>
                        <th>Pedido ML</th>
                        <th>Item ML</th>
                        <th>SKU</th>
                        <th>Produto</th>
                        <th>Qtd</th>
                        <th>Preço Unit.</th>
                        <th>Custo Unit.</th>
                        <th>Taxa ML</th>
                        <th>Custo Envio</th>
                        <th>Lucro Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendas as $venda): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($venda[
'data_venda'
]))); ?></td>
                            <td><?php echo htmlspecialchars($venda[
'ml_order_id'
]); ?></td>
                            <td><?php echo htmlspecialchars($venda[
'ml_item_id'
]); ?></td>
                            <td><?php echo htmlspecialchars($venda[
'sku'
]); ?></td>
                            <td><?php echo htmlspecialchars($venda[
'nome_produto'
] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($venda[
'quantidade'
]); ?></td>
                            <td>R$ <?php echo number_format($venda[
'preco_venda_unitario'
], 2, ',', '.'); ?></td>
                            <td><?php echo $venda[
'custo_unitario'
] !== null ? 'R$ ' . number_format($venda[
'custo_unitario'
], 2, ',', '.') : 'N/A'; ?></td>
                            <td>R$ <?php echo number_format($venda[
'taxa_ml'
], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format($venda[
'custo_envio'
], 2, ',', '.'); ?></td>
                            <td class="fw-bold <?php echo ($venda[
'lucro_total'
] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $venda[
'lucro_total'
] !== null ? 'R$ ' . number_format($venda[
'lucro_total'
], 2, ',', '.') : 'N/A'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginação das vendas">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($pagina_atual <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $pagina_atual - 1; ?>">Anterior</a>
                    </li>
                    <?php 
                    // Lógica para exibir um número limitado de páginas
                    $range = 2; // Quantas páginas mostrar antes e depois da atual
                    $start = max(1, $pagina_atual - $range);
                    $end = min($total_paginas, $pagina_atual + $range);

                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                        if ($start > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }

                    for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?php echo ($i == $pagina_atual) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; 

                    if ($end < $total_paginas) {
                        if ($end < $total_paginas - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?page='.$total_paginas.'">'.$total_paginas.'</a></li>';
                    }
                    ?>
                    <li class="page-item <?php echo ($pagina_atual >= $total_paginas) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $pagina_atual + 1; ?>">Próxima</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php
// Inclui o rodapé
include_once __DIR__ . 
'/footer.php'
;
?>
