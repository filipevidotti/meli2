<?php
// Criar diretório assets e subdiretórios
$directories = [
    'assets/css',
    'assets/js',
    'assets/img'
];

foreach ($directories as $directory) {
    $path = __DIR__ . '/' . $directory;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
        echo "Diretório criado: $directory<br>";
    } else {
        echo "Diretório já existe: $directory<br>";
    }
}

// Criar arquivo CSS básico
$css_content = "/* Estilos customizados para CalcMeli */\n\n/* Adicione seus estilos personalizados aqui */";
file_put_contents(__DIR__ . '/assets/css/style.css', $css_content);
echo "Arquivo style.css criado<br>";

// Criar arquivo JS básico
$js_content = "// Scripts personalizados para CalcMeli\n\n// Adicione seus scripts personalizados aqui";
file_put_contents(__DIR__ . '/assets/js/script.js', $js_content);
echo "Arquivo script.js criado<br>";

echo "Configuração de assets concluída!";
?>
