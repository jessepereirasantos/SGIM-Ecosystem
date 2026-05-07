<?php
$key = $_GET['key'] ?? 'ERRO-LICENCA';
?>
<!-- Success Content -->
<div class="flex-1 flex flex-col items-center justify-center py-12 max-w-4xl mx-auto w-full">
    <div class="relative mb-8">
        <div class="size-32 bg-brand rounded-full flex items-center justify-center shadow-[0_0_50px_rgba(255,193,7,0.3)]">
            <span class="material-symbols-outlined text-black text-6xl font-bold">check_circle</span>
        </div>
    </div>
    
    <div class="text-center mb-10 space-y-3">
        <h1 class="text-4xl md:text-5xl font-bold text-white tracking-tight">Pagamento Confirmado!</h1>
        <p class="text-lg text-slate-400">Sua chave do SGIM já está liberada para uso.</p>
    </div>

    <!-- Chave Card -->
    <div class="bg-surface-dark border border-brand/20 rounded-xl p-8 shadow-xl w-full max-w-md text-center">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-brand mb-4">Sua Chave de Ativação</h3>
        <div class="bg-black/40 border border-white/5 p-4 rounded-lg mb-6">
            <span class="text-3xl font-mono font-black text-white tracking-widest"><?= htmlspecialchars($key) ?></span>
        </div>
        <p class="text-xs text-gray-500 mb-6">Copie e cole este código na tela de ativação do seu SGIM-CLIENTE.</p>
        
        <div class="flex flex-col gap-3">
            <button onclick="navigator.clipboard.writeText('<?= $key ?>').then(() => alert('Chave copiada!'))" class="w-full h-12 bg-brand hover:bg-brand-dark text-black font-bold rounded-lg transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">content_copy</span>
                Copiar Chave
            </button>
            <a href="index.php?page=dashboard" class="w-full h-12 border border-white/10 hover:bg-white/5 text-white font-bold rounded-lg transition-all flex items-center justify-center gap-2">
                 Ir para o Dashboard
            </a>
        </div>
    </div>
</div>
