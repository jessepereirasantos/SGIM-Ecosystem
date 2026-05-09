<?php
ob_start();
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title   = 'SGIM - Página de Teste';
$current_page = 'teste';

require_once 'includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-3xl font-black text-white tracking-tight italic uppercase">Página de <span class="text-brand">Teste</span></h2>
    <p class="text-xs text-gray-500 uppercase tracking-widest font-bold mt-1">Esta página confirma que o sistema OTA está funcionando corretamente</p>
</div>

<div class="max-w-2xl">
    <div class="bg-darkcard border border-brand/30 rounded-twelve p-8 text-center shadow-2xl relative overflow-hidden">
        <div class="absolute -top-16 -right-16 w-48 h-48 bg-brand/5 rounded-full blur-3xl pointer-events-none"></div>
        <span class="material-symbols-outlined text-6xl text-brand mb-4 block">rocket_launch</span>
        <h3 class="text-2xl font-black text-white mb-2">✅ Atualização OTA Confirmada!</h3>
        <p class="text-gray-400 text-sm leading-relaxed">Se você está vendo esta página, significa que o sistema de atualização automática (OTA) está funcionando perfeitamente. Esta aba foi instalada remotamente, sem nenhuma intervenção manual no servidor do cliente.</p>
        <div class="mt-6 bg-darkbg border border-darkborder rounded-xl p-4 text-left">
            <p class="text-xs text-brand font-bold uppercase tracking-widest mb-2">Info do Ambiente</p>
            <p class="text-xs text-gray-500 font-mono">Servidor: <?= $_SERVER['HTTP_HOST'] ?></p>
            <p class="text-xs text-gray-500 font-mono">Horário: <?= date('d/m/Y H:i:s') ?></p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
