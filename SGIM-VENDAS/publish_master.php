<?php
/**
 * SGIM MASTER - PUBLICADOR OTA (Módulo Desativado)
 */
$current_page = 'publish';
require_once 'templates/header.php';
?>

<div class="flex justify-between items-end mb-10">
    <div>
        <h2 class="text-display-lg font-display-lg text-on-surface mb-2">Publicador OTA</h2>
        <p class="text-on-surface-variant font-body-md opacity-80">Módulo em manutenção técnica.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2 glass-card p-12 rounded-2xl flex flex-col justify-center min-h-[450px] text-center">
        <div class="size-16 bg-on-surface-variant/5 rounded-2xl flex items-center justify-center text-on-surface-variant/20 mx-auto mb-8">
            <span class="material-symbols-outlined text-4xl">construction</span>
        </div>
        <h3 class="text-headline-md font-black text-white mb-4 italic tracking-tighter">Módulo Desativado</h3>
        <p class="text-on-surface-variant text-lg leading-relaxed max-w-md mx-auto">
            O sistema de distribuição de atualizações está passando por um processo de reset total e reconstrução industrial.
        </p>
    </div>

    <div class="glass-card p-10 rounded-2xl bg-surface-container flex flex-col justify-center text-center">
        <h4 class="text-title-sm font-bold text-white mb-4">Estado do Sistema</h4>
        <p class="text-[32px] font-black text-on-surface-variant opacity-20 italic leading-none mb-2">OFFLINE</p>
        <p class="text-[10px] uppercase tracking-widest text-zinc-500 font-bold">Aguardando Nova Arquitetura</p>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
