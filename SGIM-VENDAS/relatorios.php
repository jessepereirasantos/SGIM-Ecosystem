<!-- Header Section -->
<header class="mb-10">
    <h1 class="text-4xl font-black text-white tracking-tight">Relatórios</h1>
    <p class="text-slate-500 dark:text-slate-400 text-lg">Acompanhe o desempenho do seu negócio em tempo real</p>
</header>

<!-- Dashboard Cards Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
    <!-- Sales by Period Card -->
    <div class="bg-surface-dark p-6 rounded-xl border border-white/5 flex flex-col gap-4 shadow-xl">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-slate-400 uppercase tracking-wider">Vendas Totais (Mês)</p>
                <?php
                $stmt = $pdo->query("SELECT SUM(valor) as total FROM vendas WHERE status = 'approved' AND MONTH(data_venda) = MONTH(CURRENT_DATE)");
                $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                ?>
                <h3 class="text-3xl font-bold mt-1">R$ <?= number_format($total, 2, ',', '.') ?></h3>
            </div>
            <div class="text-green-500 flex items-center gap-1 bg-green-500/10 px-2 py-1 rounded-lg text-xs font-bold">
                <span class="material-symbols-outlined text-sm">trending_up</span>
                +100%
            </div>
        </div>
        <!-- Simplified Chart -->
        <div class="h-40 w-full mt-4 flex items-end gap-1">
             <div class="flex-1 bg-brand/20 h-10 rounded-t border-t border-brand/40"></div>
             <div class="flex-1 bg-brand/30 h-24 rounded-t border-t border-brand/50"></div>
             <div class="flex-1 bg-brand/40 h-16 rounded-t border-t border-brand/60"></div>
             <div class="flex-1 bg-brand h-32 rounded-t"></div>
        </div>
        <div class="flex justify-between text-[10px] text-slate-500 font-bold uppercase mt-2">
            <span>Sem 1</span><span>Sem 2</span><span>Sem 3</span><span>Sem 4</span>
        </div>
    </div>

    <!-- Growth -->
    <div class="bg-surface-dark p-6 rounded-xl border border-white/5 flex flex-col gap-4 shadow-xl">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-slate-400 uppercase tracking-wider">Novos Clientes</p>
                <?php $cnt = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn(); ?>
                <h3 class="text-3xl font-bold mt-1"><?= $cnt ?> Churchs</h3>
            </div>
            <div class="text-brand flex items-center gap-1 bg-brand/10 px-2 py-1 rounded-lg text-xs font-bold">
                <span class="material-symbols-outlined text-sm">group</span>
                Ativos
            </div>
        </div>
        <div class="h-40 w-full mt-4 flex items-center justify-center">
             <div class="size-32 rounded-full border-[10px] border-brand/10 border-t-brand flex items-center justify-center">
                 <span class="text-2xl font-black"><?= $cnt ?></span>
             </div>
        </div>
    </div>

    <!-- Export -->
    <div class="bg-surface-dark p-6 rounded-xl border border-white/5 flex flex-col gap-4 shadow-xl">
        <h3 class="font-bold text-lg text-white mb-2">Exportar Dados</h3>
        <p class="text-sm text-gray-500 mb-4">Gere arquivos CSV ou PDF para análise externa.</p>
        <button class="w-full py-4 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl font-bold flex items-center justify-center gap-3 transition-all">
             <span class="material-symbols-outlined">description</span>
             Relatório de Vendas (PDF)
        </button>
        <button class="w-full py-4 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl font-bold flex items-center justify-center gap-3 transition-all">
             <span class="material-symbols-outlined">table_view</span>
             Relatório de Clientes (CSV)
        </button>
    </div>
</div>
