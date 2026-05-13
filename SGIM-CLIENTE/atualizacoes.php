<?php
/**
 * SGIM CLIENT - CENTRAL DE ATUALIZAÇÕES v2.0
 * Lê o manifesto DIRETAMENTE do Master (sem loopback cURL).
 * Instala com 1 clique via /api/ota_install.php.
 */
ob_start();
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title   = 'SGIM - Central de Atualizações';
$current_page = 'atualizacoes';

// ── Versão local (banco de dados) ────────────────────────────────────────────
$currentVersion = '1.1.41';
try {
    if ($pdo) {
        $s = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
        $v = $s ? $s->fetchColumn() : false;
        if ($v) $currentVersion = $v;
    }
} catch (Throwable $e) {}

// ── Master URL ────────────────────────────────────────────────────────────────
$masterUrl = 'https://escolateologicaeloha.com.br';
try {
    if ($pdo) {
        $s = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'master_url'");
        $u = $s ? $s->fetchColumn() : false;
        if ($u && $u !== 'PADRÃO' && filter_var($u, FILTER_VALIDATE_URL)) {
            $masterUrl = rtrim($u, '/');
        }
    }
} catch (Throwable $e) {}

// ── Manifesto DIRETO do Master (sem loopback) ─────────────────────────────────
$manifest      = null;
$hasUpdate     = false;
$latestVersion = '';
$releaseNotes  = '';
$downloadUrl   = '';
$manifestError = '';

