<?php
require_once 'config/database.php';
$current_page = 'licencas';

// Função para Gerar Licença Profissional (Pattern: SGIM-XXXX-XXXX-XXXX)
function generateSGIMLicense() {
    return 'SGIM-' . strtoupper(substr(md5(uniqid()), 0, 4)) . '-' . strtoupper(substr(md5(uniqid()), 5, 4)) . '-' . strtoupper(substr(md5(uniqid()), 10, 4));
}

$msg = "";
if (isset($_POST['gerar_licenca'])) {
    $nova_chave = generateSGIMLicense();
    $msg = "<div class='p-6 bg-amber-500/10 text-amber-500 rounded-[32px] mb-10 border border-amber-500/20 flex items-center justify-between'>
                <div>
                    <p class='text-[10px] uppercase font-bold tracking-widest mb-1 opacity-60'>Nova Licença Gerada</p>
                    <h4 class='text-2xl font-black font-mono tracking-tight'>$nova_chave</h4>
                </div>
                <button onclick=\"navigator.clipboard.writeText('$nova_chave')\" class='bg-amber-500 text-black font-black px-6 py-3 rounded-2xl text-xs hover:scale-105 transition-all'>COPIAR CHAVE</button>
            </div>";
}

// Busca de Licenças Ativas (Simulado da tabela clientes onde license_key não é nulo)
$stmt = $pdo->query("SELECT id, nome, dominio, license_key, data_criacao FROM clientes WHERE license_key IS NOT NULL ORDER BY id DESC");
$licencas = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'templates/header.php';
?>

<div class="flex">
    <?php include 'templates/sidebar.php'; ?>

    <main class="ml-72 flex-1 p-10 bg-[#050505] min-h-screen">
        <div class="flex justify-between items-end mb-10">
            <div>
                <h2 class="text-3xl font-black text-white tracking-tighter">Licenças & <span class="text-amber-500">Ativações</span></h2>
                <p class="text-zinc-500 text-sm mt-1">Controle de chaves de acesso e integridade de licenças SaaS.</p>
            </div>
            <form method="POST">
                <button type="submit" name="gerar_licenca" class="bg-amber-500 text-black font-black px-8 py-4 rounded-2xl flex items-center gap-3 hover:scale-105 transition-all text-sm shadow-xl shadow-amber-500/10">
                    <span class="material-symbols-outlined font-bold">key</span>
                    GERAR LICENÇA MANUAL
                </button>
            </form>
        </div>

        <?= $msg ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($licencas as $l): ?>
            <div class="bg-zinc-900/30 border border-zinc-800 p-8 rounded-[40px] hover:border-amber-500/30 transition-all group relative overflow-hidden">
                <div class="absolute top-0 right-0 p-6 opacity-5 group-hover:opacity-20 transition-all">
                    <span class="material-symbols-outlined text-6xl">verified_user</span>
                </div>
                
                <div class="flex items-center gap-3 mb-6">
                    <div class="size-2 bg-emerald-500 rounded-full shadow-[0_0_10px_rgba(16,185,129,0.5)]"></div>
                    <span class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest">Ativa & Vinculada</span>
                </div>

                <h4 class="text-white font-bold text-lg mb-1"><?= htmlspecialchars($l['nome']) ?></h4>
                <p class="text-zinc-500 text-xs font-mono mb-6"><?= htmlspecialchars($l['dominio']) ?></p>

                <div class="bg-black/50 border border-zinc-800 p-4 rounded-2xl mb-6">
                    <p class="text-[9px] text-zinc-600 font-bold uppercase tracking-widest mb-1">Chave de Acesso</p>
                    <code class="text-amber-500 text-sm font-black font-mono"><?= $l['license_key'] ?></code>
                </div>

                <div class="flex justify-between items-center pt-6 border-t border-zinc-800/50">
                    <span class="text-[10px] text-zinc-600 font-bold uppercase">Emitida em <?= date('d/m/Y', strtotime($l['data_criacao'])) ?></span>
                    <button class="text-zinc-500 hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-[20px]">settings</span>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Card de Adição Rápida (Estilo Empty State) -->
            <div class="border-2 border-dashed border-zinc-800 p-8 rounded-[40px] flex flex-col items-center justify-center text-center opacity-50 hover:opacity-100 hover:border-amber-500/50 transition-all cursor-pointer group">
                <div class="size-16 bg-zinc-900 rounded-full flex items-center justify-center text-zinc-600 mb-4 group-hover:bg-amber-500/10 group-hover:text-amber-500 transition-all">
                    <span class="material-symbols-outlined text-3xl">add</span>
                </div>
                <p class="text-sm font-bold text-zinc-500 group-hover:text-white">Nova Licença</p>
            </div>
        </div>
    </main>
</div>

<?php include 'templates/footer.php'; ?>
