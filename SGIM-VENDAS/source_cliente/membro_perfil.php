<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/db.php';

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM membros WHERE id = ?");
$stmt->execute([$id]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$m) {
    die("Membro não encontrado.");
}

$page_title = 'SGIM - Perfil do Membro';
$current_page = 'membros';

require_once 'includes/header.php';
?>

<div class="flex flex-col gap-8">
    <!-- Header Perfil -->
    <div class="flex flex-col md:flex-row items-center gap-8 bg-darkcard p-8 rounded-twelve border border-darkborder shadow-xl">
        <div class="size-48 rounded-full border-4 border-brand/20 p-1">
            <div class="w-full h-full rounded-full bg-darkbg overflow-hidden flex items-center justify-center border-2 border-brand">
                <?php if ($m['foto']): ?>
                    <img src="uploads/membros/<?= htmlspecialchars($m['foto']) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <span class="material-symbols-outlined text-gray-700 text-7xl font-light">person</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex-1 text-center md:text-left">
            <div class="flex flex-wrap items-center justify-center md:justify-start gap-3 mb-2">
                <h2 class="text-4xl font-black text-white tracking-tighter"><?= htmlspecialchars($m['nome']) ?></h2>
                <span class="px-3 py-1 bg-brand text-black text-[10px] font-black uppercase rounded-full">Pastor</span>
            </div>
            <p class="text-gray-500 font-medium tracking-wide">Sede Central • Membro desde <?= $m['data_conversao'] ? date('d/m/Y', strtotime($m['data_conversao'])) : 'N/A' ?></p>
            
            <div class="flex flex-wrap gap-4 mt-8">
                <a href="membro_editar.php?id=<?= $m['id'] ?>" class="flex items-center gap-2 px-6 py-2.5 bg-darkbg border border-darkborder hover:border-brand rounded-twelve text-sm font-bold text-gray-300 transition-all">
                    <span class="material-symbols-outlined text-[18px]">edit</span>
                    Editar Perfil
                </a>
                <a href="carteirinha_gerar.php?id=<?= $m['id'] ?>" class="flex items-center gap-2 px-6 py-2.5 bg-brand hover:bg-brand-dark text-black rounded-twelve text-sm font-bold shadow-lg shadow-brand/20 transition-all">
                    <span class="material-symbols-outlined text-[18px]">badge</span>
                    Gerar Carteirinha
                </a>
            </div>
        </div>
    </div>

    <!-- Grid de Informações -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Dados Pessoais -->
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder">
            <h3 class="flex items-center gap-2 text-brand text-sm font-black uppercase tracking-widest mb-6">
                <span class="material-symbols-outlined text-lg">person</span>
                Dados Pessoais
            </h3>
            <div class="space-y-4">
                <div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase mb-1">Nome Completo</p>
                    <p class="text-white font-medium"><?= htmlspecialchars($m['nome']) ?></p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase mb-1">Data de Nascimento</p>
                    <p class="text-white font-medium"><?= $m['data_nascimento'] ? date('d/m/Y', strtotime($m['data_nascimento'])) : 'N/A' ?></p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase mb-1">Endereço Residencial</p>
                    <p class="text-white font-medium leading-relaxed"><?= htmlspecialchars($m['endereco'] ?: 'N/A') ?></p>
                </div>
            </div>
        </div>

        <!-- Vida Ministerial -->
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder">
            <h3 class="flex items-center gap-2 text-brand text-sm font-black uppercase tracking-widest mb-6">
                <span class="material-symbols-outlined text-lg">church</span>
                Vida Ministerial
            </h3>
            <div class="space-y-4">
                <div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase mb-1">Data de Batismo</p>
                    <p class="text-white font-medium"><?= $m['data_batismo'] ? date('d/m/Y', strtotime($m['data_batismo'])) : 'N/A' ?></p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase mb-1">Cargo Atual</p>
                    <p class="text-brand font-black">Pastor</p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-500 font-bold uppercase mb-1">Congregação</p>
                    <p class="text-white font-medium">Sede Central</p>
                </div>
            </div>
        </div>

        <!-- Contato -->
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder">
            <h3 class="flex items-center gap-2 text-brand text-sm font-black uppercase tracking-widest mb-6">
                <span class="material-symbols-outlined text-lg">contact_support</span>
                Contato
            </h3>
            <div class="space-y-4">
                <div class="flex items-center gap-3 p-3 bg-darkbg rounded-lg border border-darkborder">
                    <span class="material-symbols-outlined text-brand text-sm">mail</span>
                    <p class="text-xs text-gray-300"><?= htmlspecialchars($m['email'] ?: 'N/A') ?></p>
                </div>
                <div class="flex items-center gap-3 p-3 bg-darkbg rounded-lg border border-darkborder">
                    <span class="material-symbols-outlined text-green-500 text-sm">chat</span>
                    <p class="text-xs text-gray-300"><?= htmlspecialchars($m['telefone'] ?: 'N/A') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
