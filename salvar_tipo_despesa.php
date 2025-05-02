<?php
// salvar_tipo_despesa.php

// Inclui funções e protege a página
require_once 'functions/functions.php';
protegerPagina();

// Verifica se o método é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Se não for POST, redireciona ou mostra erro
    $_SESSION['message'] = 'Método não permitido.';
    $_SESSION['msg_type'] = 'danger';
    header('Location: configurar_despesas.php');
    exit;
}

// Recebe os dados do formulário
$id = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null;
$nome = trim($_POST['nome'] ?? '');
$categoria = trim($_POST['categoria'] ?? '');

// Validação básica
if (empty($nome) || empty($categoria)) {
    $_SESSION['message'] = 'Nome e categoria são obrigatórios.';
    $_SESSION['msg_type'] = 'danger';
    header('Location: configurar_despesas.php');
    exit;
}

// Tenta salvar ou atualizar usando as funções isoladas por usuário
try {
    if ($id) {
        // Atualizar tipo existente
        $success = atualizarTipoDespesa($id, $nome, $categoria);
        $message = $success ? 'Tipo de despesa atualizado com sucesso!' : 'Erro ao atualizar o tipo de despesa.';
    } else {
        // Salvar novo tipo
        $newId = salvarTipoDespesa($nome, $categoria);
        $success = ($newId !== false);
        $message = $success ? 'Tipo de despesa salvo com sucesso!' : 'Erro ao salvar o tipo de despesa (verifique se o nome já existe).';
    }

    if ($success) {
        $_SESSION['message'] = $message;
        $_SESSION['msg_type'] = 'success';
    } else {
        // A função já pode ter logado o erro específico
        $_SESSION['message'] = $message;
        $_SESSION['msg_type'] = 'danger';
    }

} catch (Exception $e) {
    // Captura exceções gerais (embora as funções CRUD já tratem PDOExceptions)
    error_log("Erro geral em salvar_tipo_despesa.php: " . $e->getMessage());
    $_SESSION['message'] = 'Ocorreu um erro inesperado ao processar a solicitação.';
    $_SESSION['msg_type'] = 'danger';
}

// Redireciona de volta para a página de configuração
header('Location: configurar_despesas.php');
exit;

?>
