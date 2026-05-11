<?php
require_once 'config/database.php';
$current_page = 'ota';

// 🛠️ Dados OTA (lidos do manifesto oficial)
$manifestPath = __DIR__ . '/api/update/latest.json';
$manifest = [];
if (file_exists($manifestPath)) {
    $decoded = json_decode(@file_get_contents($manifestPath), true);
    if (is_array($decoded)) $manifest = $decoded;
}

$current_version = 'v' . ($manifest['version'] ?? 'N/A');
$last_update = 'N/A';
if (!empty($manifest['published_at'])) {
    $ts = strtotime($manifest['published_at']);
    if ($ts) $last_update = date('d/m/Y', $ts);
} elseif (!empty($manifest['release_date'])) {
    $ts = strtotime($manifest['release_date']);
    if ($ts) $last_update = date('d/m/Y', $ts);
}

$active_nodes = 1;

include 'templates/header.php';
?>

<div class="flex" x-data="{ 
    loading: false, 
    statusMsg: '',
    async executeAction(action) {
        this.loading = true;
        this.statusMsg = 'Processando...';
        
        let fd = new FormData();
        fd.append('acao', action);
        
        try {
            let res = await fetch('api/ota_action.php', { method: 'POST', body: fd });
            let data = await res.json();
            
            if(data.status === 'success') {
                this.statusMsg = data.message;
                setTimeout(() => { this.statusMsg = ''; this.loading = false; }, 3000);
            } else {
                alert(data.message);
                this.loading = false;
            }
        } catch (e) {
            alert('Erro de conexão com a API OTA');
            this.loading = false;
        }
    }
}">
    <?php include 'sidebar.php'; ?>

    <main class="ml-[280px] min-h-screen flex-1">
        <!-- Status Bar Flutuante (Feedback) -->
        <template x-if="statusMsg">
            <div class="fixed top-20 right-10 z-[100] bg-primary text-on-primary px-6 py-3 rounded-xl shadow-2xl animate-bounce font-bold text-sm">
                <span x-text="statusMsg"></span>
            </div>
        </template>

        <!-- Top Navigation -->
        <header class="h-16 flex items-center justify-between px-8 bg-surface/80 backdrop-blur-md sticky top-0 z-40 border-b border-outline-variant/10">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">cloud_sync</span>
                <span class="text-xs font-bold text-on-surface-variant uppercase tracking-widest">OTA Management Hub</span>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-right">
                    <p class="text-body-sm font-bold text-on-surface">Admin OTA</p>
                    <p class="text-[10px] uppercase tracking-widest text-primary font-bold">Orchestrator Node</p>
                </div>
                <div class="size-10 bg-surface-container-highest border border-primary/20 rounded-lg flex items-center justify-center text-primary font-black">O</div>
            </div>
        </header>

        <div class="p-10 max-w-[1600px] mx-auto">
            <!-- Header Section -->
            <div class="flex justify-between items-end mb-10">
                <div>
                    <h2 class="text-display-lg font-bold text-on-surface tracking-tighter">Engenharia <span class="text-primary">OTA</span></h2>
                    <p class="text-on-surface-variant font-body-md opacity-60">Gerenciamento de releases, sincronização de versões e auditoria de nodes.</p>
                </div>
                <button 
                    @click="executeAction('publicar_release')"
                    :disabled="loading"
                    class="px-5 py-2.5 rounded-lg bg-primary text-on-primary font-bold hover:opacity-90 transition-all flex items-center gap-2 text-sm shadow-xl shadow-primary/20 disabled:opacity-50">
                    <span class="material-symbols-outlined text-sm" :class="loading ? 'animate-spin' : ''" x-text="loading ? 'sync' : 'publish'">publish</span>
                    <span x-text="loading ? 'PUBLICANDO...' : 'PUBLICAR RELEASE'">PUBLICAR RELEASE</span>
                </button>
            </div>

            <!-- OTA Status Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div class="glass-card p-8 rounded-xl relative overflow-hidden group">
                    <p class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-widest mb-1">Versão de Produção</p>
                    <p class="text-headline-md font-bold text-primary font-mono"><?= $current_version ?></p>
                    <p class="text-[10px] text-secondary font-bold mt-2 italic">● Sincronizado com Main</p>
                </div>
                <div class="glass-card p-8 rounded-xl relative overflow-hidden group">
                    <p class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-widest mb-1">Última Atualização</p>
                    <p class="text-headline-md font-bold text-on-surface"><?= $last_update ?></p>
                </div>
                <div class="glass-card p-8 rounded-xl relative overflow-hidden group">
                    <p class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-widest mb-1">Nodes Monitorados</p>
                    <p class="text-headline-md font-bold text-on-surface"><?= $active_nodes ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Release History -->
                <div class="lg:col-span-2 glass-card rounded-xl overflow-hidden">
                    <div class="p-8 border-b border-outline-variant/10">
                        <h3 class="text-title-sm font-bold text-on-surface">Histórico de Releases</h3>
                    </div>
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-outline-variant/10">
                            <tr class="hover:bg-surface-variant/5 transition-colors">
                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-4">
                                        <div class="size-10 bg-surface-container-highest rounded-lg flex items-center justify-center text-primary font-mono font-bold"><?= htmlspecialchars($manifest['version'] ?? 'N/A') ?></div>
                                        <div>
                                            <p class="text-sm font-bold text-white"><?= htmlspecialchars(($manifest['notes'] ?? '') ? 'Release Atual' : 'Release Atual') ?></p>
                                            <p class="text-[10px] text-on-surface-variant opacity-60"><?= htmlspecialchars($manifest['notes'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-6 text-right">
                                    <span class="text-[10px] bg-secondary-container/20 text-secondary px-3 py-1 rounded-full font-bold uppercase tracking-widest">Stable</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- OTA Toolbox -->
                <div class="glass-card p-8 rounded-xl">
                    <h3 class="text-title-sm font-bold text-on-surface mb-6 italic">Orchestrator Tools</h3>
                    <div class="space-y-4">
                        <button class="w-full py-4 bg-surface-container border border-outline-variant/20 rounded-xl text-on-surface font-bold text-xs flex items-center justify-between px-6 hover:border-primary/50 transition-all">
                            <span>Hardening Audit</span>
                            <span class="material-symbols-outlined text-emerald-500">verified</span>
                        </button>
                        <button 
                            @click="executeAction('gerar_instalador')"
                            :disabled="loading"
                            class="w-full py-4 bg-surface-container border border-outline-variant/20 rounded-xl text-on-surface font-bold text-xs flex items-center justify-between px-6 hover:border-primary/50 transition-all disabled:opacity-50">
                            <span x-text="loading ? 'GERANDO...' : 'Geração de Instalador'">Geração de Instalador</span>
                            <span class="material-symbols-outlined text-primary text-sm" :class="loading ? 'animate-spin' : ''" x-text="loading ? 'sync' : 'build'">build</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'templates/footer.php'; ?>
