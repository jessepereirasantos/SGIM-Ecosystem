<?php
ob_start();
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = 'SGIM - Relatórios Gerais';
$current_page = 'relatorios';

try {
    $tl_membros = $pdo->query("SELECT COUNT(id) FROM membros")->fetchColumn();
    $tl_cong = $pdo->query("SELECT COUNT(id) FROM congregacoes")->fetchColumn();
    $tl_eventos = $pdo->query("SELECT COUNT(id) FROM eventos")->fetchColumn();
    $tl_deptos = $pdo->query("SELECT COUNT(id) FROM departamentos")->fetchColumn();
    $tl_cargos = $pdo->query("SELECT COUNT(id) FROM cargos")->fetchColumn();
    
    // Ativos e Inativos
    $membros_ativos = $pdo->query("SELECT COUNT(id) FROM membros WHERE status='Ativo'")->fetchColumn();
    $membros_inativos = $tl_membros - $membros_ativos;
    
    // Status eventos
    $eventos_pendentes = $pdo->query("SELECT COUNT(id) FROM eventos WHERE status='pendente'")->fetchColumn();
    
} catch (Exception $e) {
    $tl_membros = $tl_cong = $tl_eventos = $tl_deptos = $tl_cargos = $membros_ativos = $membros_inativos = $eventos_pendentes = 0;
}

require_once 'includes/header.php';
?>

    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight">Central de Relatórios</h2>
            <p class="text-sm text-gray-500 mt-1">Visão panôramica geral da infraestrutura ecadastros da Igreja.</p>
        </div>
        <div>
            <button onclick="window.print()" class="px-6 py-2 rounded-twelve bg-white text-black font-bold text-sm shadow-sm hover:bg-gray-200 transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px]">print</span>
                Imprimir Painel
            </button>
        </div>
    </div>

    <!-- Estatisticas Resumo -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder">
            <h3 class="text-gray-400 text-xs font-semibold uppercase tracking-widest">Congregações</h3>
            <p class="text-3xl font-bold mt-2 text-white"><?= $tl_cong ?></p>
        </div>
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder">
            <h3 class="text-gray-400 text-xs font-semibold uppercase tracking-widest">Membros Ativos</h3>
            <p class="text-3xl font-bold mt-2 text-brand"><?= $membros_ativos ?></p>
        </div>
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder">
            <h3 class="text-gray-400 text-xs font-semibold uppercase tracking-widest">Membros Inativos</h3>
            <p class="text-3xl font-bold mt-2 text-red-500"><?= $membros_inativos ?></p>
        </div>
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder">
            <h3 class="text-gray-400 text-xs font-semibold uppercase tracking-widest">Eventos Totais</h3>
            <p class="text-3xl font-bold mt-2 text-white"><?= $tl_eventos ?></p>
        </div>
    </div>

    <!-- Módulos Específicos -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Bloco 1 -->
        <div class="bg-darkcard rounded-twelve border border-darkborder overflow-hidden">
            <div class="p-6 border-b border-darkborder">
                <div class="flex items-center gap-3 mb-2">
                    <span class="material-symbols-outlined text-brand">group</span>
                    <h3 class="text-lg font-bold text-white">Relatórios de Pessoas</h3>
                </div>
                <p class="text-xs text-gray-500">Métricas de membresia e liderança</p>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between p-4 rounded-xl border border-darkborder bg-white/[0.02]">
                    <div>
                        <p class="text-sm font-bold text-white">Total de Departamentos</p>
                        <p class="text-xs text-brand font-medium"><?= $tl_deptos ?> operacionais</p>
                    </div>
                    <a href="departamentos.php" class="text-xs bg-darkbg border border-darkborder px-4 py-2 rounded-lg text-gray-300 hover:text-white transition-colors">Gerenciar</a>
                </div>
                <div class="flex items-center justify-between p-4 rounded-xl border border-darkborder bg-white/[0.02]">
                    <div>
                        <p class="text-sm font-bold text-white">Lideranças (Cargos)</p>
                        <p class="text-xs text-blue-500 font-medium"><?= $tl_cargos ?> cargos criados</p>
                    </div>
                </div>
                <a href="crescimento.php" class="block text-center mt-4 text-xs font-bold text-brand uppercase tracking-widest hover:underline">
                    Ver Dossiê Analítico de Crescimento &rarr;
                </a>
            </div>
        </div>

        <!-- Bloco 2 -->
        <div class="bg-darkcard rounded-twelve border border-darkborder overflow-hidden">
            <div class="p-6 border-b border-darkborder">
                <div class="flex items-center gap-3 mb-2">
                    <span class="material-symbols-outlined text-brand">event</span>
                    <h3 class="text-lg font-bold text-white">Infraestrutura e Agenda</h3>
                </div>
                <p class="text-xs text-gray-500">Dados de culto e estrutura</p>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between p-4 rounded-xl border border-darkborder bg-white/[0.02]">
                    <div>
                        <p class="text-sm font-bold text-white">Eventos Agendados</p>
                        <p class="text-xs text-yellow-500 font-medium"><?= $eventos_pendentes ?> pendentes</p>
                    </div>
                    <a href="eventos.php" class="text-xs bg-darkbg border border-darkborder px-4 py-2 rounded-lg text-gray-300 hover:text-white transition-colors">Ver Agenda</a>
                </div>
                <div class="flex items-center justify-between p-4 rounded-xl border border-darkborder bg-white/[0.02]">
                    <div>
                        <p class="text-sm font-bold text-white">Rede de Congregações</p>
                        <p class="text-xs text-gray-400 font-medium"><?= $tl_cong ?> filiais</p>
                    </div>
                </div>
                <a href="financeiro_relatorio.php" target="_blank" class="block text-center mt-4 text-xs font-bold text-gray-400 uppercase tracking-widest hover:underline hover:text-white">
                    Exportar Relatório Financeiro (XLS/PDF) &rarr;
                </a>
            </div>
        </div>

    </div>

<?php require_once 'includes/footer.php'; ?>
