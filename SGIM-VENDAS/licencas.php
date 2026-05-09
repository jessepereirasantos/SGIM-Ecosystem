<?php
require_once 'config/database.php';
$current_page = 'licencas';

// Função para Gerar Licença (Preservando lógica)
function generateSGIMLicense() {
    return 'SGIM-' . strtoupper(substr(md5(uniqid()), 0, 4)) . '-' . strtoupper(substr(md5(uniqid()), 5, 4)) . '-' . strtoupper(substr(md5(uniqid()), 10, 4));
}

$msg = "";
if (isset($_POST['gerar_licenca'])) {
    $nova_chave = generateSGIMLicense();
    $msg = "<div class='glass-card p-6 rounded-xl mb-10 border-primary/20 flex items-center justify-between'>
                <div>
                    <p class='text-[10px] uppercase font-bold tracking-widest mb-1 text-primary'>Nova Licença Gerada</p>
                    <h4 class='text-2xl font-black font-mono tracking-tight text-white'>$nova_chave</h4>
                </div>
                <button onclick=\"navigator.clipboard.writeText('$nova_chave')\" class='bg-primary text-on-primary font-bold px-6 py-3 rounded-lg text-xs hover:scale-105 transition-all shadow-xl shadow-primary/10'>COPIAR CHAVE</button>
            </div>";
}

// Busca de Licenças Ativas
$stmt = $pdo->query("SELECT id, nome, dominio, license_key, created_at FROM clientes WHERE license_key IS NOT NULL ORDER BY id DESC");
$licencas = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'templates/header.php';
?>

<div class="flex">
    <?php include 'sidebar.php'; ?>

    <main class="ml-[280px] min-h-screen flex-1">
        <!-- Top Navigation -->
        <header class="h-16 flex items-center justify-between px-8 bg-surface/80 backdrop-blur-md sticky top-0 z-40 border-b border-outline-variant/10">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">vpn_key</span>
                <span class="text-xs font-bold text-on-surface-variant uppercase tracking-widest">Licensing Control</span>
            </div>
        </header>

        <div class="p-10 max-w-[1600px] mx-auto">
            <div class="flex justify-between items-end mb-10">
                <div>
                    <h2 class="text-display-lg font-bold text-on-surface tracking-tighter">Licenças & <span class="text-primary">Ativações</span></h2>
                    <p class="text-on-surface-variant font-body-md opacity-60">Controle de chaves de acesso e integridade de licenças SaaS.</p>
                </div>
                <form method="POST">
                    <button type="submit" name="gerar_licenca" class="bg-primary text-on-primary font-bold px-8 py-4 rounded-xl flex items-center gap-3 hover:scale-105 transition-all text-sm shadow-xl shadow-primary/20">
                        <span class="material-symbols-outlined font-bold">key</span>
                        GERAR LICENÇA MANUAL
                    </button>
                </form>
            </div>

            <?= $msg ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($licencas as $l): ?>
                <div class="glass-card p-8 rounded-xl hover:border-primary/30 transition-all group relative overflow-hidden">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="size-2 bg-secondary rounded-full shadow-[0_0_10px_rgba(242,191,58,0.5)]"></div>
                        <span class="text-[10px] font-bold text-secondary uppercase tracking-widest italic">Ativa & Vinculada</span>
                    </div>

                    <h4 class="text-on-surface font-bold text-lg leading-tight"><?= htmlspecialchars($l['nome']) ?></h4>
                    <p class="text-on-surface-variant text-xs font-mono mb-6"><?= htmlspecialchars($l['dominio']) ?></p>

                    <div class="bg-surface-container/50 border border-outline-variant/10 p-4 rounded-xl mb-6">
                        <p class="text-[9px] text-on-surface-variant font-bold uppercase tracking-widest mb-1">Chave de Acesso</p>
                        <code class="text-primary text-sm font-black font-mono break-all"><?= $l['license_key'] ?></code>
                    </div>

                    <div class="flex justify-between items-center pt-6 border-t border-outline-variant/10">
                        <span class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest opacity-60">Emitida em <?= date('d/m/Y', strtotime($l['created_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<?php include 'templates/footer.php'; ?>
