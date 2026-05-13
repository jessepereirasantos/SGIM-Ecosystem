<?php
$files = glob("*.php");
$count = 0;

foreach ($files as $file) {
    if ($file === 'setup.php' || $file === 'database.php') continue;
    
    $content = file_get_contents($file);
    
    // Substitui qualquer variação incorreta de include de config de banco
    // ex: require_once 'config/database.php'; ou require_once 'config/database.php'; ou require 'db_config.php';
    // pela ponte unificada: require_once 'config/database.php';
    
    $modified = preg_replace('/require_once\s+[\'"](?:config\/)?(?:db|db_config)\.php[\'"]\s*;/i', "require_once 'config/database.php';", $content);
    
    // Também limpa o HTML duplo do dashboard.php
    if ($file === 'dashboard.php') {
        $modified = preg_replace('/<!DOCTYPE html>\s*<html[^>]*>\s*<head>.*?<\/head>\s*<body[^>]*>/is', '', $modified);
    }
    
    if ($content !== $modified) {
        file_put_contents($file, $modified);
        $count++;
    }
}
echo "Correção aplicada em $count arquivos com sucesso!";
