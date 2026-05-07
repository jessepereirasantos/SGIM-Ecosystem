<?php
$dir = __DIR__;
$search = "Chave de licenca nao fornecida";
$search2 = "Chave de licença não fornecida";

function scan($dir, $search, $search2) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            scan($path, $search, $search2);
        } else {
            if (strpos($file, '.php') !== false || strpos($file, '.htaccess') !== false) {
                $content = file_get_contents($path);
                if (strpos($content, $search) !== false || strpos($content, $search2) !== false) {
                    echo "ENCONTRADO EM: $path\n";
                }
            }
        }
    }
}

echo "Iniciando busca...\n";
scan($dir, $search, $search2);
echo "Busca finalizada.\n";
