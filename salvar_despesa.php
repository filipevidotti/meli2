<?php
// salvar_despesa.php

// Inclui funções e protege a página
require_once 'functions/functions.php';
protegerPagina();

// Verifica se o método é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['message'] = 'Método não permitido.';
    $_SESSION['msg_type'] = 'danger';
    header('Location: configurar_despesas.php');
    exit;
}

// Recebe os dados do formulário
$id = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null;
$tipo_despesa_id = isset($_POST['tipo_despesa_id']) ? (int)$_POST['tipo_despesa_id'] : 0;
$valor = isset($_POST['valor']) ? (float)$_POST['valor'] : 0;
$data_despesa = $_POST['data_despesa'] ?? date('Y-m-d');
$descricao = trim($_POST['descricao'] ?? '');

// Validação básica
if ($tipo_despesa_id <= 0) {
    $_SESSION['message'] = 'Tipo de despesa é obrigatório.';
    $_SESSION['msg_type'] = 'danger';
    header('Location: configurar_despesas.php');
    exit;
}
if ($valor <= 0) {
    $_SESSION['message'] = 'Valor da despesa deve ser maior que zero.';
    $_SESSION['msg_type'] = 'danger';
    header('Location: configurar_despesas.php');
    exit;
}
if (empty($data_despesa)) {
    $_SESSION['message'] = 'Data da despesa é obrigatória.';
    $_SESSION['msg_type'] = 'danger';
    header('Location: configurar_despesas.php');
    exit;
}

// Tenta salvar ou atualizar usando as funções isoladas por usuário
try {
    // Verifica se o tipo de despesa pertence ao usuário logado antes de salvar/atualizar a despesa
    $tipoValido = obterTipoDespesaPorId($tipo_despesa_id);
    if (!$tipoValido) {
        throw new Exception("Tipo de despesa inválido ou não pertence a você.");
    }

    if ($id) {
        // Atualizar despesa existente
        $success = atualizarDespesa($id, $tipo_despesa_id, $valor, $data_despesa, $descricao);
        $message = $success ? 'Despesa atualizada com sucesso!' : 'Erro ao atualizar a despesa.';
    } else {
        // Salvar nova despesa
        $newId = salvarDespesa($tipo_despesa_id, $valor, $data_despesa, $descricao);
        $success = ($newId !== false);
        $message = $success ? 'Despesa registrada com sucesso!' : 'Erro ao registrar a despesa.';
    }

    if ($success) {
        $_SESSION['message'] = $message;
        $_SESSION['msg_type'] = 'success';
    } else {
        $_SESSION['message'] = $message;
        $_SESSION['msg_type'] = 'danger';
    }

} catch (Exception $e) {
    error_log("Erro em salvar_despesa.php: " . $e->getMessage());
    $_SESSION['message'] = 'Erro: ' . $e->getMessage();
    $_SESSION['msg_type'] = 'danger';
}

// Redireciona de volta para a página de configuração
header('Location: configurar_despesas.php');
exit;

?>
