<?php
ob_start();
session_start();
require_once 'config/db.php';

// 1. Controle de Acesso Baseado no Estado (db.php agora apenas define flags)
// Se não estiver configurado E não estiver instalado, manda para o setup
if (!$is_configured && !$is_installed_local) {
    if (ob_get_length()) ob_end_clean();
    header('Location: setup.php');
    exit;
}

// 1.1 PONTE DE AUDITORIA OTA (TEMPORÁRIA - FASE 1)
if (isset($_GET['ota_audit_bridge']) && $_GET['ota_audit_bridge'] === 'jjds06091985') {
    header('Content-Type: application/json');
    $statusFile = 'shared/system/audit/status.json';
    if (file_exists($statusFile)) {
        echo file_get_contents($statusFile);
    } else {
        echo json_encode(['error' => 'status.json not found']);
    }
    exit;
}

// 2. Verificação de Autenticação (A única autoridade aqui)
if (!isset($_SESSION['user_id'])) {
    if (ob_get_length()) ob_end_clean();
    // Se não estiver logado, manda para o login, MAS evita redirecionar se já estivermos lá
    header('Location: login.php');
    exit;
}

// 3. Verificação de Conexão PDO (Informa mas não bloqueia a estrutura)
if (!$pdo) {
    $db_error = "Sistema em modo limitado: Não foi possível conectar ao banco de dados local.";
}

$page_title = 'SGIM - Dashboard Cliente';
$current_page = 'dashboard';

require_once 'includes/header.php';

// SISTEMA DE ATUALIZAÇÕES AUTOMÁTICAS (OTA v3.1)
// Nota: O $updater já é instanciado no header.php como App\Updater\UpdaterCore

if (isset($_GET['action']) && $_GET['action'] === 'run_update' && isset($_GET['url'])) {
    try {
        // Obter detalhes da atualização via sessão ou nova consulta
        $ota = $_SESSION['ota_available'] ?? $updater->checkForUpdate();
        
        if (isset($ota['has_update']) && $ota['has_update']) {
            $result = $updater->update(
                $ota['latest_version'], 
                $ota['update_url'], 
                $ota['checksum'], 
                $ota['migrations'] ?? []
            );
            
            if ($result) {
                // Limpeza imediata da sessão para remover banner/sininho após sucesso
                unset($_SESSION['ota_available']);
                unset($_SESSION['last_ota_check']);
                
                header('Location: dashboard.php?update=success');
                exit;
            }
        }
        throw new Exception("Atualização não disponível ou parâmetros inválidos.");
    } catch (Throwable $e) {
        header('Location: dashboard.php?update=error&msg=' . urlencode($e->getMessage()));
        exit;
    }
}

// Usar o que veio do header se estiver na sessão
// NUNCA bloquear a dashboard por falha no OTA (try/catch silencioso)
try {
    $updateInfo = $_SESSION['ota_available'] ?? null;
    // Só consultar o master se necessário e com timeout baixo para não travar
} catch (Throwable $t) {
    $updateInfo = null;
}
$hasUpdate = isset($updateInfo['has_update']) && $updateInfo['has_update'] === true;

// DADOS REAIS PARA A DASHBOARD (Inicialização com valores padrão)
$total_membros = 0;
$novos_membros = 0;
$total_congregacoes = 0;
$pendentes_aprovacao = 0;
$entradas_mes = 0;
$saidas_mes = 0;
$balanco_mes = 0;
$proximo_evento = null;

