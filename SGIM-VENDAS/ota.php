<?php
require_once 'config/database.php';
$current_page = 'ota';

// Busca de Releases
$releases = $pdo->query("SELECT * FROM ota_releases ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

include 'templates/header.php';
?>

<div class="flex">
    <?php include 'templates/sidebar.php'; ?>

    <main class="ml-72 flex-1 p-10 bg-[#050505] min-h-screen">
        <div class="flex justify-between items-end mb-10">
            <div>
                <h2 class="text-3xl font-black text-white tracking-tighter italic">Engenharia <span class="text-amber-500">OTA</span></h2>
                <p class="text-zinc-500 text-sm mt-1">Gerenciamento de versões, distribuição industrial e monitoramento.</p>
            </div>
            <div class="flex gap-4">
                <button class="bg-zinc-900 text-white font-bold px-6 py-3 rounded-2xl border border-zinc-800 hover:border-amber-500/50 transition-all text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px]">package_2</span>
                    GERAR INSTALADOR (ZIP)
                </button>
                <button class="bg-amber-500 text-black font-black px-6 py-3 rounded-2xl flex items-center gap-2 hover:scale-105 transition-all text-sm">
                    <span class="material-symbols-outlined text-[20px]">upload_file</span>
                    NOVA RELEASE
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
            <div class="bg-zinc-900/50 p-6 rounded-[30px] border border-zinc-900 shadow-xl">
                <p class="text-[10px] font-black text-zinc-600 uppercase tracking-widest mb-2">Status do Master</p>
                <h3 class="text-2xl font-black text-white">ONLINE</h3>
                <p class="text-emerald-500 text-[10px] font-bold mt-1">Sincronizado com GitHub</p>
            </div>
            <div class="bg-zinc-900/50 p-6 rounded-[30px] border border-zinc-900 shadow-xl">
                <p class="text-[10px] font-black text-zinc-600 uppercase tracking-widest mb-2">Versão Estável</p>
                <h3 class="text-2xl font-black text-amber-500">v1.1.0</h3>
                <p class="text-zinc-500 text-[10px] font-bold mt-1">Publicada em 08/05/2026</p>
            </div>
            <div class="bg-zinc-900/50 p-6 rounded-[30px] border border-zinc-900 shadow-xl">
                <p class="text-[10px] font-black text-zinc-600 uppercase tracking-widest mb-2">Clientes Conectados</p>
                <h3 class="text-2xl font-black text-white">1 ATIVO</h3>
                <p class="text-zinc-500 text-[10px] font-bold mt-1">Monitoramento em tempo real</p>
            </div>
        </div>

        <div class="bg-zinc-900/20 border border-zinc-900 rounded-[40px] overflow-hidden">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-zinc-900/50">
                        <th class="px-8 py-6 text-[10px] font-black text-zinc-600 uppercase tracking-widest">Versão / ID</th>
                        <th class="px-8 py-6 text-[10px] font-black text-zinc-600 uppercase tracking-widest">Status</th>
                        <th class="px-8 py-6 text-[10px] font-black text-zinc-600 uppercase tracking-widest">Data</th>
                        <th class="px-8 py-6 text-center text-[10px] font-black text-zinc-600 uppercase tracking-widest">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-900/30">
                    <?php if (empty($releases)): ?>
                        <tr>
                            <td colspan="4" class="p-20 text-center">
                                <span class="material-symbols-outlined text-6xl text-zinc-800 mb-4">inventory_2</span>
                                <p class="text-zinc-500 text-sm">Nenhuma release gerada ainda. O Master está pronto para o primeiro empacotamento.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($releases as $r): ?>
                        <tr class="group hover:bg-white/[0.02] transition-all">
                            <td class="px-8 py-6">
                                <div class="flex flex-col">
                                    <span class="text-white font-black tracking-tighter">v<?= $r['version'] ?></span>
                                    <span class="text-[10px] text-zinc-600 font-mono"><?= $r['release_id'] ?></span>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <span class="px-3 py-1 bg-emerald-500/10 text-emerald-500 rounded-full text-[9px] font-black uppercase"><?= $r['status'] ?></span>
                            </td>
                            <td class="px-8 py-6 text-xs text-zinc-400">
                                <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?>
                            </td>
                            <td class="px-8 py-6">
                                <div class="flex items-center justify-center gap-2">
                                    <button class="size-10 bg-zinc-900 rounded-xl border border-zinc-800 flex items-center justify-center text-zinc-400 hover:text-amber-500 transition-all">
                                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include 'templates/footer.php'; ?>
