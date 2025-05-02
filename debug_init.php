<?php
// Definir exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Depuração do Arquivo init.php</h1>";
echo "<pre>";

// Iniciar análise linha por linha
$file = file_get_contents('init.php');
$lines = explode("\n", $file);

foreach ($lines as $number => $line) {
    $line_number = $number + 1;
    echo "Linha $line_number: " . htmlspecialchars($line) . "\n";
    
    try {
        // Avaliar cada linha (com cuidado)
        if (trim($line) && strpos($line, 'session_start') === false && 
            strpos($line, 'function') === false && strpos($line, 'class') === false) {
            
            // Pular comentários e fechamentos PHP
            if (strpos(trim($line), '//') === 0 || trim($line) == '?>' || 
                strpos(trim($line), '/*') === 0 || strpos(trim($line), '*') === 0) {
                continue;
            }
            
            // Testar linha
            try {
                eval($line);
                echo "  ✅ Executado sem erros\n";
            } catch (ParseError $e) {
                // Ignorar erros de parse (código incompleto)
                echo "  ⚠️ Sintaxe incompleta\n";
            } catch (Error $e) {
                echo "  ❌ ERRO: " . $e->getMessage() . "\n";
            }
        }
    } catch (Exception $e) {
        echo "  ❌ Exceção: " . $e->getMessage() . "\n";
    }
}

echo "</pre>";
echo "<p>Análise concluída.</p>";
?>
