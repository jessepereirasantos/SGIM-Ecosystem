<?php
ob_start();
session_start();
require_once 'config/db.php';

// Controle de acesso
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// *** DEVE VIR ANTES do require header.php ***
$page_title   = 'SGIM - Central de Atualizações';
$current_page = 'atualizacoes';

// Forçar novo check ao entrar nesta página
unset($_SESSION['last_ota_check']);

require_once 'includes/header.php';

// Pegar Versão Local
$v_local = "1.1.0";
try {
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
    $stmt->execute();
    $v_local = $stmt->fetchColumn() ?: '1.1.0';
} catch (Exception $e) {
    $v_local = "Erro ao ler banco";
}

// Verificar se há update pendente na sessão
$updateInfo = $_SESSION['ota_available'] ?? null;
$hasUpdate = isset($updateInfo['has_update']) && $updateInfo['has_update'] === true;
$v_latest = $updateInfo['latest'] ?? $updateInfo['latest_version'] ?? $v_local;
?>

<div class="mb-6">
    <h2 class="text-3xl font-black text-white tracking-tight italic uppercase">Central de <span class="text-brand">Atualizações</span></h2>
    <p class="text-xs text-gray-500 uppercase tracking-widest font-bold mt-1">Mantenha seu sistema sempre na melhor versão (SaaS OTA)</p>
</div>

<div class="mt-8 max-w-4xl">
    <div class="bg-darkcard border border-darkborder rounded-twelve p-8 shadow-2xl relative overflow-hidden">
        <!-- Decoration -->
        <div class="absolute -top-24 -right-24 w-64 h-64 bg-brand/5 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex items-center gap-6 mb-8 relative z-10">
            <div class="w-20 h-20 bg-darkbg border border-darkborder rounded-full flex items-center justify-center shadow-inner flex-shrink-0">
                <span class="material-symbols-outlined text-4xl text-brand">cloud_sync</span>
            </div>
            <div>
                <h3 class="text-2xl font-black text-white">Status do Sistema</h3>
                <div class="flex flex-wrap items-center gap-3 mt-2">
                    <span class="bg-white/5 border border-white/10 px-3 py-1.5 rounded-full text-xs font-bold text-gray-400">Versão Atual: v<?= htmlspecialchars($v_local) ?></span>
                    <?php if ($hasUpdate): ?>
                        <span class="bg-red-500/10 border border-red-500/20 px-3 py-1.5 rounded-full text-xs font-bold text-red-400 flex items-center gap-1 animate-pulse">
                            <span class="material-symbols-outlined text-[14px]">new_releases</span> v<?= htmlspecialchars($v_latest) ?> Disponível
                        </span>
                    <?php else: ?>
                        <span class="bg-emerald-500/10 border border-emerald-500/20 px-3 py-1.5 rounded-full text-xs font-bold text-emerald-400 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">check_circle</span> Atualizado
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="border-t border-darkborder pt-8 relative z-10">
            <div id="update-container">
                <?php if ($hasUpdate): ?>
                    <p class="text-sm text-gray-400 mb-6 leading-relaxed max-w-2xl">Uma nova versão do SGIM está disponível para download. Recomendamos aplicar a atualização imediatamente para receber os novos recursos, melhorias de estabilidade e correções de segurança.</p>
                    <button id="btn-update" onclick="runUpdate()" class="w-full sm:w-auto px-8 py-4 bg-brand hover:bg-yellow-500 text-black rounded-xl font-black uppercase tracking-widest text-sm transition-all shadow-lg shadow-brand/20 flex items-center justify-center gap-3">
                        <span class="material-symbols-outlined">download</span>
                        Baixar e Instalar v<?= htmlspecialchars($v_latest) ?>
                    </button>
                <?php else: ?>
                    <p class="text-sm text-gray-400 mb-6 leading-relaxed max-w-2xl">Você já está rodando a versão mais recente e segura do sistema SGIM. Nenhuma ação é necessária no momento. Você será notificado automaticamente quando uma nova atualização for liberada no Master.</p>
                    <button id="btn-update" onclick="runUpdate()" class="w-full sm:w-auto px-8 py-3 bg-darkbg border border-darkborder hover:border-brand/30 text-gray-300 rounded-xl font-bold uppercase tracking-widest text-xs transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-base">refresh</span>
                        Verificar Novamente
                    </button>
                <?php endif; ?>
            </div>

            <!-- Div de Progresso Oculta -->
            <div id="progress-container" class="hidden">
                <div class="flex flex-col items-center justify-center py-6 bg-darkbg border border-darkborder rounded-xl">
                    <span class="material-symbols-outlined text-brand text-5xl animate-spin mb-4" style="animation-duration: 2s;">sync</span>
                    <h4 class="text-white font-bold text-lg mb-1">Processando Atualização...</h4>
                    <p id="progress-status" class="text-sm text-brand font-mono font-bold animate-pulse text-center px-4">Conectando ao Master e Baixando Pacotes OTA...</p>
                    
                    <div class="w-full max-w-md h-2 bg-darkcard rounded-full mt-6 overflow-hidden border border-darkborder">
                        <div class="h-full bg-brand w-1/2 rounded-full relative overflow-hidden shadow-[0_0_10px_rgba(255,193,7,0.5)]" style="animation: loadbar 2s infinite ease-in-out alternate;"></div>
                    </div>
                    <p class="text-[10px] text-gray-500 uppercase tracking-widest mt-4 font-bold">Por favor, não feche esta janela.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes loadbar {
    0% { width: 10%; margin-left: 0; }
    100% { width: 40%; margin-left: 60%; }
}
</style>

<script>
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
                if (data.updated === false) {
                    statusText.innerText = 'Sistema já está atualizado!';
                    statusText.style.color = '#10b981';
                    setTimeout(() => {
                        container.style.display = 'block';
                        progress.classList.add('hidden');
                        window.location.reload(); // Recarrega para limpar o estado
                    }, 2500);
                } else {
                    statusText.innerText = 'Sucesso! Finalizando instalação e recarregando sistema...';
                    statusText.style.color = '#10b981'; // emerald-500
                    setTimeout(() => {
                        window.location.href = 'dashboard.php?update=success';
                    }, 2000);
                }
            } else {
                statusText.innerText = 'FALHA: ' + data.message;
                statusText.style.color = '#ef4444'; // red-500
                
                // Mostrar botão de voltar após erro
                setTimeout(() => {
                    container.style.display = 'block';
                    progress.classList.add('hidden');
                    alert('Erro na atualização: ' + data.message);
                }, 4000);
            }
        })
        .catch(err => {
            statusText.innerText = 'ERRO DE COMUNICAÇÃO: Não foi possível baixar do Master.';
            statusText.style.color = '#ef4444';
            setTimeout(() => {
                container.style.display = 'block';
                progress.classList.add('hidden');
            }, 4000);
        });
}
</script>

<?php require_once 'includes/footer.php'; ?>