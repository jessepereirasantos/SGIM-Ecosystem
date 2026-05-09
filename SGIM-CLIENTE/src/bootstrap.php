<?php
/**
 * SGIM System Bootstrap (Saneado - Zero Resíduo OTA)
 */
session_start();

// Configurações do Banco de Dados
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/autoload.php';

// O bootstrap agora é puramente um carregador de dependências.
// Nenhuma lógica de auto-patching ou verificação de versão automática é permitida aqui.
