<?php
/**
 * SGIM CLIENT - CENTRAL DE NOVIDADES (NULL STATE)
 */
ob_start();
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title   = 'SGIM - Novidades';
$current_page = 'novidades';

require_once 'includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-3xl font-black text-white tracking-tight italic uppercase">O que há de <span class="text-brand">Novo?</span></h2>
    <p class="text-xs text-gray-500 uppercase tracking-widest font-bold mt-1">Status: Histórico Congelado</p>
</div>

<div class="mt-8 max-w-4xl">
    <div class="bg-darkcard border border-darkborder rounded-twelve p-12 text-center">
        <div class="w-20 h-20 bg-darkbg border border-darkborder rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
            <span class="material-symbols-outlined text-4xl text-gray-600">history</span>
        </div>
        <h3 class="text-2xl font-black text-white mb-2">Sem Novidades no Momento</h3>
        <p class="text-gray-400 max-w-md mx-auto">
            O feed de atualizações e novidades está sendo reestruturado para a nova arquitetura SGIM Industrial.
        </p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