function getMasterManifest($url) {
    // Tenta file_get_contents
    $ctx = stream_context_create([
        'http' => ['timeout' => 10, 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw) return $raw;

    // Fallback cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        return $raw;
    }
    return false;
}

$manifestUrl = $masterUrl . '/api/update/latest.json';
$rawManifest = getMasterManifest($manifestUrl);

if ($rawManifest) {
    $manifest = json_decode($rawManifest, true);
    if ($manifest && isset($manifest['version'])) {
        $latestVersion = $manifest['version'];
        $releaseNotes  = $manifest['notes'] ?? '';
        $downloadUrl   = $manifest['url'] ?? '';
        $hasUpdate     = version_compare($latestVersion, $currentVersion, '>');
    } else {
        $manifestError = 'Manifesto JSON inválido recebido do Master.';
    }
} else {
    $manifestError = "Não foi possível acessar o Master em: $manifestUrl. Verifique se o domínio está acessível.";
}


require_once 'includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-3xl font-black text-white tracking-tight italic uppercase">
        Central de <span class="text-brand">Atualizações</span>
    </h2>
    <p class="text-xs text-gray-500 uppercase tracking-widest font-bold mt-1">
        Status: <?= $hasUpdate ? '🟡 Atualização Disponível' : ($manifestError ? '🔴 Erro ao consultar Master' : '🟢 Sistema Atualizado') ?>
    </p>
</div>

<div class="mt-8 max-w-4xl space-y-6" x-data="{
    installing: false,
    progress: '',
    result: null,
    async install() {
        if (!confirm('Confirma a instalação da atualização? O sistema será atualizado agora.')) return;
        this.installing = true;
        this.progress = 'Conectando ao Master e baixando pacote...';
        this.result = null;
        try {
            let res  = await fetch('api/ota_install.php', { method: 'POST' });
            let data = await res.json();
            this.result = data;
            this.progress = '';
            this.installing = false;
            if (data.status === 'success') {
                setTimeout(() => window.location.reload(), 3000);
            }
        } catch(e) {
            this.result = { status: 'error', message: 'Erro de conexão: ' + e.message };
            this.progress = '';
            this.installing = false;
        }
    }
}">

    <?php if ($manifestError): ?>
    <!-- Erro de conexão com Master -->
    <div class="glass-card p-6 border-red-500/30">
        <div class="flex items-start gap-4">
            <span class="material-symbols-outlined text-red-400 text-3xl">error</span>
            <div>
                <h3 class="font-bold text-white mb-1">Erro ao consultar o servidor Master</h3>
                <p class="text-xs text-red-300 font-mono"><?= htmlspecialchars($manifestError) ?></p>
                <p class="text-xs text-gray-500 mt-2">Master URL: <code class="text-gray-300"><?= htmlspecialchars($manifestUrl) ?></code></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-1">Versão Local (Instalada)</p>
                <p class="text-2xl font-black text-white"><?= htmlspecialchars($currentVersion) ?></p>
            </div>
            <div class="bg-darkbg/50 border border-darkborder rounded-xl p-4">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-1">Versão Disponível (Master)</p>
                <p class="text-2xl font-black <?= $hasUpdate ? 'text-emerald-400' : 'text-gray-400' ?>">
                    <?= htmlspecialchars($latestVersion ?: ($manifestError ? 'Erro' : '—')) ?>
                </p>
            </div>
        </div>
    </div>

    <?php if ($hasUpdate): ?>
    <!-- Changelog -->
    <div class="glass-card p-6">
        <h3 class="text-lg font-bold text-white mb-3">
            Novidades da Versão <?= htmlspecialchars($latestVersion) ?>
        </h3>
        <div class="bg-darkbg/50 border border-darkborder rounded-xl p-4 text-gray-300 text-sm leading-relaxed whitespace-pre-line">
            <?= nl2br(htmlspecialchars($releaseNotes ?: 'Changelog não disponível.')) ?>
        </div>
        <?php if ($downloadUrl): ?>
        <p class="text-[10px] text-gray-600 mt-2 font-mono">
            Pacote: <?= htmlspecialchars($downloadUrl) ?>
        </p>
        <?php endif; ?>
    </div>

    <!-- Card Ação Principal -->
    <div class="glass-card p-6">
        <!-- Feedback de resultado -->
        <template x-if="result">
            <div :class="result.status === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-red-500/10 border-red-500/30 text-red-300'"
                 class="border rounded-xl p-4 mb-4 text-sm font-mono">
                <p class="font-bold mb-1" x-text="result.status === 'success' ? '✅ Sucesso!' : '❌ Erro'"></p>
                <p x-text="result.message"></p>
                <template x-if="result.status === 'success'">
                    <p class="text-xs mt-2 text-gray-400">Recarregando em 3 segundos...</p>
                </template>
                <template x-if="result.files_failed > 0">
                    <p class="text-xs mt-1 text-yellow-400" x-text="'⚠ ' + result.files_failed + ' arquivo(s) com falha de permissão'"></p>
                </template>
            </div>
        </template>

        <!-- Barra de progresso durante instalação -->
        <template x-if="installing">
            <div class="bg-darkbg/50 border border-darkborder rounded-xl p-4 mb-4">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-emerald-400 animate-spin">sync</span>
                    <p class="text-sm text-emerald-300 font-mono" x-text="progress"></p>
                </div>
                <div class="mt-3 h-1.5 bg-darkborder rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full animate-pulse" style="width: 100%"></div>
                </div>
            </div>
        </template>

        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-white mb-1">Instalar Atualização</h3>
                <p class="text-xs text-gray-400">
                    O sistema será atualizado de <strong class="text-white"><?= htmlspecialchars($currentVersion) ?></strong>
                    para <strong class="text-emerald-400"><?= htmlspecialchars($latestVersion) ?></strong> automaticamente.
                </p>
            </div>
            <button
                @click="install()"
                :disabled="installing"
                class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 disabled:cursor-wait text-black text-xs font-black px-6 py-3 rounded-xl transition-all shadow-lg shadow-emerald-500/20 ml-6 shrink-0">
                <span class="material-symbols-outlined" :class="installing ? 'animate-spin' : ''" x-text="installing ? 'sync' : 'system_update'">system_update</span>
                <span x-text="installing ? 'INSTALANDO...' : 'ATUALIZAR AGORA'">ATUALIZAR AGORA</span>
            </button>
        </div>
    </div>

    <?php else: ?>
    <!-- Já atualizado -->
    <div class="glass-card p-12 text-center">
        <div class="w-20 h-20 bg-darkbg border border-darkborder rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
            <span class="material-symbols-outlined text-4xl text-emerald-400">verified</span>
        </div>
        <h3 class="text-2xl font-black text-white mb-2">Sistema Atualizado</h3>
        <p class="text-gray-400 max-w-md mx-auto">
            Você está executando a versão <strong class="text-white"><?= htmlspecialchars($currentVersion) ?></strong>, a mais recente disponível.
        </p>
    </div>
    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>