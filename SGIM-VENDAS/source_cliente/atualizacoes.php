<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title   = 'SGIM - Central de Atualizações';
$current_page = 'atualizacoes';

require_once __DIR__ . '/includes/header.php';

// ── Versão local (banco de dados) ────────────────────────────────────────────
$currentVersion = '0.0.0';
try {
    if ($pdo) {
        $s = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'");
        $v = $s ? $s->fetchColumn() : false;
        if ($v) $currentVersion = $v;
    }
} catch (Throwable $e) {}

// ── Master URL ────────────────────────────────────────────────────────────────
$masterUrl = 'https://escolateologicaeloha.com.br';
?>

<div class="space-y-8 animate-fade-in">
    <!-- Header de Contexto -->
    <div class="flex items-center justify-between bg-darkcard p-6 rounded-2xl border border-darkborder shadow-xl">
        <div class="flex items-center gap-4">
            <div class="size-14 bg-brand/10 rounded-2xl flex items-center justify-center text-brand">
                <span class="material-symbols-outlined text-3xl">system_update</span>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-white">SGIM - Central de Atualizações</h2>
                <p class="text-xs text-gray-500">Bem-vindo ao SGIM, Administrador.</p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-1">STATUS:</p>
            <div class="flex items-center gap-2 text-yellow-500 font-bold text-sm">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-yellow-500"></span>
                </span>
                ATUALIZAÇÃO DISPONÍVEL
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Card: Versão Atual -->
        <div class="bg-darkcard border border-darkborder rounded-3xl p-8 overflow-hidden relative group">
            <div class="absolute -right-10 -top-10 size-40 bg-brand/5 rounded-full blur-3xl group-hover:bg-brand/10 transition-all"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-8">
                    <h3 class="text-gray-400 font-bold text-sm uppercase tracking-widest">Versões Instaladas</h3>
                    <span class="px-3 py-1 bg-brand/10 text-brand text-[10px] font-black rounded-full border border-brand/20">NOVA VERSÃO DETECTADA</span>
                </div>
                
                <div class="flex items-center justify-between gap-12">
                    <div class="space-y-1">
                        <p class="text-[10px] text-gray-500 font-bold uppercase">Versão Local (Instalada)</p>
                        <p class="text-5xl font-black text-white tracking-tighter"><?= $currentVersion ?></p>
                    </div>
                    <div class="h-16 w-[1px] bg-darkborder"></div>
                    <div class="space-y-1">
                        <p class="text-[10px] text-gray-500 font-bold uppercase">Versão Disponível (Master)</p>
                        <p class="text-5xl font-black text-brand tracking-tighter" x-text="otaVersion || '...'"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card: Notas da Versão -->
        <div class="bg-darkcard border border-darkborder rounded-3xl p-8 flex flex-col justify-between">
            <div>
                <h3 class="text-gray-400 font-bold text-sm uppercase tracking-widest mb-6">Novidades da Versão <span x-text="otaVersion"></span></h3>
                <div class="p-4 bg-[#050505] border border-darkborder rounded-2xl min-h-[100px]">
                    <p class="text-sm text-gray-300 leading-relaxed italic" x-text="otaNotes || 'Consultando melhorias...'"></p>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-[10px] text-gray-500 italic">Pacote: <span class="truncate opacity-50" x-text="'<?= $masterUrl ?>/api/update/packages/SGIM-CLIENTE-v' + otaVersion + '.zip'"></span></p>
            </div>
        </div>
    </div>

    <!-- Seção de Ação Principal -->
    <div class="bg-darkcard border border-darkborder rounded-3xl p-10 text-center relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-brand/5 to-transparent opacity-50"></div>
        <div class="relative z-10 max-w-2xl mx-auto space-y-6">
            <h2 class="text-3xl font-black text-white">Instalar Atualização</h2>
            <p class="text-gray-400 text-sm">O sistema será atualizado de <span class="text-white font-bold"><?= $currentVersion ?></span> para <span class="text-brand font-bold" x-text="otaVersion"></span> automaticamente. Seus dados e configurações serão preservados durante o processo.</p>
            
            <div class="pt-6 flex flex-col items-center gap-4">
                <button 
                    @click="iniciarAtualizacao()"
                    :disabled="atualizando"
                    class="group relative inline-flex items-center gap-3 px-10 py-5 bg-brand text-black font-black rounded-2xl hover:scale-105 transition-all shadow-2xl shadow-brand/20 disabled:opacity-50 disabled:scale-100"
                >
                    <span class="material-symbols-outlined" :class="atualizando ? 'animate-spin' : ''">
                        <template x-if="!atualizando">system_update</template>
                        <template x-if="atualizando">sync</template>
                    </span>
                    <span class="uppercase tracking-widest text-sm" x-text="atualizando ? 'Processando...' : 'ATUALIZAR AGORA'"></span>
                </button>
                <p class="text-[10px] text-gray-600 font-bold uppercase tracking-widest">Tempo estimado: ~2 minutos</p>
            </div>
        </div>
    </div>

    <!-- Overlay de Progresso (Modal) -->
    <div 
        x-show="atualizando" 
        x-transition
        class="fixed inset-0 z-[100] flex items-center justify-center p-6"
    >
        <div class="absolute inset-0 bg-[#050505]/95 backdrop-blur-xl"></div>
        
        <div class="relative z-10 w-full max-w-md bg-darkcard border border-darkborder rounded-3xl p-10 shadow-3xl text-center space-y-8">
            <div class="flex flex-col items-center gap-4">
                <div class="size-20 bg-brand/10 rounded-full flex items-center justify-center text-brand relative">
                    <span class="material-symbols-outlined text-4xl animate-spin">sync</span>
                    <div class="absolute inset-0 border-4 border-brand border-t-transparent rounded-full animate-spin"></div>
                </div>
                <h3 class="text-xl font-bold text-white">Atualizando Sistema</h3>
                <p class="text-sm text-gray-400" x-text="etapaAtual"></p>
            </div>

            <!-- Barra de Progresso Visual -->
            <div class="space-y-2">
                <div class="h-3 w-full bg-[#050505] rounded-full border border-darkborder overflow-hidden">
                    <div 
                        class="h-full bg-brand shadow-[0_0_15px_rgba(255,200,128,0.5)] transition-all duration-500" 
                        :style="'width: ' + progresso + '%'"
                    ></div>
                </div>
                <div class="flex justify-between text-[10px] font-black text-gray-500 uppercase tracking-widest">
                    <span>Início</span>
                    <span x-text="progresso + '%'"></span>
                    <span>Concluído</span>
                </div>
            </div>

            <p class="text-[10px] text-yellow-500/70 font-bold uppercase tracking-tight leading-relaxed animate-pulse">
                ⚠️ NÃO FECHE ESTA PÁGINA NEM DESLIGUE O SERVIDOR.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
