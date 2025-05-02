<?php
// Função para formatar moeda
if (!function_exists('formatCurrency')) {
    function formatCurrency($value) {
        return "R$ " . number_format($value, 2, ",", ".");
    }
}

// Função para formatar porcentagem
if (!function_exists('formatPercentage')) {
    function formatPercentage($value) {
        return number_format($value, 2, ",", ".") . "%";
    }
}

// Função para formatar data
if (!function_exists('formatDate')) {
    function formatDate($date) {
        return date("d/m/Y", strtotime($date));
    }
}

// Outras funções de formatação que possam ser necessárias
if (!function_exists('formatDecimal')) {
    function formatDecimal($value, $decimals = 2) {
        return number_format($value, $decimals, ",", ".");
    }
}

if (!function_exists('formatPhone')) {
    function formatPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) === 11) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
        } elseif (strlen($phone) === 10) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
        }
        
        return $phone;
    }
}

if (!function_exists('formatCPF')) {
    function formatCPF($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf) === 11) {
            return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9);
        }
        
        return $cpf;
    }
}

if (!function_exists('formatCNPJ')) {
    function formatCNPJ($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        if (strlen($cnpj) === 14) {
            return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12);
        }
        
        return $cnpj;
    }
}
?>
