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

<div class="flex bg-black">
    <?php include 'sidebar.php'; ?>

    <main class="ml-72 flex-1 p-10 bg-black min-h-screen">
        <!-- Top Bar Busca -->
        <div class="mb-10 flex items-center gap-6">
            <div class="relative flex-1 group">
                <span class="material-symbols-outlined absolute left-6 top-1/2 -translate-y-1/2 text-zinc-600 group-focus-within:text-amber-500 transition-colors">search</span>
                <input type="text" placeholder="Buscar clientes ou licenças..." class="w-full bg-zinc-900/40 border border-zinc-900 rounded-[20px] py-4 pl-16 pr-6 text-zinc-400 focus:outline-none focus:border-amber-500/50 transition-all text-sm">
            </div>
            <div class="flex items-center gap-4 px-6 py-3">
                <div class="text-right">
                    <p class="text-[11px] font-black text-white leading-tight">Admin SGIM</p>
                    <p class="text-[9px] text-amber-500 uppercase font-bold tracking-widest">Production Stable</p>
                </div>
                <div class="size-10 bg-zinc-900 border border-zinc-800 rounded-xl flex items-center justify-center text-zinc-400 font-black">A</div>
            </div>
        </div>

        <div class="flex gap-8">
            <!-- Conteúdo Principal -->
            <div class="flex-1">
                <div class="flex justify-between items-center mb-10">
                    <div>
                        <h2 class="text-4xl font-black text-white tracking-tighter">Panorama <span class="text-amber-500">Geral</span></h2>
                        <p class="text-zinc-500 text-sm mt-1">Bem-vindo ao centro de comando do SGIM SaaS.</p>
                    </div>
                    <div class="flex items-center gap-3 px-4 py-2 bg-zinc-900/50 border border-zinc-800 rounded-full">
                        <div class="size-2 bg-emerald-500 rounded-full animate-pulse shadow-[0_0_10px_rgba(16,185,129,0.5)]"></div>
                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">Sistema Online</span>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-3 gap-6 mb-10">
                    <div class="bg-[#0A0A0A] border border-zinc-900/50 p-8 rounded-[40px] hover:border-amber-500/20 transition-all group relative overflow-hidden">
                        <div class="flex justify-between items-start mb-8">
                            <span class="material-symbols-outlined text-amber-500 text-3xl">group</span>
                            <span class="px-3 py-1 bg-emerald-500/10 text-emerald-500 rounded-full text-[10px] font-black">+12%</span>
                        </div>
                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-1">Total de Clientes</p>
                        <h3 class="text-4xl font-black text-white"><?= $total_clientes ?></h3>
                    </div>

                    <div class="bg-[#0A0A0A] border border-zinc-900/50 p-8 rounded-[40px] hover:border-amber-500/20 transition-all group relative overflow-hidden">
                        <div class="flex justify-between items-start mb-8">
                            <span class="material-symbols-outlined text-amber-500 text-3xl">shopping_cart</span>
                            <span class="px-3 py-1 bg-amber-500/10 text-amber-500 rounded-full text-[10px] font-black italic">Mensal</span>
                        </div>
                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-1">Vendas no Mês</p>
                        <h3 class="text-4xl font-black text-white"><?= $vendas_mes ?></h3>
                    </div>

                    <div class="bg-[#0A0A0A] border border-zinc-900/50 p-8 rounded-[40px] hover:border-amber-500/20 transition-all group relative overflow-hidden">
                        <div class="flex justify-between items-start mb-8">
                            <span class="material-symbols-outlined text-amber-500 text-3xl">payments</span>
                        </div>
                        <p class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mb-1">Receita Total</p>
                        <h3 class="text-4xl font-black text-white">R$ <?= number_format($receita_total, 2, ',', '.') ?></h3>
                    </div>
                </div>

                <!-- Tabela de Clientes Recentes -->
                <div class="bg-[#0A0A0A] border border-zinc-900/50 rounded-[40px] p-8">
                    <div class="flex justify-between items-center mb-8">
                        <h4 class="text-lg font-bold text-white">Novos Clientes <span class="text-zinc-600 font-normal ml-2 text-sm">Recentemente Ativados</span></h4>
                        <a href="clientes.php" class="text-amber-500 text-xs font-bold hover:underline">Ver Todos</a>
                    </div>
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] text-zinc-700 uppercase tracking-widest border-b border-zinc-900">
                                <th class="pb-4 font-bold">Cliente</th>
                                <th class="pb-4 font-bold">Domínio</th>
                                <th class="pb-4 font-bold">Status</th>
                                <th class="pb-4 font-bold">Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-900/50">
                            <?php foreach ($ultimos_clientes as $c): ?>
                            <tr class="group hover:bg-white/[0.01] transition-all">
                                <td class="py-5 flex items-center gap-4">
                                    <div class="size-10 bg-zinc-900 rounded-xl border border-zinc-800 flex items-center justify-center text-zinc-600 font-black"><?= strtoupper(substr($c['nome'],0,1)) ?></div>
                                    <div>
                                        <p class="text-sm font-bold text-white"><?= htmlspecialchars($c['nome']) ?></p>
                                        <p class="text-[10px] text-zinc-600"><?= htmlspecialchars($c['email']) ?></p>
                                    </div>
                                </td>
                                <td class="py-5 text-zinc-500 text-xs font-mono"><?= htmlspecialchars($c['dominio'] ?? 'Não definido') ?></td>
                                <td class="py-5">
                                    <span class="px-3 py-1 bg-emerald-500/10 text-emerald-500 text-[9px] font-black uppercase rounded-full">Ativo</span>
                                </td>
                                <td class="py-5 text-zinc-600 text-xs font-mono"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Coluna Lateral: Ações Rápidas -->
            <div class="w-80">
                <div class="bg-amber-500 rounded-[40px] p-8 text-black relative overflow-hidden group hover:scale-[1.02] transition-all cursor-pointer shadow-2xl shadow-amber-500/20">
                    <div class="relative z-10">
                        <h4 class="text-2xl font-black tracking-tighter leading-tight mb-4">Ativação<br>Assistida</h4>
                        <p class="text-sm font-bold leading-relaxed mb-8 opacity-80">Gere uma nova licença e prepare o sistema para um novo cliente em um único clique.</p>
                        <button class="w-full bg-black text-white font-black py-4 rounded-2xl text-xs flex items-center justify-center gap-2">
                            INICIAR PROCESSO
                            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </button>
                    </div>
                    <span class="material-symbols-outlined absolute -bottom-4 -right-4 text-[150px] opacity-10 rotate-12">key</span>
                </div>

                <div class="mt-6 bg-zinc-900/30 border border-zinc-900 p-8 rounded-[40px]">
                    <h5 class="text-zinc-500 text-[10px] font-black uppercase tracking-widest mb-6">Logs de Segurança</h5>
                    <div class="space-y-6">
                        <div class="flex gap-4">
                            <div class="size-2 bg-amber-500 rounded-full mt-1.5 shadow-[0_0_8px_rgba(245,158,11,0.5)]"></div>
                            <div>
                                <p class="text-xs text-white font-bold">Deploy Concluído</p>
                                <p class="text-[10px] text-zinc-600">v1.1.0 via Rsync Hub</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="size-2 bg-zinc-800 rounded-full mt-1.5"></div>
                            <div>
                                <p class="text-xs text-zinc-500">Backup Diário</p>
                                <p class="text-[10px] text-zinc-700">Concluído há 2h</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'templates/footer.php'; ?>
