<?php
/**
 * SGIM CLIENT - CENTRAL DE ATUALIZAÇÕES v4.5 (FORCE SYNC)
 */
ob_start();
session_start();
require_once 'config/db.php';

// Controle de acesso
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title   = 'SGIM - Central de Atualizações';
$current_page = 'atualizacoes';

// FORÇAR LIMPEZA DE CACHE DE SESSÃO PARA OTA
unset($_SESSION['last_ota_check']);
unset($_SESSION['ota_available']);

require_once 'includes/header.php';

// 1. Pegar Versão Local (Prioridade: version.json, Fallback: Banco)
$v_local = "1.1.0";
$version_file = __DIR__ . '/version.json';
if (file_exists($version_file)) {
    $v_data = json_decode(file_get_contents($version_file), true);
    $v_local = $v_data['version'] ?? '1.1.0';
} else {
    try {
        $v_local = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'")->fetchColumn() ?: '1.1.0';
    } catch (Exception $e) {
        $v_local = "Error";
    }
}

// 2. O Header.php ou um check via AJAX deve preencher o ota_available
// Como limpamos a sessão acima, vamos forçar uma exibição de "Verificando..." ou similar
$updateInfo = $_SESSION['ota_available'] ?? null;
$hasUpdate = isset($updateInfo['has_update']) && $updateInfo['has_update'] === true;
$v_latest = $updateInfo['latest'] ?? $updateInfo['latest_version'] ?? '...';
?>

<div class="mb-6">
    <h2 class="text-3xl font-black text-white tracking-tight italic uppercase">Central de <span class="text-brand">Atualizações</span></h2>
    <p class="text-xs text-gray-500 uppercase tracking-widest font-bold mt-1">Source of Truth: Version.json + Master API</p>
</div>

<div class="mt-8 max-w-4xl">
    <div class="bg-darkcard border border-darkborder rounded-twelve p-8 shadow-2xl relative overflow-hidden">
        <div class="flex items-center gap-6 mb-8 relative z-10">
            <div class="w-20 h-20 bg-darkbg border border-darkborder rounded-full flex items-center justify-center shadow-inner flex-shrink-0">
                <span class="material-symbols-outlined text-4xl text-brand">cloud_sync</span>
            </div>
            <div>
                <h3 class="text-2xl font-black text-white">Status do Sistema</h3>
                <div class="flex flex-wrap items-center gap-3 mt-2">
                    <span class="bg-white/5 border border-white/10 px-3 py-1.5 rounded-full text-xs font-bold text-gray-400">Sua Versão: v<?= htmlspecialchars($v_local) ?></span>
                    
                    <div id="check-badge">
                        <span class="bg-blue-500/10 border border-blue-500/20 px-3 py-1.5 rounded-full text-xs font-bold text-blue-400 animate-pulse">Consultando Master...</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-darkborder pt-8 relative z-10">
            <div id="update-container">
                <p class="text-sm text-gray-400 mb-6 leading-relaxed max-w-2xl" id="status-msg">Sincronizando com o servidor de distribuição OTA...</p>
                <button id="btn-action" disabled class="opacity-50 cursor-not-allowed px-8 py-4 bg-brand text-black rounded-xl font-black uppercase tracking-widest text-sm transition-all flex items-center justify-center gap-3">
                    Aguarde...
                </button>
            </div>

            <!-- Div de Progresso Oculta -->
            <div id="progress-container" class="hidden">
                <div class="flex flex-col items-center justify-center py-6 bg-darkbg border border-darkborder rounded-xl">
                    <span class="material-symbols-outlined text-brand text-5xl animate-spin mb-4">sync</span>
                    <h4 class="text-white font-bold text-lg mb-1">Processando Atualização...</h4>
                    <p id="progress-status" class="text-sm text-brand font-mono font-bold animate-pulse text-center px-4">Baixando arquivos da v1.5.99...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    checkVersion();
});

function checkVersion() {
    fetch('api/ota_process.php?check_only=1')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('check-badge');
            const btn = document.getElementById('btn-action');
            const msg = document.getElementById('status-msg');

            if (data.has_update) {
                badge.innerHTML = `<span class="bg-red-500/10 border border-red-500/20 px-3 py-1.5 rounded-full text-xs font-bold text-red-400 animate-pulse">v${data.latest} Disponível</span>`;
                msg.innerHTML = `Uma nova versão (v${data.latest}) está disponível. Clique abaixo para iniciar o download atômico.`;
                btn.innerHTML = `<span class="material-symbols-outlined">download</span> BAIXAR v${data.latest} AGORA`;
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                btn.onclick = runUpdate;
            } else {
                badge.innerHTML = `<span class="bg-emerald-500/10 border border-emerald-500/20 px-3 py-1.5 rounded-full text-xs font-bold text-emerald-400">Sistema Atualizado</span>`;
                msg.innerHTML = "Você já está rodando a versão mais recente do SGIM. Nenhuma ação é necessária.";
                btn.innerHTML = `<span class="material-symbols-outlined text-base">refresh</span> VERIFICAR NOVAMENTE`;
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                btn.onclick = () => location.reload();
            }
        });
}

function runUpdate() {
    const container = document.getElementById('update-container');
    const progress = document.getElementById('progress-container');
    const statusText = document.getElementById('progress-status');
    
    container.style.display = 'none';
    progress.classList.remove('hidden');

    fetch('api/ota_process.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                statusText.innerText = 'Sucesso! v' + data.version + ' instalada. Recarregando...';
                statusText.style.color = '#10b981';
                setTimeout(() => { window.location.href = 'dashboard.php?update=success'; }, 2000);
            } else {
                alert('Erro: ' + data.message);
                location.reload();
            }
        });
}
</script>

<?php require_once 'includes/footer.php'; ?>