<?php
/**
 * SGIM System Bootstrap (Saneado - Zero Resíduo OTA)
 */
session_start();

// Localização segura do Banco de Dados
$dbConfig = __DIR__ . '/../config/database.php';
if (file_exists($dbConfig)) {
    require_once $dbConfig;
}

// Autoload
if (file_exists(__DIR__ . '/autoload.php')) {
    require_once __DIR__ . '/autoload.php';
}

// O bootstrap agora é puramente um carregador de dependências.
// Nenhuma lógica de auto-patching ou verificação de versão automática é permitida aqui.
