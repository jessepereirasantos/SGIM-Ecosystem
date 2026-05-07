<?php
/**
 * SGIM MASTER - CLIENTES (Obsidian Amber Style v8.1)
 */
$current_page = 'clientes';
require_once 'templates/header.php';

try {
    $stmt = $pdo->query("SELECT * FROM clientes ORDER BY id DESC");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $error_sql = $e->getMessage(); }
?>

<div class="flex justify-between items-end mb-10">
    <div>
        <h2 class="text-display-lg text-on-surface mb-2 font-display-lg">Gestão de Clientes</h2>
        <p class="text-on-surface-variant font-body-md opacity-80">Administração de parceiros e acessos.</p>
    </div>
    <button onclick="document.getElementById('modalCliente').classList.remove('hidden')" class="px-8 py-4 rounded-xl bg-primary text-on-primary text-sm font-black hover:scale-105 transition-all shadow-xl shadow-amber-500/10">
        NOVO CLIENTE
    </button>
</div>

<div class="glass-card rounded-2xl overflow-hidden">
    <table class="w-full text-left">
        <thead>
            <tr class="bg-surface-container-low/50">
                <th class="px-8 py-6 text-label-caps text-on-surface-variant/50 uppercase">Nome / Documento</th>
                <th class="px-8 py-6 text-label-caps text-on-surface-variant/50 uppercase">Contato</th>
                <th class="px-8 py-6 text-label-caps text-on-surface-variant/50 uppercase">Data</th>
                <th class="px-8 py-6 text-label-caps text-on-surface-variant/50 uppercase text-right">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant/10">
            <?php foreach ($clientes as $c): ?>
            <tr id="row-<?= $c['id'] ?>" class="hover:bg-white/[0.02] transition-all group">
                <td class="px-8 py-6">
                    <p class="text-sm font-bold text-on-surface"><?= htmlspecialchars($c['nome']) ?></p>
                    <p class="text-[10px] text-zinc-500 font-mono"><?= htmlspecialchars($c['documento'] ?? '---') ?></p>
                </td>
                <td class="px-8 py-6">
                    <p class="text-sm text-zinc-400"><?= htmlspecialchars($c['email']) ?></p>
                    <p class="text-[10px] text-zinc-600 italic"><?= htmlspecialchars($c['telefone'] ?? '') ?></p>
                </td>
                <td class="px-8 py-6 text-xs text-zinc-500"><?= date('d/m/Y', strtotime($c['data_cadastro'])) ?></td>
                <td class="px-8 py-6 text-right">
                    <button class="size-10 bg-zinc-900 rounded-xl inline-flex items-center justify-center text-zinc-600 hover:text-primary transition-all">
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                    </button>
                    <button onclick="deleteRecord('clientes', <?= $c['id'] ?>)" class="size-10 bg-zinc-900 rounded-xl inline-flex items-center justify-center text-zinc-600 hover:text-red-500 transition-all">
                        <span class="material-symbols-outlined text-[18px]">delete</span>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Novo Cliente (DESIGN PADRONIZADO) -->
<div id="modalCliente" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-black/90 backdrop-blur-sm p-4">
    <div class="bg-[#121212] border border-white/5 rounded-[32px] w-full max-w-lg p-10 shadow-2xl relative">
        <div class="flex justify-between items-center mb-10">
            <h3 class="text-2xl font-bold text-white">Novo <span class="text-primary italic">Cliente</span></h3>
            <button onclick="document.getElementById('modalCliente').classList.add('hidden')" class="text-zinc-500 hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <form id="formCliente" class="space-y-6">
            <input type="hidden" name="table" value="clientes">
            
            <div class="space-y-2">
                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Nome da Empresa / Cliente</label>
                <input type="text" name="data[nome]" required class="w-full bg-white rounded-xl px-6 py-4 text-black font-bold focus:ring-4 focus:ring-primary/20 transition-all">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">E-mail</label>
                    <input type="email" name="data[email]" required class="w-full bg-white rounded-xl px-6 py-4 text-black font-bold focus:ring-4 focus:ring-primary/20">
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Telefone</label>
                    <input type="text" name="data[telefone]" class="w-full bg-white rounded-xl px-6 py-4 text-black font-bold focus:ring-4 focus:ring-primary/20">
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Documento (CPF/CNPJ)</label>
                <input type="text" name="data[documento]" class="w-full bg-white rounded-xl px-6 py-4 text-black font-bold focus:ring-4 focus:ring-primary/20">
            </div>

            <button type="submit" class="w-full py-5 bg-primary text-on-primary font-black rounded-xl shadow-xl hover:opacity-90 transition-all uppercase tracking-widest text-sm mt-4">
                SALVAR CLIENTE
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('formCliente').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('api/save_record.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json()).then(data => { if(data.success) location.reload(); else alert(data.message); });
});
function deleteRecord(table, id) {
    if(!confirm('Excluir?')) return;
    const fd = new FormData(); fd.append('table', table); fd.append('id', id);
    fetch('api/delete_record.php', { method: 'POST', body: fd })
    .then(r => r.json()).then(data => { if(data.success) document.getElementById('row-'+id).remove(); });
}
</script>

<?php include 'templates/footer.php'; ?>
