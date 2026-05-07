<?php
/**
 * SCRATCH - DISPARADOR DE PUBLICAÇÃO REAL
 */
$_POST['versao'] = '1.5.0';
$_POST['novidades'] = "Sincronização Total v1.5.0\nProva Visual: SUPORTE VIP TESTE\nCorreção SQL data_vencimento";
$_POST['notificar_email'] = '0';

// Simular Sessão para o Master
session_start();
$_SESSION['usuario_id'] = 1;

require_once __DIR__ . '/SGIM-VENDAS/api/publish_update.php';