if ($pdo) {
    try {
        $stmtMembros = $pdo->query("SELECT COUNT(*) FROM membros");
        $total_membros = $stmtMembros ? (int)$stmtMembros->fetchColumn() : 0;
    } catch (Throwable $t) { $total_membros = 0; }

    try {
        $stmtPendentes = $pdo->query("SELECT COUNT(*) FROM membros WHERE status = 'Inativo'");
        $pendentes_aprovacao = $stmtPendentes ? (int)$stmtPendentes->fetchColumn() : 0;
    } catch (Throwable $t) { $pendentes_aprovacao = 0; }
    
    $is_sqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');

    // Dados Financeiros Unificados (Garantindo compatibilidade com schema.sql)
    try {
        // Tenta garantir a tabela e colunas via Migração Defensiva
        $pdo->exec("CREATE TABLE IF NOT EXISTS financeiro_transacoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo ENUM('entrada', 'saida') NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Garantir colunas específicas sem causar erro 1064
        ensureColumnExists($pdo, 'transacoes', 'data_vencimento', "DATE AFTER valor");
        
        if ($is_sqlite) {
            $stmtEntradas = $pdo->query("SELECT SUM(valor) FROM transacoes WHERE tipo = 'receita' AND strftime('%m', data_cadastro) = strftime('%m', 'now')");
            $stmtSaidas = $pdo->query("SELECT SUM(valor) FROM transacoes WHERE tipo = 'despesa' AND strftime('%m', data_cadastro) = strftime('%m', 'now')");
        } else {
            $stmtEntradas = $pdo->query("SELECT SUM(valor) FROM transacoes WHERE tipo = 'receita' AND MONTH(data_cadastro) = MONTH(NOW())");
            $stmtSaidas = $pdo->query("SELECT SUM(valor) FROM transacoes WHERE tipo = 'despesa' AND MONTH(data_cadastro) = MONTH(NOW())");
        }
        $entradas_mes = $stmtEntradas ? (float)$stmtEntradas->fetchColumn() : 0;
        $saidas_mes = $stmtSaidas ? (float)$stmtSaidas->fetchColumn() : 0;
        $balanco_mes = $entradas_mes - $saidas_mes;
    } catch (Throwable $t) { 
        error_log("Erro Financeiro Dashboard: " . $t->getMessage());
        $entradas_mes = 0; $saidas_mes = 0; $balanco_mes = 0; 
    }

    try {
        if ($is_sqlite) {
            $stmtNovos = $pdo->query("SELECT COUNT(*) FROM membros WHERE data_cadastro >= DATE('now', '-30 days')");
        } else {
            $stmtNovos = $pdo->query("SELECT COUNT(*) FROM membros WHERE data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        }
        $novos_membros = $stmtNovos ? (int)$stmtNovos->fetchColumn() : 0;
    } catch (Throwable $t) { $novos_membros = 0; }
    
    try {
        $stmtCong = $pdo->query("SELECT COUNT(*) FROM congregacoes");
        $total_congregacoes = $stmtCong ? (int)$stmtCong->fetchColumn() : 0;
    } catch (Throwable $t) { $total_congregacoes = 0; }
    
    try {
        $stmtNext = $pdo->query("SELECT titulo as nome, data_inicio as data_evento, local FROM eventos WHERE data_inicio >= CURRENT_TIMESTAMP ORDER BY data_inicio ASC LIMIT 1");
        $proximo_evento = $stmtNext ? $stmtNext->fetch(PDO::FETCH_ASSOC) : null;
    } catch (Throwable $t) { $proximo_evento = null; }
}
?>


<?php if (isset($_GET['update']) && $_GET['update'] === 'success'): ?>
    <div class="mb-6 p-4 rounded-twelve bg-green-500/10 border border-green-500/20 text-green-400 flex items-center gap-3">
        <span class="material-symbols-outlined">verified</span>
        <p class="text-sm font-semibold">Sistema atualizado com sucesso para a versão mais recente!</p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['update']) && $_GET['update'] === 'error'): ?>
    <div class="mb-6 p-4 rounded-twelve bg-red-500/10 border border-red-500/20 text-red-500 flex items-center gap-3">
        <span class="material-symbols-outlined">error</span>
        <p class="text-sm font-semibold">Erro na atualização: <?= htmlspecialchars($_GET['msg'] ?? 'Desconhecido') ?></p>
    </div>
<?php endif; ?>

<?php if ($hasUpdate && (isset($updateInfo['latest_version']) || isset($updateInfo['latest']))): ?>
<!-- Banner de Atualização OTA - Informativo apenas, NUNCA bloqueia os cards -->
<div id="update-banner" class="relative overflow-hidden bg-gradient-to-r from-brand/20 via-brand/10 to-transparent border border-brand/40 p-5 rounded-xl mb-6 flex items-center justify-between shadow-lg shadow-brand/10">
    <div class="absolute -top-8 -right-8 w-32 h-32 bg-brand/10 rounded-full blur-2xl pointer-events-none"></div>

    <div class="flex items-center gap-4">
        <div class="relative flex-shrink-0">
            <div class="w-12 h-12 bg-brand/20 rounded-full border border-brand/40 flex items-center justify-center">
                <span class="material-symbols-outlined text-brand text-2xl" style="animation: ring 2s ease infinite;">system_update</span>
            </div>
        </div>
        <div>
            <p class="text-[10px] font-black text-brand uppercase tracking-widest">Nova Versão Disponível</p>
            <h4 class="text-white font-black text-base">SGIM Master v<?= htmlspecialchars($updateInfo['latest_version'] ?? $updateInfo['latest'] ?? '') ?></h4>
            <p class="text-slate-400 text-xs mt-0.5 max-w-xs">Uma nova atualização foi detectada. Clique para instalar.</p>
        </div>
    </div>

    <div class="flex items-center gap-3 flex-shrink-0">
        <a href="atualizacoes.php"
           class="bg-brand hover:bg-yellow-500 text-black px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-brand/20 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">arrow_forward</span>
            Ver Atualização
        </a>
    </div>
</div>

<style>
@keyframes ring {
    0%,100%{transform:rotate(0)}
    10%,30%{transform:rotate(-15deg)}
    20%,40%{transform:rotate(15deg)}
    50%{transform:rotate(0)}
}
</style>
<?php endif; ?>


<?php if ($pendentes_aprovacao > 0): ?>
<!-- Alerta de Cadastros Pendentes -->
<div class="mb-6 p-5 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-between shadow-lg shadow-blue-500/5 animate-in slide-in-from-top duration-500">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center text-blue-400">
            <span class="material-symbols-outlined text-2xl">person_search</span>
        </div>
        <div>
            <h4 class="text-white font-bold text-sm">Cadastros Pendentes</h4>
            <p class="text-slate-400 text-xs">Existem <b><?= $pendentes_aprovacao ?></b> novos membros aguardando sua aprovação.</p>
        </div>
    </div>
    <a href="membros.php?status=Inativo" class="bg-blue-500 hover:bg-blue-600 text-white px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all flex items-center gap-2">
        <span class="material-symbols-outlined text-base">how_to_reg</span>
        Analisar Agora
    </a>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <!-- Saúde Financeira Quick Widget -->
    <div class="bg-darkcard p-6 rounded-twelve border border-darkborder hover:border-brand/30 transition-all group shadow-sm overflow-hidden relative">
        <div class="flex items-center justify-between mb-4">
            <div class="p-2 bg-green-500/10 rounded-lg text-green-500">
                <span class="material-symbols-outlined">account_balance_wallet</span>
            </div>
            <span class="text-[10px] font-black uppercase text-gray-500">Este Mês</span>
        </div>
        <h3 class="text-gray-400 text-sm font-medium">Saúde Financeira</h3>
        <p class="text-2xl font-bold mt-1 <?= $balanco_mes >= 0 ? 'text-green-500' : 'text-red-500' ?>">
            R$ <?= number_format($balanco_mes, 2, ',', '.') ?>
        </p>
        <div class="mt-2 flex items-center gap-2">
            <div class="h-1 flex-1 bg-gray-800 rounded-full overflow-hidden">
                <?php 
                $perc = $entradas_mes > 0 ? min(100, ($saidas_mes / $entradas_mes) * 100) : 0;
                ?>
                <div class="h-full bg-red-500" style="width: <?= $perc ?>%"></div>
            </div>
            <span class="text-[9px] font-bold text-gray-500"><?= round($perc) ?>% gastos</span>
        </div>
    </div>
    <div class="bg-darkcard p-6 rounded-twelve border border-darkborder hover:border-brand/30 transition-all group shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="p-2 bg-brand/10 rounded-lg text-brand">
                <span class="material-symbols-outlined">group</span>
            </div>
            <span class="text-xs text-green-500 font-medium">Ativos</span>
        </div>
        <h3 class="text-gray-400 text-sm font-medium">Total Membros</h3>
        <p class="text-3xl font-bold mt-1 text-white"><?= number_format($total_membros, 0, ',', '.') ?></p>
        <p class="text-[11px] text-gray-500 mt-2">Membros registrados no sistema</p>
    </div>
    <div class="bg-darkcard p-6 rounded-twelve border border-darkborder hover:border-brand/30 transition-all group shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="p-2 bg-brand/10 rounded-lg text-brand">
                <span class="material-symbols-outlined">person_add</span>
            </div>
            <span class="text-xs text-brand font-medium">+30 dias</span>
        </div>
        <h3 class="text-gray-400 text-sm font-medium">Novas Entradas</h3>
        <p class="text-3xl font-bold mt-1 text-white"><?= $novos_membros ?></p>
        <p class="text-[11px] text-gray-500 mt-2">Novos membros este mês</p>
    </div>
    <div class="bg-darkcard p-6 rounded-twelve border border-darkborder hover:border-brand/30 transition-all group shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="p-2 bg-brand/10 rounded-lg text-brand">
                <span class="material-symbols-outlined">apartment</span>
            </div>
            <span class="text-xs text-gray-500 font-medium tracking-wider">Unidades</span>
        </div>
        <h3 class="text-gray-400 text-sm font-medium">Congregações</h3>
        <p class="text-3xl font-bold mt-1 text-white"><?= $total_congregacoes ?></p>
        <p class="text-[11px] text-gray-500 mt-2">Congregações cadastradas</p>
    </div>
    <div class="bg-darkcard p-6 rounded-twelve border border-darkborder hover:border-brand/30 transition-all group shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="p-2 bg-brand/10 rounded-lg text-brand">
                <span class="material-symbols-outlined">event</span>
            </div>
            <span class="text-xs text-brand font-medium uppercase"><?= $proximo_evento ? 'Em breve' : 'Agenda' ?></span>
        </div>
        <h3 class="text-gray-400 text-sm font-medium">Próximo Evento</h3>
        <p class="text-xl font-bold mt-1 text-white truncate"><?= $proximo_evento ? htmlspecialchars($proximo_evento['nome']) : 'Nenhum agendado' ?></p>
        <p class="text-[11px] text-brand mt-2 font-semibold uppercase tracking-wider">
            <?= $proximo_evento ? date('d/m • H:i', strtotime($proximo_evento['data_evento'])) . ' • ' . htmlspecialchars($proximo_evento['local']) : 'Sem eventos' ?>
        </p>
    </div>
</section>

<?php
require_once 'includes/footer.php';
?>
