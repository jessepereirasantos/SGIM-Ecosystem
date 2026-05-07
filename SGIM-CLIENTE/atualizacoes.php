<?php
/**
 * SGIM CLIENT - CENTRAL DE ATUALIZAÇÕES (Módulo Desativado)
 */
ob_start();
session_start();
require_once 'config/database.php';

// Controle de acesso
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title   = 'SGIM - Central de Atualizações';
$current_page = 'atualizacoes';

require_once 'includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-3xl font-black text-white tracking-tight italic uppercase">Central de <span class="text-brand">Atualizações</span></h2>
    <p class="text-xs text-gray-500 uppercase tracking-widest font-bold mt-1">Status do Módulo: Inativo</p>
</div>

<div class="mt-8 max-w-4xl">
    <div class="bg-darkcard border border-darkborder rounded-twelve p-12 text-center">
        <div class="w-20 h-20 bg-darkbg border border-darkborder rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
            <span class="material-symbols-outlined text-4xl text-gray-600">block</span>
        </div>
        <h3 class="text-2xl font-black text-white mb-2">Módulo em Manutenção</h3>
        <p class="text-gray-400 max-w-md mx-auto">
            O sistema de atualizações automáticas foi temporariamente desativado para reestruturação técnica. Nenhuma ação é necessária no momento.
        </p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>