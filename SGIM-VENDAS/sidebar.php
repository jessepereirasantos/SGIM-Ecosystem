<?php
$current_page = $current_page ?? '';
?>
<!-- Sidebar Premium SGIM Vendas -->
<aside class="w-72 fixed inset-y-0 left-0 bg-[#0A0A0A] border-r border-zinc-900/50 flex flex-col z-50 shadow-2xl">
    <div class="p-8 flex flex-col items-center">
        <div class="size-20 bg-amber-500 rounded-3xl flex items-center justify-center text-black shadow-2xl shadow-amber-500/20 mb-6 group transition-all hover:scale-105">
            <span class="material-symbols-outlined text-4xl font-black">shield_person</span>
        </div>
        <h1 class="text-xl font-black text-white tracking-tighter italic">SGIM <span class="text-amber-500 text-2xl">VENDAS</span></h1>
        <p class="text-[9px] text-zinc-600 uppercase tracking-[4px] font-bold mt-1">Management Hub</p>
    </div>

    <nav class="flex-1 overflow-y-auto px-6 py-4 space-y-8">
        <!-- Grupo: Negócio -->
        <div>
            <h3 class="px-4 text-[10px] font-bold text-zinc-700 uppercase tracking-widest mb-4">Visão de Negócio</h3>
            <ul class="space-y-2">
                <li>
                    <a href="dashboard.php" class="flex items-center gap-3 px-5 py-3.5 rounded-[20px] transition-all <?= ($current_page == 'dashboard') ? 'bg-amber-500 text-black shadow-lg shadow-amber-500/10 font-black' : 'text-zinc-500 hover:text-white hover:bg-white/5' ?>">
                        <span class="material-symbols-outlined text-[22px]">dashboard_customize</span>
                        <span class="text-[13px]">Panorama Geral</span>
                    </a>
                </li>
                <li>
                    <a href="clientes.php" class="flex items-center gap-3 px-5 py-3.5 rounded-[20px] transition-all <?= ($current_page == 'clientes') ? 'bg-amber-500 text-black shadow-lg shadow-amber-500/10 font-black' : 'text-zinc-500 hover:text-white hover:bg-white/5' ?>">
                        <span class="material-symbols-outlined text-[22px]">group</span>
                        <span class="text-[13px]">Gestão de Clientes</span>
                    </a>
                </li>
                <li>
                    <a href="licencas.php" class="flex items-center gap-3 px-5 py-3.5 rounded-[20px] transition-all <?= ($current_page == 'licencas') ? 'bg-amber-500 text-black shadow-lg shadow-amber-500/10 font-black' : 'text-zinc-500 hover:text-white hover:bg-white/5' ?>">
                        <span class="material-symbols-outlined text-[22px]">vpn_key</span>
                        <span class="text-[13px]">Licenças & Ativação</span>
                    </a>
                </li>
                <li>
                    <a href="relatorios.php" class="flex items-center gap-3 px-5 py-3.5 rounded-[20px] transition-all <?= ($current_page == 'relatorios') ? 'bg-amber-500 text-black shadow-lg shadow-amber-500/10 font-black' : 'text-zinc-500 hover:text-white hover:bg-white/5' ?>">
                        <span class="material-symbols-outlined text-[22px]">analytics</span>
                        <span class="text-[13px]">Relatórios & Auditoria</span>
                    </a>
                </li>
                <li>
                    <a href="ota.php" class="flex items-center gap-3 px-5 py-3.5 rounded-[20px] transition-all <?= ($current_page == 'ota') ? 'bg-amber-500 text-black shadow-lg shadow-amber-500/10 font-black' : 'text-zinc-500 hover:text-white hover:bg-white/5' ?>">
                        <span class="material-symbols-outlined text-[22px]">cloud_sync</span>
                        <span class="text-[13px]">Engenharia OTA</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Grupo: Financeiro -->
        <div>
            <h3 class="px-4 text-[10px] font-bold text-zinc-700 uppercase tracking-widest mb-4">Financeiro</h3>
            <ul class="space-y-2">
                <li>
                    <a href="pedidos.php" class="flex items-center gap-3 px-5 py-3.5 rounded-[20px] transition-all <?= ($current_page == 'pedidos') ? 'bg-amber-500 text-black shadow-lg shadow-amber-500/10 font-black' : 'text-zinc-500 hover:text-white hover:bg-white/5' ?>">
                        <span class="material-symbols-outlined text-[22px]">receipt_long</span>
                        <span class="text-[13px]">Histórico de Vendas</span>
                    </a>
                </li>
                <li>
                    <a href="cupons.php" class="flex items-center gap-3 px-5 py-3.5 rounded-[20px] transition-all <?= ($current_page == 'cupons') ? 'bg-amber-500 text-black shadow-lg shadow-amber-500/10 font-black' : 'text-zinc-500 hover:text-white hover:bg-white/5' ?>">
                        <span class="material-symbols-outlined text-[22px]">confirmation_number</span>
                        <span class="text-[13px]">Cupons & Descontos</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="p-8 border-t border-zinc-900/50">
        <a href="logout.php" class="flex items-center gap-3 px-5 py-4 rounded-[20px] text-zinc-600 hover:text-red-500 hover:bg-red-500/5 transition-all text-[13px] font-bold">
            <span class="material-symbols-outlined">logout</span>
            Sair do Painel
        </a>
    </div>
</aside>
