<?php
// Redirecionamento de compatibilidade de cadastro_publico.php para a nova rota amigável /cadastro
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$scriptName = $_SERVER['SCRIPT_NAME']; 
$subDir = dirname($scriptName);
$subDir = ($subDir === '/' || $subDir === '\\') ? '' : $subDir;

$newUrl = $protocol . $domain . $subDir . '/cadastro';
header("Location: " . $newUrl, true, 301);
exit;
?>
