<?php
/**
 * Funções de autenticação e proteção de páginas
 */

/**
 * Protege uma página, exigindo que o usuário esteja autenticado
 * 
 * @param string $tipo_acesso Tipo de acesso requerido (admin, vendedor)
 * @param string $redirect URL para redirecionamento em caso de falha na autenticação
 * @return bool
 */
function protegerPagina($tipo_acesso = '', $redirect = '') {
    // Se não estiver logado
    if (!isset($_SESSION['user_id'])) {
        // Se um redirecionamento específico foi definido
        if (!empty($redirect)) {
            header("Location: " . $redirect);
        } else {
            // Senão, vai para a página de login
            header("Location: emergency_login.php");
        }
        exit;
    }
    
    // Se um tipo específico de acesso foi exigido
    if (!empty($tipo_acesso)) {
        // Se o usuário não tem o tipo de acesso necessário
        if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== $tipo_acesso) {
            // Redirecionar para página específica se definida
            if (!empty($redirect)) {
                header("Location: " . $redirect);
            } else {
                // Ou redirecionar para a página apropriada conforme o tipo do usuário
                switch ($_SESSION['user_type']) {
                    case 'admin':
                        header("Location: admin/index.php");
                        break;
                    case 'vendedor':
                        header("Location: vendedor_index.php");
                        break;
                    default:
                        header("Location: index.php");
                }
            }
            exit;
        }
    }
    
    return true;
}

/**
 * Verifica se o usuário está logado
 *
 * @return bool
 */
function estaLogado() {
    return isset($_SESSION['user_id']);
}

/**
 * Verifica se o usuário tem permissão de administrador
 *
 * @return bool
 */
function ehAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

/**
 * Verifica se o usuário tem permissão de vendedor
 *
 * @return bool
 */
function ehVendedor() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'vendedor';
}


?>