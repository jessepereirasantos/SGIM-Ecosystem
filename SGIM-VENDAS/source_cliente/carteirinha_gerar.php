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

$page_title = 'SGIM - Gerar Carteirinha';
$current_page = 'membros';

require_once 'includes/header.php';
?>

<div class="flex flex-col gap-8 items-center">
    <div class="w-full max-w-4xl flex justify-between items-center bg-darkcard p-6 rounded-twelve border border-darkborder">
        <div>
            <h2 class="text-2xl font-black text-white tracking-tighter">Editor de Carteirinha</h2>
            <p class="text-xs text-gray-500 uppercase font-bold tracking-widest mt-1">Módulo SGIM Cliente</p>
        </div>
        <div class="flex gap-3">
            <button onclick="window.print()" class="flex items-center gap-2 px-6 py-2.5 bg-brand hover:bg-brand-dark text-black rounded-twelve text-sm font-bold shadow-lg shadow-brand/20 transition-all">
                <span class="material-symbols-outlined text-[18px]">print</span>
                Imprimir / Exportar PDF
            </button>
        </div>
    </div>

    <!-- Layout da Carteirinha (Baseado na Imagem) -->
    <div id="carteirinha-layout" class="relative w-[450px] h-[280px] bg-[#0A0A0A] rounded-2xl border-2 border-brand/30 shadow-2xl overflow-hidden p-6 flex flex-col justify-between group">
        <!-- Brilho de Fundo -->
        <div class="absolute -top-20 -right-20 w-64 h-64 bg-brand/5 rounded-full blur-3xl pointer-events-none"></div>
        
        <!-- Header -->
        <div class="flex items-center justify-between relative z-10">
            <div class="flex items-center gap-3">
                <div class="size-10 bg-brand rounded-lg flex items-center justify-center text-black">
                    <span class="material-symbols-outlined text-2xl font-bold">church</span>
                </div>
                <div>
                    <h1 class="text-sm font-black text-white leading-tight uppercase tracking-tighter">SGIM CHURCH</h1>
                    <p class="text-[8px] text-gray-500 font-bold uppercase tracking-widest">Sistema de Gestão Integrada</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-[8px] text-brand font-black uppercase tracking-widest">Membro Oficial</p>
                <p class="text-[10px] text-gray-400 font-mono">ID: 2024-<?= str_pad($m['id'], 5, '0', STR_PAD_LEFT) ?></p>
            </div>
        </div>

        <!-- Corpo Central -->
        <div class="flex gap-6 items-center mt-4 relative z-10">
            <!-- Foto -->
            <div class="relative">
                <div class="size-28 rounded-xl bg-darkbg border-2 border-brand overflow-hidden flex items-center justify-center shadow-lg">
                    <?php if ($m['foto']): ?>
                        <img src="uploads/membros/<?= htmlspecialchars($m['foto']) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span class="material-symbols-outlined text-gray-700 text-5xl">person</span>
                    <?php endif; ?>
                </div>
                <div class="absolute -bottom-2 left-1/2 -translate-x-1/2 bg-brand text-black text-[8px] font-black px-2 py-0.5 rounded-full whitespace-nowrap shadow-md">
                    VÁLIDA ATÉ 12/26
                </div>
            </div>

            <!-- Dados -->
            <div class="flex-1 space-y-3">
                <div>
                    <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest mb-0.5">Nome do Membro</p>
                    <p class="text-sm font-black text-white uppercase tracking-tight"><?= htmlspecialchars($m['nome']) ?></p>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest mb-0.5">Cargo / Função</p>
                        <p class="text-[10px] font-bold text-gray-200 uppercase">Pastor</p>
                    </div>
                    <div>
                        <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest mb-0.5">Congregação</p>
                        <p class="text-[10px] font-bold text-gray-200 uppercase">Sede Central</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between border-t border-white/5 pt-4 mt-4 relative z-10">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-brand text-[14px]">verified_user</span>
                <span class="text-[8px] text-gray-500 font-bold uppercase tracking-widest">Autenticidade Digital</span>
            </div>
            <!-- QR Code Placeholder -->
            <div class="size-10 bg-white p-1 rounded-sm shadow-inner">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=SGIM-VERIFY-<?= $m['id'] ?>" class="w-full h-full">
            </div>
        </div>
    </div>

    <p class="text-sm text-gray-500 max-w-lg text-center mt-4">
        <span class="text-brand font-bold">Dica:</span> Use o atalho Ctrl+P e salve como PDF para envio digital via WhatsApp para o membro.
    </p>
</div>

<style>
@media print {
    body * { visibility: hidden; }
    #carteirinha-layout, #carteirinha-layout * { visibility: visible; }
    #carteirinha-layout {
        position: absolute;
        left: 0;
        top: 0;
        margin: 0;
        box-shadow: none;
        border: 1px solid #FFC107;
    }
    aside, header, button, p { display: none !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
