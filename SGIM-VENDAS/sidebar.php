<?php
$current_page = $current_page ?? '';
?>
<!-- Sidebar Moderno SaaS SGIM -->
<aside class="w-72 fixed inset-y-0 left-0 bg-[#0A0A0A] border-r border-zinc-900 flex flex-col z-50 transition-all duration-300">
    <div class="p-8 flex flex-col items-center border-b border-zinc-900/50">
        <div class="size-14 bg-gradient-to-br from-amber-400 to-amber-600 rounded-2xl flex items-center justify-center text-black shadow-lg shadow-amber-500/10 mb-4">
            <span class="material-symbols-outlined text-3xl font-black">admin_panel_settings</span>
        </div>
        <h1 class="text-xl font-black text-white tracking-tighter italic">SGIM <span class="text-amber-500">VENDAS</span></h1>
        <p class="text-[9px] text-zinc-600 uppercase tracking-[4px] font-bold mt-1">Management Hub</p>
    </div>

    <nav class="flex-1 overflow-y-auto p-6 space-y-8">
        <!-- Grupo: Gestão -->
        <div>
            <h3 class="px-4 text-[10px] font-bold text-zinc-600 uppercase tracking-widest mb-4">Visão de Negócio</h3>
            <ul class="space-y-1.5">
                <li>
                    <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'dashboard') ? 'bg-amber-500 text-black font-bold' : 'text-zinc-400 hover:bg-white/5 hover:text-white' ?>">
                        <span class="material-symbols-outlined text-[20px]">dashboard</span>
                        <span class="text-sm">Panorama Geral</span>
                    </a>
                </li>
                <li>
                    <a href="clientes.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'clientes') ? 'bg-amber-500 text-black font-bold' : 'text-zinc-400 hover:bg-white/5 hover:text-white' ?>">
                        <span class="material-symbols-outlined text-[20px]">group</span>
                        <span class="text-sm">Gestão de Clientes</span>
                    </a>
                </li>
                <li>
                    <a href="licencas.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'licencas') ? 'bg-amber-500 text-black font-bold' : 'text-zinc-400 hover:bg-white/5 hover:text-white' ?>">
                        <span class="material-symbols-outlined text-[20px]">vpn_key</span>
                        <span class="text-sm">Licenças & Ativação</span>
                    </a>
                </li>
                <li>
                    <a href="relatorios.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'relatorios') ? 'bg-amber-500 text-black font-bold' : 'text-zinc-400 hover:bg-white/5 hover:text-white' ?>">
                        <span class="material-symbols-outlined text-[20px]">analytics</span>
                        <span class="text-sm">Relatórios & Auditoria</span>
                    </a>
                </li>
                <li>
                    <a href="ota.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'ota') ? 'bg-amber-500 text-black font-bold' : 'text-zinc-400 hover:bg-white/5 hover:text-white' ?>">
                        <span class="material-symbols-outlined text-[20px]">cloud_sync</span>
                        <span class="text-sm">Engenharia OTA</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Grupo: Financeiro -->
        <div>
            <h3 class="px-4 text-[10px] font-bold text-zinc-600 uppercase tracking-widest mb-4">Financeiro</h3>
            <ul class="space-y-1.5">
                <li>
                    <a href="pedidos.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'pedidos') ? 'bg-amber-500 text-black font-bold' : 'text-zinc-400 hover:bg-white/5 hover:text-white' ?>">
                        <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                        <span class="text-sm">Histórico de Vendas</span>
                    </a>
                </li>
                <li>
                    <a href="cupons.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all <?= ($current_page == 'cupons') ? 'bg-amber-500 text-black font-bold' : 'text-zinc-400 hover:bg-white/5 hover:text-white' ?>">
                        <span class="material-symbols-outlined text-[20px]">confirmation_number</span>
                        <span class="text-sm">Cupons & Descontos</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="p-6 border-t border-zinc-900/50">
        <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-zinc-500 hover:text-red-500 hover:bg-red-500/5 transition-all text-sm font-bold">
            <span class="material-symbols-outlined">logout</span>
            Sair do Painel
        </a>
    </div>
</aside>