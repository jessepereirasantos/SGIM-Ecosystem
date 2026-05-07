<?php
require_once '../config/database.php';
$current_page = 'clientes';

// Processamento de Ações (Simples para este MVP, evoluiremos para AJAX)
$msg = "";
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id]);
        $msg = "<div class='p-4 bg-emerald-500/10 text-emerald-500 rounded-2xl mb-6 text-sm font-bold border border-emerald-500/20'>✅ Cliente removido com sucesso.</div>";
    } catch (Exception $e) {
        $msg = "<div class='p-4 bg-red-500/10 text-red-500 rounded-2xl mb-6 text-sm font-bold border border-red-500/20'>🛑 Erro ao remover: " . $e->getMessage() . "</div>";
    }
}

// Busca de Clientes
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM clientes";
if (!empty($search)) {
    $query .= " WHERE nome LIKE :s OR email LIKE :s OR dominio LIKE :s";
}
$query .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query);
if (!empty($search)) {
    $stmt->bindValue(':s', "%$search%");
}
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../templates/header.php';
?>

<div class="flex">
    <?php include '../templates/sidebar.php'; ?>

    <main class="ml-72 flex-1 p-10 bg-[#050505] min-h-screen">
        <div class="flex justify-between items-end mb-10">
            <div>
                <h2 class="text-3xl font-black text-white tracking-tighter">Gestão de <span class="text-amber-500">Clientes</span></h2>
                <p class="text-zinc-500 text-sm mt-1">Controle total sobre a base instalada e domínios ativos.</p>
            </div>
            <div class="flex gap-4">
                <form class="relative group">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar cliente..." 
                           class="bg-zinc-900 border border-zinc-800 rounded-2xl px-5 py-3 text-sm text-white w-64 focus:border-amber-500 outline-none transition-all pl-12">
                    <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-zinc-600 group-focus-within:text-amber-500 transition-colors">search</span>
                </form>
                <button class="bg-amber-500 text-black font-black px-6 py-3 rounded-2xl flex items-center gap-2 hover:scale-105 transition-all text-sm">
                    <span class="material-symbols-outlined text-[20px]">person_add</span>
                    NOVO CLIENTE
                </button>
            </div>
        </div>

        <?= $msg ?>

        <div class="bg-zinc-900/20 border border-zinc-900 rounded-[40px] overflow-hidden">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] text-zinc-600 uppercase tracking-widest bg-white/[0.02] border-b border-zinc-800">
                        <th class="px-8 py-6 font-bold">Identificação</th>
                        <th class="px-8 py-6 font-bold">Domínio Autorizado</th>
                        <th class="px-8 py-6 font-bold">Licença</th>
                        <th class="px-8 py-6 font-bold text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/50">
                    <?php if (count($clientes) > 0): ?>
                        <?php foreach ($clientes as $c): ?>
                        <tr class="group hover:bg-white/[0.01] transition-colors">
                            <td class="px-8 py-6">
                                <div class="flex items-center gap-4">
                                    <div class="size-12 bg-gradient-to-br from-zinc-800 to-zinc-900 rounded-2xl flex items-center justify-center text-lg font-black text-zinc-500 border border-zinc-800"><?= strtoupper(substr($c['nome'] ?? 'U', 0, 1)) ?></div>
                                    <div>
                                        <p class="text-sm font-bold text-white"><?= htmlspecialchars($c['nome'] ?? 'Sem Nome') ?></p>
                                        <p class="text-[11px] text-zinc-500"><?= htmlspecialchars($c['email'] ?? '') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="flex flex-col">
                                    <span class="text-xs text-white font-mono"><?= htmlspecialchars($c['dominio'] ?? 'pendente...') ?></span>
                                    <span class="text-[9px] text-zinc-600 uppercase font-bold tracking-tighter mt-1">HostGator / Shared</span>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="bg-zinc-900 px-3 py-2 rounded-xl border border-zinc-800 flex items-center justify-between group-hover:border-amber-500/30 transition-all">
                                    <span class="text-[10px] text-zinc-400 font-mono"><?= substr($c['license_key'] ?? 'N/A', 0, 15) ?>...</span>
                                    <span class="material-symbols-outlined text-[14px] text-emerald-500">verified</span>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="flex items-center justify-center gap-2">
                                    <button class="size-10 bg-zinc-900 rounded-xl border border-zinc-800 flex items-center justify-center text-zinc-400 hover:text-amber-500 hover:border-amber-500/50 transition-all shadow-sm">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <a href="?delete=<?= $c['id'] ?>" onclick="return confirm('ATENÇÃO: Deseja realmente excluir este cliente? Esta ação é irreversível.')" 
                                       class="size-10 bg-zinc-900 rounded-xl border border-zinc-800 flex items-center justify-center text-zinc-400 hover:text-red-500 hover:border-red-500/50 transition-all shadow-sm">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="p-20 text-center">
                                <span class="material-symbols-outlined text-6xl text-zinc-800 mb-4">person_search</span>
                                <p class="text-zinc-500 text-sm">Nenhum cliente encontrado com os critérios de busca.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include '../templates/footer.php'; ?>
