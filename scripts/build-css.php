<?php
require __DIR__.'/../vendor/autoload.php';

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;

$scss = new Compiler();
$scss->setOutputStyle(OutputStyle::COMPRESSED);

// Rutas donde buscar @import
$scss->setImportPaths([
    __DIR__ . '/../resources/scss',
    __DIR__ . '/../vendor/bootstrap/scss',
]);

$input  = __DIR__ . '/../resources/scss/app.scss';
$output = __DIR__ . '/../public/css/bootstrap-bachpedia.min.css';

try {
    $css = $scss->compileString(file_get_contents($input))->getCss();
    if (!is_dir(dirname($output))) { mkdir(dirname($output), 0775, true); }
    file_put_contents($output, $css);
    echo "✔ Generado: {$output}\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "✖ Error: ".$e->getMessage()."\n");
    exit(1);
}
