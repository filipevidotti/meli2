<?php
// produtos_salvos.php

// Inclui funções e protege a página
require_once 'functions/functions.php';
protegerPagina();

$success_message = null;
$error_message = null;

// Verifica ação de exclusão
if (isset($_GET[
'delete_id
'])) {
    $delete_id = intval($_GET[
'delete_id
']);
    // excluirProdutoLocal já verifica se o produto pertence ao usuário logado
    if (excluirProdutoLocal($delete_id)) {
        $success_message = "Produto excluído com sucesso!";
    } else {
        $error_message = "Erro ao excluir produto. Verifique se ele existe e pertence a você.";
    }
}

// Obtém os produtos salvos para o usuário logado
$produtos = obterTodosProdutosLocais();

// Inclui o cabeçalho
include __DIR__ . 
'/header.php
';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1>Produtos Salvos Localmente</h1>
        <p>Lista de produtos cadastrados no sistema.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="card card-dashboard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4>Meus Produtos</h4>
                <a href="calculadora.php" class="btn btn-primary">Adicionar Novo Produto</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>SKU</th>
                                <th class="text-end">Preço de Custo</th>
                                <th class="text-center">Peso (kg)</th>
                                <th class="text-center">Dimensões (cm)</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($produtos)): ?>
                                <tr><td colspan="6" class="text-center">Nenhum produto salvo.</td></tr>
                            <?php else: ?>
                                <?php foreach ($produtos as $produto): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($produto["sku"]); ?></td>
                                        <td class="text-end">R$ <?php echo number_format($produto["preco_custo"] ?? 0, 2, 
',
, 
'.
); ?></td>
                                        <td class="text-center"><?php echo number_format($produto["peso"] ?? 0, 3, ',', '.')
'.
); ?></td>
                                        <td class="text-center">
                                            <?php 
                                            $dimensoes = [];
                                            if (!empty($produto["comprimento"])) $dimensoes[] = number_format($produto["comprimento"], 1, 
',
, 
'.
);
                                            if (!empty($produto["largura"]))     $dimensoes[] = number_format($produto["largura"], 1, 
',
, 
'.
);
                                            if (!empty($produto["altura"]))      $dimensoes[] = number_format($produto["altura"], 1, 
',
, 
'.
);
                                            echo !empty($dimensoes) ? implode(
' x 
', $dimensoes) : '-';
                                            ?>
                                        </td>
                                        <td>
                                            <a href="calculadora.php?produto_id=<?php echo $produto["id"]; ?>" class="btn btn-sm btn-primary" title="Editar/Calcular"><i class="fas fa-edit"></i></a>
                                            <a href="produtos_salvos.php?delete_id=<?php echo $produto[
'id
']; ?>" class="btn btn-sm btn-danger" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este produto?')"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Inclui o rodapé
include 'footer.php';
?>

