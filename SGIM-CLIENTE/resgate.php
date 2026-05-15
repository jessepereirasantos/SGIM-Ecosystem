<?php
/**
 * SGIM EMERGENCY RESCUE - Reseta o sistema para a versão estável da raiz.
 */
session_start();
header('Content-Type: text/plain');

echo "--- INICIANDO RESGATE SGIM ---\n\n";

$basePath = __DIR__ . '/';
$currentLink = $basePath . 'releases/current';

// 1. Remover link/ponte de atualização que possa estar quebrada
if (file_exists($currentLink)) {
    echo "[Limpeza] Removendo ponte 'releases/current'...\n";
    if (is_link($currentLink)) {
        unlink($currentLink);
    } else {
        // Se for uma pasta física (ponte de fallback), renomeia para desativar
        rename($currentLink, $currentLink . '_backup_' . time());
    }
    echo "OK: Ponte removida.\n\n";
} else {
    echo "[Limpeza] Nenhuma ponte ativa encontrada.\n\n";
}

// 2. Limpar logs de erro que possam estar travando o sistema
$logFile = $basePath . 'shared/system/logs/ota.log';
if (file_exists($logFile)) {
    echo "[Log] Limpando log de erro...\n";
    file_put_contents($logFile, "");
}

// 3. Resetar Estado Transacional
$stateFile = $basePath . 'shared/system/state/current_state.json';
if (file_exists($stateFile)) {
    echo "[Estado] Resetando transação pendente...\n";
    unlink($stateFile);
}

echo "\n--- RESGATE CONCLUÍDO ---\n";
echo "Tente acessar a dashboard.php agora. Se o erro 500 persistir, o problema é permissão de arquivo (CHMOD).";
?>
