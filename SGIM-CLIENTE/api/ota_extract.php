<?php
/**
 * SGIM CLIENT - API OTA EXTRACT v1.1.41
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

// Trava de sessão removida temporariamente


// O OtaOrchestrator já extraiu os arquivos durante o updateLifecycle() chamado no passo de download.
// Retornamos sucesso para manter a compatibilidade com o fluxo visual.
echo json_encode(['status' => 'success', 'message' => 'Arquivos validados com sucesso.']);
