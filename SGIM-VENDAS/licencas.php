<?php
/**
 * SGIM MASTER - LICENÇAS (Obsidian Amber Style v8.1)
 */
$current_page = 'licencas';
require_once 'templates/header.php';

try {
    $stmt = $pdo->query("SELECT l.*, c.nome as cliente_nome FROM licencas l JOIN clientes c ON l.cliente_id = c.id ORDER BY l.id DESC");
    $licencas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Lista de clientes para o modal
    $lista_clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $error_sql = $e->getMessage(); }
?>

<div class="flex justify-between items-end mb-10">
    <div>
        <h2 class="text-display-lg font-display-lg text-on-surface mb-2">Licenças & Ativação</h2>
        <p class="text-on-surface-variant font-body-md opacity-80">Controle de chaves e domínios autorizados.</p>
    </div>
    <button onclick="document.getElementById('modalLicenca').classList.remove('hidden')" class="px-8 py-4 rounded-xl bg-primary text-on-primary text-sm font-black hover:scale-105 transition-all shadow-xl shadow-amber-500/10">
        GERAR LICENÇA
    </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($licencas as $l): ?>
    <div id="row-lic-<?= $l['id'] ?>" class="glass-card p-8 rounded-2xl border-l-4 <?= $l['status'] == 'ativa' ? 'border-emerald-500' : 'border-amber-500' ?> relative group transition-all">
        <div class="flex justify-between items-start mb-6">
            <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest bg-zinc-900 border border-zinc-800 text-zinc-500">
                <?= strtoupper($l['status']) ?>
            </span>
            <span class="text-[10px] text-zinc-600 font-mono italic"><?= htmlspecialchars($l['dominio']) ?></span>
        </div>
        <h4 class="text-lg font-bold text-white mb-1"><?= htmlspecialchars($l['cliente_nome']) ?></h4>
        <div class="mt-6 p-5 bg-black/40 rounded-xl border border-zinc-800 flex justify-between items-center group/key">
            <code class="text-primary font-mono text-xs font-bold"><?= $l['chave_licenca'] ?></code>
            <button onclick="navigator.clipboard.writeText('<?= $l['chave_licenca'] ?>')" class="material-symbols-outlined text-zinc-700 hover:text-white cursor-pointer transition-colors text-[18px]">content_copy</button>
        </div>
        <button onclick="deleteRecord('licencas', <?= $l['id'] ?>)" class="absolute top-4 right-4 size-8 bg-black/50 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-rose-500 hover:bg-rose-500 hover:text-white">
            <span class="material-symbols-outlined text-sm">delete</span>
        </button>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal Gerar Licença (DESIGN PADRONIZADO) -->
<div id="modalLicenca" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-black/90 backdrop-blur-sm p-4">
    <div class="bg-[#121212] border border-white/5 rounded-[32px] w-full max-w-md p-10 shadow-2xl relative">
        <div class="flex justify-between items-center mb-10">
            <h3 class="text-2xl font-bold text-white">Gerar <span class="text-primary italic">Licença</span></h3>
            <button onclick="document.getElementById('modalLicenca').classList.add('hidden')" class="text-zinc-500 hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <form id="formLicenca" class="space-y-6">
            <input type="hidden" name="table" value="licencas">
            <input type="hidden" name="data[status]" value="ativa">
            <input type="hidden" name="data[chave_licenca]" value="<?= strtoupper(substr(md5(uniqid()), 0, 16)) ?>">
            
            <div class="space-y-2">
                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Selecionar Cliente</label>
                <div class="relative">
                    <select name="data[cliente_id]" required class="w-full bg-white rounded-xl px-6 py-4 text-black font-bold appearance-none focus:ring-4 focus:ring-primary/20">
                        <option value="">Selecione o Cliente...</option>
                        <?php foreach ($lista_clientes as $lc): ?>
                            <option value="<?= $lc['id'] ?>"><?= htmlspecialchars($lc['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none">expand_more</span>
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Domínio de Ativação</label>
                <input type="text" name="data[dominio]" required class="w-full bg-white rounded-xl px-6 py-4 text-black font-bold focus:ring-4 focus:ring-primary/20 transition-all" placeholder="ex: meusistema.com.br">
            </div>

            <button type="submit" class="w-full py-5 bg-primary text-on-primary font-black rounded-xl shadow-xl hover:opacity-90 transition-all uppercase tracking-widest text-sm mt-4">
                GERAR AGORA
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('formLicenca').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('api/save_record.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json()).then(data => { if(data.success) location.reload(); else alert(data.message); });
});
function deleteRecord(table, id) {
    if(!confirm('Excluir licença?')) return;
    const fd = new FormData(); fd.append('table', table); fd.append('id', id);
    fetch('api/delete_record.php', { method: 'POST', body: fd })
    .then(r => r.json()).then(data => { if(data.success) document.getElementById('row-lic-'+id).remove(); });
}
</script>

<?php include 'templates/footer.php'; ?>
