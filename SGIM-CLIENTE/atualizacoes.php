<?php
/**
 * SGIM CLIENT - CENTRAL DE ATUALIZAÇÕES (Módulo Ativo)
 */
ob_start();
session_start();
require_once 'config/database.php';

// Controle de acesso
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title   = 'SGIM - Central de Atualizações';
$current_page = 'atualizacoes';

// Consulta local ao endpoint OTA para obter status real
$otaData = null;
$hasUpdate = false;
$currentVersion = '1.1.0';
$latestVersion = '';
$releaseNotes = '';
$downloadUrl = '';

try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
        $dbVer = $stmt ? $stmt->fetchColumn() : false;
        if ($dbVer) $currentVersion = $dbVer;
    }
} catch (Throwable $e) {}

try {
    $otaUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace(basename(__FILE__), 'ota.php', $_SERVER['PHP_SELF']);
    if (function_exists('curl_init')) {
        $ch = curl_init($otaUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $otaRaw = curl_exec($ch);
        curl_close($ch);
        if ($otaRaw) {
            $otaJson = json_decode($otaRaw, true);
            if ($otaJson && $otaJson['status'] === 'success') {
                $otaData = $otaJson;
                $hasUpdate = !empty($otaJson['has_update']);
                $latestVersion = $otaJson['latest_version'] ?? '';
                $releaseNotes = $otaJson['notes'] ?? '';
                if (isset($otaJson['manifest']['url'])) {
                    $downloadUrl = $otaJson['manifest']['url'];
                } elseif (isset($otaJson['manifest']['package'])) {
                    $base = rtrim($otaJson['manifest']['package'], '/');
                    $downloadUrl = 'https://escolateologicaeloha.com.br/api/update/packages/' . $otaJson['manifest']['package'];
                }
            }
        }
    }
} catch (Throwable $e) {}

require_once 'includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-3xl font-black text-white tracking-tight italic uppercase">Central de <span class="text-brand">Atualizações</span></h2>
    <p class="text-xs text-gray-500 uppercase tracking-widest font-bold mt-1">Status do Módulo: <?= $hasUpdate ? 'Atualização Disponível' : 'Sistema Atualizado' ?></p>
</div>

<div class="mt-8 max-w-4xl space-y-6">
    <!-- Card Versões -->
    <div class="glass-card p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-white">Versões Instaladas</h3>
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $hasUpdate ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20' ?>">
                <?= $hasUpdate ? 'Nova Versão Detectada' : 'Versão Atual' ?>
            </span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-darkbg/50 border border-darkborder rounded-xl p-4">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-1">Versão Local</p>
                <p class="text-2xl font-black text-white"><?= htmlspecialchars($currentVersion) ?></p>
            </div>
            <div class="bg-darkbg/50 border border-darkborder rounded-xl p-4">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-1">Versão Disponível</p>
                <p class="text-2xl font-black text-emerald-400"><?= htmlspecialchars($latestVersion ?: '—') ?></p>
            </div>
        </div>
    </div>

    <?php if ($hasUpdate): ?>
    <!-- Card Changelog -->
    <div class="glass-card p-6">
        <h3 class="text-lg font-bold text-white mb-3">Novidades da Versão <?= htmlspecialchars($latestVersion) ?></h3>
        <div class="bg-darkbg/50 border border-darkborder rounded-xl p-4 text-gray-300 text-sm leading-relaxed whitespace-pre-line">
            <?= nl2br(htmlspecialchars($releaseNotes ?: 'Changelog não disponível.')) ?>
        </div>
    </div>

    <!-- Card Ação -->
    <div class="glass-card p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-white mb-1">Atualizar Sistema</h3>
                <p class="text-xs text-gray-400">A atualização será aplicada automaticamente em 1 clique.</p>
            </div>
            <a href="admin_ota.php" class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-black px-6 py-3 rounded-xl transition-all shadow-lg shadow-emerald-500/20">
                <span class="material-symbols-outlined">system_update</span>
                ATUALIZAR AGORA
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="glass-card p-12 text-center">
        <div class="w-20 h-20 bg-darkbg border border-darkborder rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
            <span class="material-symbols-outlined text-4xl text-emerald-400">verified</span>
        </div>
        <h3 class="text-2xl font-black text-white mb-2">Sistema Atualizado</h3>
        <p class="text-gray-400 max-w-md mx-auto">
            Você está executando a versão mais recente disponível do SGIM. Nenhuma ação é necessária.
        </p>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>