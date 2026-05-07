<?php
/**
 * SGIM MASTER - DASHBOARD v7.6 (FIDELIDADE TOTAL AO DESIGN ORIGINAL)
 */
$current_page = 'dashboard';
require_once 'templates/header.php';

try {
    $total_clientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn() ?: 0;
    $licencas_ativas = $pdo->query("SELECT COUNT(*) FROM licencas WHERE status = 'ativa'")->fetchColumn() ?: 0;
    $vendas_mes = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE MONTH(data_venda) = MONTH(CURRENT_DATE()) AND YEAR(data_venda) = YEAR(CURRENT_DATE())")->fetchColumn() ?: 0;
    try {
        $receita_total = $pdo->query("SELECT SUM(valor) FROM pedidos WHERE status IN ('approved', 'pago', 'APROVADO')")->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $receita_total = 0;
    }
    $ultimos_clientes = $pdo->query("SELECT * FROM clientes ORDER BY id DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_sql = $e->getMessage();
}
?>

<!-- Header Section ORIGINAL -->
<div class="flex justify-between items-end mb-10">
    <div>
        <h2 class="text-display-lg font-display-lg text-on-surface mb-2">Panorama Geral</h2>
        <p class="text-on-surface-variant font-body-md opacity-80">Bem-vindo de volta. Aqui estão as métricas de
            desempenho da SGIM Vendas.</p>
    </div>
    <div class="flex gap-3">
        <button
            class="px-5 py-2.5 rounded-lg border border-outline-variant/20 text-on-surface font-title-sm hover:bg-surface-variant/10 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">calendar_today</span>
            Últimos 30 dias
        </button>
        <button
            class="px-5 py-2.5 rounded-lg bg-primary text-on-primary font-title-sm hover:opacity-90 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">add</span>
            Novo Relatório
        </button>
    </div>
</div>

<!-- KPI Cards Grid ORIGINAL -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-10">
    <!-- Card 1: Clientes -->
    <div class="glass-card p-8 rounded-xl relative overflow-hidden group">
        <div class="flex justify-between items-start mb-4">
            <div class="p-3 rounded-lg bg-primary/10 text-primary">
                <span class="material-symbols-outlined">person_add</span>
            </div>
            <div class="flex items-center gap-1 text-secondary">
                <span class="material-symbols-outlined text-sm">trending_up</span>
                <span class="text-xs font-bold">+12.5%</span>
            </div>
        </div>
        <p class="text-label-caps font-label-caps text-on-surface-variant/60 uppercase mb-1">Clientes Ativos</p>
        <p class="text-headline-md font-headline-md text-on-surface"><?= $total_clientes ?></p>
        <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
            <span class="material-symbols-outlined text-[120px]">group</span>
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
                <span class="text-xs font-bold">+8.2%</span>
            </div>
        </div>
        <p class="text-label-caps font-label-caps text-on-surface-variant/60 uppercase mb-1">Licenças Emitidas</p>
        <p class="text-headline-md font-headline-md text-on-surface"><?= $licencas_ativas ?></p>
        <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
            <span class="material-symbols-outlined text-[120px]">workspace_premium</span>
        </div>
    </div>
    <!-- Card 3: Pedidos -->
    <div class="glass-card p-8 rounded-xl relative overflow-hidden group">
        <div class="flex justify-between items-start mb-4">
            <div class="p-3 rounded-lg bg-secondary-container/20 text-secondary">
                <span class="material-symbols-outlined">shopping_cart</span>
            </div>
            <div class="flex items-center gap-1 text-error">
                <span class="material-symbols-outlined text-sm">trending_down</span>
                <span class="text-xs font-bold">-2.4%</span>
            </div>
        </div>
        <p class="text-label-caps font-label-caps text-on-surface-variant/60 uppercase mb-1">Pedidos (Mês)</p>
        <p class="text-headline-md font-headline-md text-on-surface"><?= $vendas_mes ?></p>
        <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
            <span class="material-symbols-outlined text-[120px]">receipt_long</span>
        </div>
    </div>
    <!-- Card 4: Receita -->
    <div class="glass-card p-8 rounded-xl relative overflow-hidden group border-primary/20">
        <div class="flex justify-between items-start mb-4">
            <div class="p-3 rounded-lg bg-primary text-on-primary shadow-[0_0_20px_rgba(245,166,35,0.2)]">
                <span class="material-symbols-outlined">account_balance_wallet</span>
            </div>
            <div class="flex items-center gap-1 text-secondary">
                <span class="material-symbols-outlined text-sm">trending_up</span>
                <span class="text-xs font-bold">+24.1%</span>
            </div>
        </div>
        <p class="text-label-caps font-label-caps text-on-surface-variant/60 uppercase mb-1">Receita Mensal</p>
        <p class="text-headline-md font-headline-md text-on-surface">R$
            <?= number_format($receita_total, 2, ',', '.') ?></p>
        <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
            <span class="material-symbols-outlined text-[120px]">payments</span>
        </div>
    </div>
</div>

<!-- Main Content Area ORIGINAL -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2 glass-card rounded-xl overflow-hidden">
        <div class="p-8 border-b border-outline-variant/10 flex justify-between items-center">
            <div>
                <h3 class="text-title-sm font-title-sm text-on-surface">Últimos Clientes Cadastrados</h3>
                <p class="text-body-sm text-on-surface-variant opacity-60">Visualização detalhada dos novos parceiros
                    comerciais.</p>
            </div>
            <a href="clientes.php" class="text-primary font-title-sm hover:underline">Ver Todos</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-surface-container-low/50">
                        <th class="px-8 py-4 text-label-caps font-label-caps text-on-surface-variant/50 uppercase">
                            Empresa / Cliente</th>
                        <th class="px-8 py-4 text-label-caps font-label-caps text-on-surface-variant/50 uppercase">
                            Segmento</th>
                        <th class="px-8 py-4 text-label-caps font-label-caps text-on-surface-variant/50 uppercase">Data
                        </th>
                        <th
                            class="px-8 py-4 text-label-caps font-label-caps text-on-surface-variant/50 uppercase text-right">
                            Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10">
                    <?php foreach ($ultimos_clientes as $c): ?>
                        <tr class="hover:bg-surface-variant/5 transition-colors">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-lg bg-surface-container-highest flex items-center justify-center font-bold text-primary">
                                        <?= substr($c['nome'], 0, 2) ?>
                                    </div>
                                    <div>
                                        <p class="text-body-md font-bold text-on-surface">
                                            <?= htmlspecialchars($c['nome']) ?></p>
                                        <p class="text-xs text-on-surface-variant">
                                            <?= htmlspecialchars($c['dominio'] ?? 'pendente.com') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <span
                                    class="px-3 py-1 rounded-full bg-tertiary-container/10 text-tertiary text-xs font-bold uppercase tracking-wider">Enterprise</span>
                            </td>
                            <td class="px-8 py-5 text-body-sm text-on-surface-variant">
                                <?= date('d M, Y', strtotime($c['data_cadastro'])) ?></td>
                            <td class="px-8 py-5 text-right">
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-secondary-container/20 text-secondary text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span> Ativo
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Secondary Column ORIGINAL -->
    <div class="flex flex-col gap-8">
        <div class="glass-card p-8 rounded-xl bg-gradient-to-br from-primary/10 to-transparent">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-title-sm font-title-sm text-on-surface">Meta de Vendas</h3>
                <span class="text-xs font-bold text-primary px-2 py-1 rounded bg-primary/20">Mês Atual</span>
            </div>
            <div class="text-center py-6">
                <p class="text-[40px] font-bold text-on-surface">82%</p>
                <p class="text-body-sm text-on-surface-variant opacity-70">R$
                    <?= number_format($receita_total, 2, ',', '.') ?> de R$ 480.000</p>
            </div>
            <button onclick="window.location.href='publish_master.php'"
                class="w-full py-3 rounded-lg bg-surface border border-primary/30 text-primary font-bold hover:bg-primary/5 transition-all">
                Publicador OTA
            </button>
        </div>

        <div class="glass-card p-10 rounded-xl bg-surface-container-high border-none flex flex-col justify-center">
            <div class="flex gap-4 mb-6">
                <div
                    class="w-12 h-12 rounded-xl bg-background flex items-center justify-center border border-outline-variant/10 text-primary">
                    <span class="material-symbols-outlined">rocket_launch</span>
                </div>
                <div
                    class="w-12 h-12 rounded-xl bg-background flex items-center justify-center border border-outline-variant/10 text-secondary">
                    <span class="material-symbols-outlined">hub</span>
                </div>
            </div>
            <h3 class="text-headline-md font-headline-md text-on-surface mb-4">Módulos de IA</h3>
            <p class="text-body-md text-on-surface-variant mb-6 italic">Monitorando padrões de venda reais.</p>
            <button
                class="w-full py-3 rounded-lg bg-on-surface text-background font-bold hover:bg-on-surface-variant transition-all">VER
                INSIGHTS</button>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>