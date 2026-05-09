<?php
require_once 'config/database.php';
$current_page = 'cupons';

// Busca de Cupons
$stmt = $pdo->query("SELECT * FROM cupons ORDER BY id DESC");
$cupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'templates/header.php';
?>

<div class="flex">
    <?php include 'sidebar.php'; ?>

    <main class="ml-[280px] min-h-screen flex-1">
        <!-- Top Navigation -->
        <header class="h-16 flex items-center justify-between px-8 bg-surface/80 backdrop-blur-md sticky top-0 z-40 border-b border-outline-variant/10">
            <div class="flex items-center gap-2 text-on-surface-variant font-bold text-xs uppercase tracking-widest">
                <span class="material-symbols-outlined text-primary">confirmation_number</span>
                Campaign Management
            </div>
        </header>

        <div class="p-10 max-w-[1600px] mx-auto">
            <div class="flex justify-between items-end mb-10">
                <div>
                    <h2 class="text-display-lg font-bold text-on-surface tracking-tighter">Cupons & <span class="text-primary">Descontos</span></h2>
                    <p class="text-on-surface-variant font-body-md opacity-60">Gestão de códigos promocionais e estratégias de conversão de vendas.</p>
                </div>
                <button class="px-5 py-2.5 rounded-lg bg-primary text-on-primary font-bold hover:opacity-90 transition-all flex items-center gap-2 text-sm shadow-xl shadow-primary/20">
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    CRIAR NOVO CUPOM
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php if (count($cupons) > 0): ?>
                    <?php foreach ($cupons as $c): ?>
                    <div class="glass-card p-6 rounded-xl hover:border-primary/30 transition-all group relative overflow-hidden">
                        <div class="flex justify-between items-start mb-6">
                            <span class="px-3 py-1 bg-primary/10 text-primary text-[10px] font-black rounded-lg uppercase tracking-widest"><?= $c['codigo'] ?></span>
                            <span class="text-secondary text-sm font-black"><?= $c['desconto'] ?>% OFF</span>
                        </div>
                        <p class="text-[9px] text-on-surface-variant font-bold uppercase tracking-widest mb-1">Status da Campanha</p>
                        <div class="flex items-center gap-2 mb-4">
                            <div class="size-1.5 bg-secondary rounded-full"></div>
                            <span class="text-[10px] text-on-surface font-bold">Ativa</span>
                        </div>
                        <div class="pt-4 border-t border-outline-variant/10 flex justify-between items-center">
                            <span class="text-[9px] text-on-surface-variant uppercase font-bold">Usos: <?= $c['usos'] ?? 0 ?></span>
                            <button class="text-on-surface-variant hover:text-error transition-all">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full p-20 glass-card rounded-xl text-center opacity-40">
                        <span class="material-symbols-outlined text-5xl mb-4">local_offer</span>
                        <p class="text-sm font-bold italic tracking-tighter">Nenhuma campanha de desconto ativa no momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php include 'templates/footer.php'; ?>
