<?php
// configurar_despesas.php

// Inclui funções e protege a página
require_once 'functions/functions.php';
protegerPagina();

$page_title = "Configurar Despesas";

// Carregar dados necessários usando as funções isoladas por usuário
$categorias = obterCategoriasDespesa();
$tipos_despesa = obterTiposDespesa();
$despesas = obterDespesas(); // Por padrão, busca todas do usuário logado

// Inclui o cabeçalho
include_once 'header.php'; 
?>

<div class="container mt-4">
    <h2><i class="fas fa-cogs"></i> <?php echo $page_title; ?></h2>

    <?php if (isset($_SESSION[
'message
'])): ?>
    <div class="alert alert-<?php echo $_SESSION[
'msg_type
']; ?> alert-dismissible fade show" role="alert">
        <?php 
            echo $_SESSION[
'message
']; 
            unset($_SESSION[
'message
']);
            unset($_SESSION[
'msg_type
']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Formulário para Adicionar/Editar Tipo de Despesa -->
    <div class="card mb-4">
        <div class="card-header">
            Gerenciar Tipos de Despesa
        </div>
        <div class="card-body">
            <!-- O action aponta para salvar_tipo_despesa.php que precisará ser adaptado -->
            <form action="salvar_tipo_despesa.php" method="POST">
                <input type="hidden" name="id" id="tipo_despesa_id">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="tipo_nome" class="form-label">Nome do Tipo</label>
                        <input type="text" class="form-control" id="tipo_nome" name="nome" required>
                    </div>
                    <div class="col-md-4">
                        <label for="tipo_categoria" class="form-label">Categoria</label>
                        <input type="text" class="form-control" id="tipo_categoria" name="categoria" list="categorias_list" required>
                        <datalist id="categorias_list">
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-md-4 align-self-end">
                        <button type="submit" class="btn btn-primary" name="salvar_tipo"><i class="fas fa-save"></i> Salvar Tipo</button>
                        <button type="button" class="btn btn-secondary" onclick="limparFormTipoDespesa()"><i class="fas fa-eraser"></i> Limpar</button>
                    </div>
                </div>
            </form>
            <hr>
            <h5>Tipos de Despesa Cadastrados</h5>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tipos_despesa)): ?>
                            <tr><td colspan="3" class="text-center">Nenhum tipo de despesa cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tipos_despesa as $tipo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tipo[
'nome
']); ?></td>
                                <td><?php echo htmlspecialchars($tipo[
'categoria
']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editarTipoDespesa(<?php echo $tipo[
'id
']; ?>, '<?php echo htmlspecialchars(addslashes($tipo[
'nome
'])); ?>', '<?php echo htmlspecialchars(addslashes($tipo[
'categoria
'])); ?>')" title="Editar"><i class="fas fa-edit"></i></button>
                                    <!-- O link aponta para excluir_tipo_despesa.php que precisará ser adaptado -->
                                    <a href="excluir_tipo_despesa.php?id=<?php echo $tipo[
'id
']; ?>" class="btn btn-sm btn-danger" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este tipo de despesa? Todas as despesas associadas também serão excluídas.');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Formulário para Adicionar/Editar Despesa -->
    <div class="card mb-4">
        <div class="card-header">
            Registrar Nova Despesa
        </div>
        <div class="card-body">
             <!-- O action aponta para salvar_despesa.php que precisará ser adaptado -->
            <form action="salvar_despesa.php" method="POST">
                <input type="hidden" name="id" id="despesa_id">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="despesa_tipo" class="form-label">Tipo de Despesa</label>
                        <select class="form-select" id="despesa_tipo" name="tipo_despesa_id" required>
                            <option value="">Selecione...</option>
                            <?php 
                            $current_category = null;
                            foreach ($tipos_despesa as $tipo): 
                                if ($tipo[
'categoria
'] !== $current_category) {
                                    if ($current_category !== null) echo '</optgroup>';
                                    $current_category = $tipo[
'categoria
'];
                                    echo '<optgroup label="'.htmlspecialchars($current_category).'">';
                                }
                            ?>
                                <option value="<?php echo $tipo[
'id
']; ?>"><?php echo htmlspecialchars($tipo[
'nome
']); ?></option>
                            <?php endforeach; 
                            if ($current_category !== null) echo '</optgroup>';
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="despesa_valor" class="form-label">Valor (R$)</label>
                        <input type="number" step="0.01" class="form-control" id="despesa_valor" name="valor" required>
                    </div>
                    <div class="col-md-3">
                        <label for="despesa_data" class="form-label">Data</label>
                        <input type="date" class="form-control" id="despesa_data" name="data_despesa" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                     <div class="col-md-2 align-self-end">
                        <button type="submit" class="btn btn-success" name="salvar_despesa"><i class="fas fa-plus"></i> Registrar</button>
                    </div>
                    <div class="col-md-12">
                        <label for="despesa_descricao" class="form-label">Descrição (Opcional)</label>
                        <textarea class="form-control" id="despesa_descricao" name="descricao" rows="2"></textarea>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de Despesas Registradas -->
    <div class="card">
        <div class="card-header">
            Histórico de Despesas
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Categoria</th>
                            <th class="text-end">Valor (R$)</th>
                            <th>Descrição</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                         <?php if (empty($despesas)): ?>
                            <tr><td colspan="6" class="text-center">Nenhuma despesa registrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($despesas as $despesa): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($despesa[
'data_despesa
'])); ?></td>
                                <td><?php echo htmlspecialchars($despesa[
'tipo_nome
']); ?></td>
                                <td><?php echo htmlspecialchars($despesa[
'tipo_categoria
']); ?></td>
                                <td class="text-end"><?php echo number_format($despesa[
'valor
'], 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($despesa[
'descricao
'] ?? ''); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" title="Editar" onclick="editarDespesa(<?php echo $despesa[
'id
']; ?>, <?php echo $despesa[
'tipo_despesa_id
']; ?>, <?php echo $despesa[
'valor
']; ?>, '<?php echo $despesa[
'data_despesa
']; ?>', '<?php echo htmlspecialchars(addslashes($despesa[
'descricao
'] ?? '')); ?>')"><i class="fas fa-edit"></i></button>
                                     <!-- O link aponta para excluir_despesa.php que precisará ser adaptado -->
                                    <a href="excluir_despesa.php?id=<?php echo $despesa[
'id
']; ?>" class="btn btn-sm btn-danger" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir esta despesa?');"><i class="fas fa-trash"></i></a>
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

<script>
function editarTipoDespesa(id, nome, categoria) {
    document.getElementById('tipo_despesa_id').value = id;
    document.getElementById('tipo_nome').value = nome;
    document.getElementById('tipo_categoria').value = categoria;
    // Scroll to the type form
    var card = document.querySelector('form[action="salvar_tipo_despesa.php"]').closest('.card');
    if (card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function limparFormTipoDespesa() {
    document.getElementById('tipo_despesa_id').value = '';
    document.getElementById('tipo_nome').value = '';
    document.getElementById('tipo_categoria').value = '';
}

function editarDespesa(id, tipo_id, valor, data, descricao) {
    document.getElementById('despesa_id').value = id;
    document.getElementById('despesa_tipo').value = tipo_id;
    document.getElementById('despesa_valor').value = valor;
    document.getElementById('despesa_data').value = data;
    document.getElementById('despesa_descricao').value = descricao;
    // Scroll to the expense form
    var card = document.querySelector('form[action="salvar_despesa.php"]').closest('.card');
    if (card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
</script>

<?php
include_once 'footer.php'; // Include footer
?>

