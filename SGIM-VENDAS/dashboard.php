<?php
require_once 'config/database.php';
$current_page = 'dashboard';

// 📊 Coleta de Dados para a Dashboard
try {
    $total_clientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn() ?: 0;
    try {
        $vendas_mes = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE MONTH(created_at) = MONTH(CURRENT_DATE())")->fetchColumn() ?: 0;
    } catch (Exception $e) { $vendas_mes = 0; }
    try {
        $receita_total = $pdo->query("SELECT SUM(valor) FROM pedidos WHERE status = 'pago'")->fetchColumn() ?: 0;
    } catch (Exception $e) { $receita_total = 0; }
    $ultimos_clientes = $pdo->query("SELECT * FROM clientes ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log("Erro Dashboard: " . $e->getMessage()); }

include 'templates/header.php';
?>

<div class="flex">
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Shell -->
    <main class="ml-[280px] min-h-screen flex-1">
        <!-- Top Navigation -->
        <header class="h-16 flex items-center justify-between px-8 bg-surface/80 backdrop-blur-md sticky top-0 z-40 border-b border-outline-variant/10">
            <div class="flex items-center gap-4 bg-surface-container-low px-4 py-2 rounded-lg w-full max-w-md border border-outline-variant/20">
                <span class="material-symbols-outlined text-on-surface-variant">search</span>
                <input class="bg-transparent border-none focus:ring-0 text-body-sm w-full placeholder:text-on-surface-variant/40 outline-none text-white" placeholder="Buscar clientes ou licenças..." type="text"/>
            </div>
            <div class="flex items-center gap-6">
                <div class="flex gap-4">
                    <button class="relative p-2 rounded-lg hover:bg-surface-variant/20 transition-all text-on-surface-variant">
                        <span class="material-symbols-outlined">notifications</span>
                        <span class="absolute top-2 right-2 w-2 h-2 bg-secondary rounded-full"></span>
                    </button>
                </div>
                <div class="h-8 w-[1px] bg-outline-variant/20"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-body-sm font-bold text-on-surface leading-tight">Admin SGIM</p>
                        <p class="text-[10px] uppercase tracking-widest text-primary font-bold">Administrador</p>
                    </div>
                    <div class="size-10 bg-surface-container-highest border border-primary/20 rounded-lg flex items-center justify-center text-primary font-black shadow-lg">A</div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="p-10 max-w-[1600px] mx-auto">
            <!-- Header Section -->
            <div class="flex justify-between items-end mb-10">
                <div>
                    <h2 class="text-display-lg font-bold text-on-surface tracking-tighter">Panorama Geral</h2>
                    <p class="text-on-surface-variant font-body-md opacity-60">Bem-vindo de volta. Aqui estão as métricas de desempenho da SGIM Vendas.</p>
                </div>
                <div class="flex gap-3">
                    <button class="px-5 py-2.5 rounded-lg border border-outline-variant/20 text-on-surface font-semibold hover:bg-surface-variant/10 transition-all flex items-center gap-2 text-sm">
                        <span class="material-symbols-outlined text-sm">calendar_today</span>
                        Últimos 30 dias
                    </button>
                    <button class="px-5 py-2.5 rounded-lg bg-primary text-on-primary font-bold hover:opacity-90 transition-all flex items-center gap-2 text-sm shadow-xl shadow-primary/20">
                        <span class="material-symbols-outlined text-sm">add</span>
                        Novo Cliente
                    </button>
                </div>
            </div>

            <!-- KPI Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-10">
                <!-- Card 1: Clientes -->
                <div class="glass-card p-8 rounded-xl relative overflow-hidden group">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-3 rounded-lg bg-primary/10 text-primary">
                            <span class="material-symbols-outlined">person_add</span>
                        </div>
                        <div class="flex items-center gap-1 text-secondary">
                            <span class="material-symbols-outlined text-sm">trending_up</span>
                            <span class="text-xs font-bold">+12%</span>
                        </div>
                    </div>
                    <p class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-widest mb-1">Clientes Ativos</p>
                    <p class="text-headline-md font-bold text-on-surface"><?= $total_clientes ?></p>
                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <span class="material-symbols-outlined text-[100px]">group</span>
                    </div>
                </div>

                <!-- Card 2: Licenças -->
                <div class="glass-card p-8 rounded-xl relative overflow-hidden group">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-3 rounded-lg bg-tertiary-container/20 text-tertiary">
                            <span class="material-symbols-outlined">vpn_key</span>
                        </div>
                        <div class="flex items-center gap-1 text-secondary">
                            <span class="material-symbols-outlined text-sm">trending_up</span>
                            <span class="text-xs font-bold">Stable</span>
                        </div>
                    </div>
                    <p class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-widest mb-1">Nodes Ativos</p>
                    <p class="text-headline-md font-bold text-on-surface">1</p>
                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <span class="material-symbols-outlined text-[100px]">workspace_premium</span>
                    </div>
                </div>

                <!-- Card 3: Pedidos -->
                <div class="glass-card p-8 rounded-xl relative overflow-hidden group">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-3 rounded-lg bg-secondary-container/20 text-secondary">
                            <span class="material-symbols-outlined">shopping_cart</span>
                        </div>
                        <div class="flex items-center gap-1 text-secondary">
                            <span class="material-symbols-outlined text-sm">trending_up</span>
                            <span class="text-xs font-bold"><?= $vendas_mes ?></span>
                        </div>
                    </div>
                    <p class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-widest mb-1">Vendas (Mês)</p>
                    <p class="text-headline-md font-bold text-on-surface"><?= $vendas_mes ?></p>
                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <span class="material-symbols-outlined text-[100px]">receipt_long</span>
                    </div>
                </div>

                <!-- Card 4: Receita -->
                <div class="glass-card p-8 rounded-xl relative overflow-hidden group border-primary/20">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-3 rounded-lg bg-primary text-on-primary shadow-xl shadow-primary/20">
                            <span class="material-symbols-outlined">account_balance_wallet</span>
                        </div>
                    </div>
                    <p class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-widest mb-1">Receita Mensal</p>
                    <p class="text-headline-md font-bold text-on-surface">R$ <?= number_format($receita_total, 2, ',', '.') ?></p>
                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <span class="material-symbols-outlined text-[100px]">payments</span>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Latest Customers Table -->
                <div class="lg:col-span-2 glass-card rounded-xl overflow-hidden">
                    <div class="p-8 border-b border-outline-variant/10 flex justify-between items-center">
                        <div>
                            <h3 class="text-title-sm font-bold text-on-surface">Últimos Clientes Cadastrados</h3>
                            <p class="text-body-sm text-on-surface-variant opacity-60">Visualização detalhada dos novos parceiros comerciais.</p>
                        </div>
                        <a href="clientes.php" class="text-primary font-bold text-sm hover:underline">Ver Todos</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-surface-container-low/50 text-[10px] uppercase tracking-widest text-on-surface-variant/50">
                                    <th class="px-8 py-4 font-bold">Empresa / Cliente</th>
                                    <th class="px-8 py-4 font-bold">Domínio</th>
                                    <th class="px-8 py-4 font-bold">Data</th>
                                    <th class="px-8 py-4 font-bold text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/10">
                                <?php foreach ($ultimos_clientes as $c): ?>
                                <tr class="hover:bg-surface-variant/5 transition-colors">
                                    <td class="px-8 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-surface-container-highest flex items-center justify-center font-bold text-primary"><?= strtoupper(substr($c['nome'],0,2)) ?></div>
                                            <div>
                                                <p class="text-body-md font-bold text-on-surface leading-tight"><?= htmlspecialchars($c['nome']) ?></p>
                                                <p class="text-xs text-on-surface-variant opacity-60"><?= htmlspecialchars($c['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <span class="px-3 py-1 rounded-full bg-tertiary-container/10 text-tertiary text-[10px] font-bold uppercase tracking-wider"><?= htmlspecialchars($c['dominio'] ?? 'pendente') ?></span>
                                    </td>
                                    <td class="px-8 py-5 text-body-sm text-on-surface-variant font-mono"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                                    <td class="px-8 py-5 text-right">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-secondary-container/20 text-secondary text-[10px] font-bold uppercase">
                                            <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
                                            Ativo
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Insights Column -->
                <div class="flex flex-col gap-8">
                    <!-- Distribution Chart (Visual) -->
                    <div class="glass-card p-8 rounded-xl">
                        <h3 class="text-title-sm font-bold text-on-surface mb-6">Distribuição de Receita</h3>
                        <div class="space-y-6">
                            <div>
                                <div class="flex justify-between text-body-sm mb-2">
                                    <span class="text-on-surface-variant">Licenças SaaS</span>
                                    <span class="text-on-surface font-bold">100%</span>
                                </div>
                                <div class="h-1.5 w-full bg-surface-container-highest rounded-full overflow-hidden">
                                    <div class="h-full bg-primary rounded-full" style="width: 100%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Target Card -->
                    <div class="glass-card p-8 rounded-xl bg-gradient-to-br from-primary/10 to-transparent border-primary/20">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-title-sm font-bold text-on-surface">Meta Semanal</h3>
                            <span class="text-[10px] font-black text-primary px-2 py-1 rounded bg-primary/20 uppercase tracking-widest">Ativa</span>
                        </div>
                        <div class="text-center py-6">
                            <p class="text-[48px] font-black text-on-surface tracking-tighter">100%</p>
                            <p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest opacity-60">Operação em Escala</p>
                        </div>
                        <button class="w-full py-3 rounded-lg bg-primary text-on-primary font-black hover:opacity-90 transition-all text-xs">
                            VER RELATÓRIO IA
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'templates/footer.php'; ?>
