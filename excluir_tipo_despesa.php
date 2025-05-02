<?php
// excluir_tipo_despesa.php

// Inclui funções e protege a página
require_once __DIR__ . 
'/functions/functions.php'
;
protegerPagina();

// Verifica se o ID foi passado via GET
if (!isset($_GET[
'id'
])) {
    $_SESSION[
'message'
] = 'ID do tipo de despesa não fornecido.';
    $_SESSION[
'msg_type'
] = 'danger';
    header('Location: configurar_despesas.php');
    exit;
}

$id = intval($_GET[
'id'
]);

if ($id <= 0) {
    $_SESSION[
'message'
] = 'ID do tipo de despesa inválido.';
    $_SESSION[
'msg_type'
] = 'danger';
    header('Location: configurar_despesas.php');
    exit;
}

// Tenta excluir usando a função isolada por usuário
try {
    // A função excluirTipoDespesa já verifica se o ID pertence ao usuário logado.
    // A restrição ON DELETE CASCADE no banco de dados cuidará das despesas associadas.
    $success = excluirTipoDespesa($id);

    if ($success) {
        $_SESSION[
'message'
] = 'Tipo de despesa excluído com sucesso!';
        $_SESSION[
'msg_type'
] = 'success';
    } else {
        // A função pode ter falhado porque o tipo não existe ou não pertence ao usuário
        $_SESSION[
'message'
] = 'Erro ao excluir o tipo de despesa. Verifique se ele existe.';
        $_SESSION[
'msg_type'
] = 'danger';
    }

} catch (Exception $e) {
    // Captura exceções gerais (embora a função CRUD já trate PDOExceptions)
    error_log("Erro geral em excluir_tipo_despesa.php: " . $e->getMessage());
    $_SESSION[
'message'
] = 'Ocorreu um erro inesperado ao processar a exclusão.';
    $_SESSION[
'msg_type'
] = 'danger';
}

// Redireciona de volta para a página de configuração
header('Location: configurar_despesas.php');
exit;

?>
