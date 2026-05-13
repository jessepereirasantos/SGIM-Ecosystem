<?php
/**
 * SGIM OTA - OPERATION PANEL (INDUSTRIAL)
 * Interface administrativa para controle manual de atualizações.
 */

require_once 'config/database.php';
require_once 'src/autoload.php';              // ✅ FIX: Garante autoload SGIM\OTA\
require_once 'includes/system/OtaOrchestrator.php';
require_once 'includes/system/OtaPassiveReporter.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!$pdo instanceof PDO) {
    die(json_encode(['status' => 'error', 'message' => 'Sem conexão com banco. Verifique db_config.php']));
}

// ✅ FIX: Lê master_url do banco — não depende de hardcode
$masterUrl = 'https://escolateologicaeloha.com.br'; // fallback seguro
try {
    $stmtMaster = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'master_url' LIMIT 1");
    $urlDb = $stmtMaster ? $stmtMaster->fetchColumn() : null;
    if ($urlDb && $urlDb !== 'PADRÃO' && filter_var($urlDb, FILTER_VALIDATE_URL)) {
        $masterUrl = rtrim($urlDb, '/');
    }
} catch (Exception $e) { /* usa fallback */ }

$reporter     = new SGIM\OTA\OtaPassiveReporter(__DIR__);
$orchestrator = new SGIM\OTA\OtaOrchestrator($pdo, __DIR__, $masterUrl); // ✅ master_url do banco

$telemetry = $reporter->getTelemetryMatrix(); // ✅ atribuição restaurada
$report    = $telemetry['ota_infrastructure']['download_state'] ?? [];


// Processamento de Ações Manuais
$msg = "";
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'prepare':
            $res = $orchestrator->updateLifecycle();
            $msg = "Status: $res";
            break;
        case 'commit':
            $version = $_POST['version'];
            if ($orchestrator->commitUpdate($version)) {
                $msg = "Versão $version ATIVADA.";
            } else {
                $msg = "ERRO NA ATIVAÇÃO. Verifique os logs.";
            }
            break;
    }
    // Refresh para carregar novos estados
    header("Location: admin_ota.php?msg=" . urlencode($msg));
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>SGIM OTA - Painel de Operações</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .ota-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .status-badge { padding: 5px 10px; border-radius: 4px; font-weight: bold; }
        .status-ready { background: #e3f2fd; color: #1976d2; }
        .status-active { background: #e8f5e9; color: #2e7d32; }
        .btn-ota { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-prepare { background: #2196f3; color: white; }
        .btn-commit { background: #4caf50; color: white; }
        .log-box { background: #333; color: #0f0; padding: 15px; font-family: monospace; height: 150px; overflow-y: scroll; border-radius: 4px; }
    </style>
</head>
<body>
    <div style="max-width: 900px; margin: 40px auto; font-family: sans-serif;">
        <h1>🛡️ OTA Operation Panel</h1>
        
        <?php if(isset($_GET['msg'])): ?>
            <div style="padding:15px; background:#fff3cd; margin-bottom:20px; border-radius:4px;"><?php echo htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>

        <div class="ota-card">
            <h2>Release Disponível</h2>
            <p><strong>Versão:</strong> <?php echo $telemetry['ota_infrastructure']['current_release']; ?></p>
            <p><strong>Driver:</strong> <?php echo $telemetry['ota_infrastructure']['download_state']['recommended_driver'] ?? 'SharedHostingDriver'; ?></p>
            
            <form method="POST" style="margin-top: 20px;">
                <button type="submit" name="action" value="prepare" class="btn-ota btn-prepare">1. PREPARAR ATUALIZAÇÃO</button>
                <input type="hidden" name="version" value="1.1.0"> <!-- Exemplo fixo para teste -->
                <button type="submit" name="action" value="commit" class="btn-ota btn-commit" onclick="return confirm('Deseja ativar esta versão agora? O sistema será atualizado fisicamente.')">2. ATIVAR AGORA</button>
            </form>
        </div>

        <div class="ota-card">
            <h3>Logs de Operação</h3>
            <div class="log-box">
                <?php foreach($telemetry['last_operations'] as $log): ?>
                    <div><?php echo htmlspecialchars($log); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
