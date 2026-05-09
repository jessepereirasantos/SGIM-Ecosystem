<?php
$current_page = $current_page ?? '';
?>
<!-- Side Navigation Shell (Fiel ao Modelo Stitch) -->
<aside class="fixed left-0 top-0 h-screen w-[280px] bg-surface border-r border-outline-variant/20 flex flex-col py-8 z-50">
    <div class="px-6 mb-12">
        <h1 class="font-headline-md text-headline-md font-bold text-primary tracking-tight italic">SGIM <span class="text-on-surface">Vendas</span></h1>
        <p class="text-on-surface-variant font-body-sm opacity-60 uppercase tracking-widest text-[9px]">Enterprise Dashboard</p>
    </div>

    <nav class="flex-1 space-y-2 overflow-y-auto px-4">
        <div class="mb-6">
            <span class="px-4 text-label-caps font-label-caps text-on-surface-variant/50 block mb-2 uppercase text-[10px]">Visão de Negócio</span>
            
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= ($current_page == 'dashboard') ? 'sidebar-item-active text-primary font-bold border-r-2 border-primary' : 'text-on-surface-variant opacity-70 hover:bg-surface-variant/10 hover:text-on-surface' ?>">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="text-body-md">Panorama Geral</span>
            </a>

            <a href="clientes.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= ($current_page == 'clientes') ? 'sidebar-item-active text-primary font-bold border-r-2 border-primary' : 'text-on-surface-variant opacity-70 hover:bg-surface-variant/10 hover:text-on-surface' ?>">
                <span class="material-symbols-outlined">group</span>
                <span class="text-body-md">Gestão de Clientes</span>
            </a>

            <a href="licencas.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= ($current_page == 'licencas') ? 'sidebar-item-active text-primary font-bold border-r-2 border-primary' : 'text-on-surface-variant opacity-70 hover:bg-surface-variant/10 hover:text-on-surface' ?>">
                <span class="material-symbols-outlined">vpn_key</span>
                <span class="text-body-md">Licenças & Nodes</span>
            </a>

            <a href="ota.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= ($current_page == 'ota') ? 'sidebar-item-active text-primary font-bold border-r-2 border-primary' : 'text-on-surface-variant opacity-70 hover:bg-surface-variant/10 hover:text-on-surface' ?>">
                <span class="material-symbols-outlined">cloud_sync</span>
                <span class="text-body-md">Engenharia OTA</span>
            </a>
        </div>

        <div class="mb-6">
            <span class="px-4 text-label-caps font-label-caps text-on-surface-variant/50 block mb-2 uppercase text-[10px]">Financeiro</span>
            
            <a href="pedidos.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= ($current_page == 'pedidos') ? 'sidebar-item-active text-primary font-bold border-r-2 border-primary' : 'text-on-surface-variant opacity-70 hover:bg-surface-variant/10 hover:text-on-surface' ?>">
                <span class="material-symbols-outlined">receipt_long</span>
                <span class="text-body-md">Histórico de Vendas</span>
            </a>

            <a href="cupons.php" class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= ($current_page == 'cupons') ? 'sidebar-item-active text-primary font-bold border-r-2 border-primary' : 'text-on-surface-variant opacity-70 hover:bg-surface-variant/10 hover:text-on-surface' ?>">
                <span class="material-symbols-outlined">confirmation_number</span>
                <span class="text-body-md">Cupons & Descontos</span>
            </a>
        </div>
    </nav>

    <div class="px-4 mt-auto pt-8 border-t border-outline-variant/10">
        <div class="bg-primary-container/10 p-4 rounded-xl mb-6">
            <p class="text-body-sm text-primary font-bold mb-1">Upgrade Plan</p>
            <p class="text-[10px] text-on-surface-variant opacity-70 leading-tight">Desbloqueie relatórios de IA avançados.</p>
        </div>
        
        <a href="logout.php" class="flex items-center gap-3 px-4 py-2 rounded-lg text-on-surface-variant opacity-70 hover:text-error transition-all">
            <span class="material-symbols-outlined">logout</span>
            <span class="text-body-sm">Sair</span>
        </a>
    </div>
</aside>
