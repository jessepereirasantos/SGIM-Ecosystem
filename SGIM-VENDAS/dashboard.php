<?php
require_once 'config/database.php';
$current_page = 'dashboard';

// 📊 Coleta de Dados para a Dashboard
try {
    // Total de Clientes
    $total_clientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn() ?: 0;
    
    // Vendas do Mês (Simulação baseada na tabela pedidos)
    $vendas_mes = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE MONTH(data_criacao) = MONTH(CURRENT_DATE())")->fetchColumn() ?: 0;
    
    // Receita Total (Soma da coluna 'valor' da tabela pedidos onde status = 'pago')
    // Verificando se a coluna 'valor' existe, caso contrário usa fallback 0
    try {
        $receita_total = $pdo->query("SELECT SUM(valor) FROM pedidos WHERE status = 'pago'")->fetchColumn() ?: 0;
    } catch (Exception $e) { $receita_total = 0; }


    // Últimos 5 Clientes
    $ultimos_clientes = $pdo->query("SELECT * FROM clientes ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Erro ao carregar dados da dashboard: " . $e->getMessage());
}

include 'templates/header.php';
?>

<div class="flex">
    <?php include 'templates/sidebar.php'; ?>

    <main class="ml-72 flex-1 p-10 bg-[#050505] min-h-screen">
        <!-- Header da Página -->
        <div class="flex justify-between items-center mb-10">
            <div>
                <h2 class="text-3xl font-black text-white tracking-tighter">Panorama <span class="text-amber-500">Geral</span></h2>
                <p class="text-zinc-500 text-sm mt-1">Bem-vindo ao centro de comando do SGIM SaaS.</p>
            </div>
            <div class="bg-zinc-900/50 px-4 py-2 rounded-2xl border border-zinc-800 flex items-center gap-3">
                <div class="size-2 bg-emerald-500 rounded-full animate-pulse"></div>
                <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">Sistema Online</span>
            </div>
        </div>

        <!-- Cards de Indicadores -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <!-- Card: Clientes -->
            <div class="bg-zinc-900/30 border border-zinc-800 p-8 rounded-[32px] hover:border-amber-500/30 transition-all group">
                <div class="flex justify-between items-start mb-4">
                    <span class="material-symbols-outlined text-amber-500 text-3xl">group</span>
                    <span class="text-[10px] font-bold text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded-lg">+12%</span>
                </div>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mb-1">Total de Clientes</p>
                <h3 class="text-4xl font-black text-white"><?= $total_clientes ?></h3>
            </div>

            <!-- Card: Vendas Mes -->
            <div class="bg-zinc-900/30 border border-zinc-800 p-8 rounded-[32px] hover:border-amber-500/30 transition-all group">
                <div class="flex justify-between items-start mb-4">
                    <span class="material-symbols-outlined text-amber-500 text-3xl">shopping_cart</span>
                    <span class="text-[10px] font-bold text-amber-500 bg-amber-500/10 px-2 py-1 rounded-lg">Mensal</span>
                </div>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mb-1">Vendas no Mês</p>
                <h3 class="text-4xl font-black text-white"><?= $vendas_mes ?></h3>
            </div>

            <!-- Card: Receita -->
            <div class="bg-zinc-900/30 border border-zinc-800 p-8 rounded-[32px] hover:border-amber-500/30 transition-all group">
                <div class="flex justify-between items-start mb-4">
                    <span class="material-symbols-outlined text-amber-500 text-3xl">payments</span>
                </div>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mb-1">Receita Total</p>
                <h3 class="text-4xl font-black text-white">R$ <?= number_format($receita_total, 2, ',', '.') ?></h3>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Tabela: Últimos Clientes -->
            <div class="lg:col-span-2 bg-zinc-900/20 border border-zinc-900 rounded-[40px] p-8">
                <div class="flex justify-between items-center mb-8">
                    <h4 class="text-lg font-bold text-white tracking-tight">Novos Clientes <span class="text-zinc-600 text-sm ml-2">Recentemente Ativados</span></h4>
                    <a href="clientes.php" class="text-xs text-amber-500 font-bold hover:underline">Ver Todos</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] text-zinc-600 uppercase tracking-widest border-b border-zinc-800">
                                <th class="pb-4 font-bold">Cliente</th>
                                <th class="pb-4 font-bold">Domínio</th>
                                <th class="pb-4 font-bold">Status</th>
                                <th class="pb-4 font-bold">Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/50">
                            <?php foreach ($ultimos_clientes as $c): ?>
                            <tr class="group hover:bg-white/[0.01] transition-colors">
                                <td class="py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="size-9 bg-zinc-800 rounded-xl flex items-center justify-center text-xs font-bold text-zinc-400"><?= substr($c['nome'] ?? 'U', 0, 1) ?></div>
                                        <div>
                                            <p class="text-sm font-bold text-white"><?= htmlspecialchars($c['nome'] ?? 'Usuário Sem Nome') ?></p>
                                            <p class="text-[10px] text-zinc-500"><?= htmlspecialchars($c['email'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-5 text-xs text-zinc-400 font-mono"><?= htmlspecialchars($c['dominio'] ?? 'Não definido') ?></td>
                                <td class="py-5">
                                    <span class="px-2 py-1 bg-emerald-500/10 text-emerald-500 text-[10px] font-bold rounded-lg border border-emerald-500/20">Ativo</span>
                                </td>
                                <td class="py-5 text-xs text-zinc-600"><?= date('d/m/Y', strtotime($c['data_criacao'] ?? 'now')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card: Ativação Rápida -->
            <div class="bg-gradient-to-br from-amber-500 to-amber-700 rounded-[40px] p-10 flex flex-col justify-between shadow-2xl shadow-amber-500/10">
                <div>
                    <h4 class="text-2xl font-black text-black tracking-tighter mb-4 leading-none">Ativação <br>Assistida</h4>
                    <p class="text-black/60 text-sm font-medium leading-relaxed">Gere uma nova licença e prepare o sistema para um novo cliente em um único clique.</p>
                </div>
                <div class="mt-8 space-y-3">
                    <a href="licencas.php?acao=nova" class="w-full bg-black text-amber-500 font-black py-4 rounded-2xl flex items-center justify-center gap-3 hover:scale-[1.02] transition-all">
                        <span class="material-symbols-outlined">add_circle</span>
                        NOVA LICENÇA
                    </a>
                    <p class="text-center text-[9px] text-black/40 font-bold uppercase tracking-widest">Processo Automatizado</p>
                </div>
            </div>
        </div>

    </main>
</div>

<?php include 'templates/footer.php'; ?>
