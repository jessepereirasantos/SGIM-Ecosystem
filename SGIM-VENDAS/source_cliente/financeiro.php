<?php
ob_start();
session_start();
require_once __DIR__ . '/config/database.php';

// Verificação de Autenticação e Conexão de Banco (v1.4.9 logic)
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 🛡️ Inicializa o AccessManager para proteção de rota antecipada
if (!class_exists('SGIM\\Auth\\AccessManager')) {
    $amPath = __DIR__ . '/src/Auth/AccessManager.php';
    if (file_exists($amPath)) require_once $amPath;
}
$access = new \SGIM\Auth\AccessManager($pdo, $_SESSION['user_id']);

// Validação antecipada de leitura
if ($access && !$access->can('financeiro', 'visualizar')) {
    echo "<script>alert('Acesso Negado: Você não tem permissão para ver o Financeiro.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Obter somatórias
$mes_atual = $_GET['mes'] ?? date('m');
$ano_atual = $_GET['ano'] ?? date('Y');

// Ajuste de queries para compatibilidade (MySQL/SQLite)
$is_sqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');

try {
    // 🔍 FILTRO DE ESCOPO (Global vs Local)
    $scopeFilter = $access ? $access->getScopeFilter() : '';

    if ($is_sqlite) {
        $stmtEntradas = $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM financeiro_transacoes WHERE tipo = 'entrada' AND deleted_at IS NULL AND strftime('%m', data_transacao) = '{$mes_atual}' AND strftime('%Y', data_transacao) = '{$ano_atual}' $scopeFilter");
        $stmtSaidas = $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM financeiro_transacoes WHERE tipo = 'saida' AND deleted_at IS NULL AND strftime('%m', data_transacao) = '{$mes_atual}' AND strftime('%Y', data_transacao) = '{$ano_atual}' $scopeFilter");
    } else {
        $stmtEntradas = $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM financeiro_transacoes WHERE tipo = 'entrada' AND deleted_at IS NULL AND MONTH(data_transacao) = {$mes_atual} AND YEAR(data_transacao) = {$ano_atual} $scopeFilter");
        $stmtSaidas = $pdo->query("SELECT COALESCE(SUM(valor), 0) FROM financeiro_transacoes WHERE tipo = 'saida' AND deleted_at IS NULL AND MONTH(data_transacao) = {$mes_atual} AND YEAR(data_transacao) = {$ano_atual} $scopeFilter");
    }

    $entradas_mes = $stmtEntradas ? $stmtEntradas->fetchColumn() : 0;
    $saidas_mes = $stmtSaidas ? $stmtSaidas->fetchColumn() : 0;

    // Saldo Total respeitando escopo e exclusão lógica
    $stmtSaldoTotal = $pdo->query("SELECT COALESCE((SELECT SUM(valor) FROM financeiro_transacoes WHERE tipo = 'entrada' AND deleted_at IS NULL " . $scopeFilter . "), 0) - COALESCE((SELECT SUM(valor) FROM financeiro_transacoes WHERE tipo = 'saida' AND deleted_at IS NULL " . $scopeFilter . "), 0)");
    $saldo_total = $stmtSaldoTotal ? $stmtSaldoTotal->fetchColumn() : 0;

    // Obter ultimas transações respeitando escopo e exclusão lógica
    $stmtTransacoes = $pdo->query("SELECT ft.*, m.nome as membro_nome FROM financeiro_transacoes ft LEFT JOIN membros m ON ft.membro_id = m.id WHERE ft.deleted_at IS NULL " . str_replace('AND', 'AND ft.', $scopeFilter) . " ORDER BY ft.data_transacao DESC, ft.id DESC LIMIT 10");
    $transacoes = $stmtTransacoes ? $stmtTransacoes->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $t) {
    error_log("Financeiro Data Error: " . $t->getMessage());
    $entradas_mes = 0;
    $saidas_mes = 0;
    $saldo_total = 0;
    $transacoes = [];
}

require_once __DIR__ . '/includes/header.php';
?>

    <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 1): ?>
        <div class="mb-6 p-4 rounded-twelve bg-green-500/10 border border-green-500/20 text-green-400 flex items-center gap-3">
            <span class="material-symbols-outlined">check_circle</span>
            <p class="text-sm font-semibold">Transação registrada com sucesso!</p>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder hover:border-brand/30 transition-all group shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-blue-500/10 rounded-lg text-blue-500">
                    <span class="material-symbols-outlined">account_balance</span>
                </div>
            </div>
            <h3 class="text-gray-400 text-sm font-medium">Saldo em Caixa</h3>
            <p class="text-3xl font-bold mt-1 text-white">R$ <?= number_format($saldo_total, 2, ',', '.') ?></p>
            <p class="text-[11px] text-gray-500 mt-2">Volume total disponível</p>
        </div>
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder hover:border-brand/30 transition-all group shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-brand/10 rounded-lg text-brand">
                    <span class="material-symbols-outlined">volunteer_activism</span>
                </div>
            </div>
            <h3 class="text-gray-400 text-sm font-medium">Entradas do Mês</h3>
            <p class="text-3xl font-bold mt-1 text-white">R$ <?= number_format($entradas_mes, 2, ',', '.') ?></p>
            <p class="text-[11px] text-gray-500 mt-2">Dízimos e Ofertas recebidas</p>
        </div>
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder hover:border-brand/30 transition-all group shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-purple-500/10 rounded-lg text-purple-500">
                    <span class="material-symbols-outlined">shopping_cart_checkout</span>
                </div>
            </div>
            <h3 class="text-gray-400 text-sm font-medium">Despesas do Mês</h3>
            <p class="text-3xl font-bold mt-1 text-white">R$ <?= number_format($saidas_mes, 2, ',', '.') ?></p>
            <p class="text-[11px] text-gray-500 mt-2">Pagamentos e custos operacionais</p>
        </div>
    </div>

    <!-- Main Grid: Chart & Transactions -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Chart Area -->
        <div class="lg:col-span-2 bg-darkcard p-6 rounded-twelve border border-darkborder shadow-sm">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-lg font-bold text-white">Evolução de Saldo</h3>
                <span class="text-xs px-3 py-1.5 text-gray-400">Gráfico ilustrativo</span>
            </div>
            <div class="h-64 flex items-end justify-between gap-4 px-2">
                <div class="flex-1 bg-brand/20 rounded-t h-[40%] relative group">
                    <p class="absolute -bottom-6 left-1/2 -translate-x-1/2 text-[10px] uppercase font-bold text-gray-600">Jan</p>
                </div>
                <div class="flex-1 bg-brand/20 rounded-t h-[55%] relative group">
                    <p class="absolute -bottom-6 left-1/2 -translate-x-1/2 text-[10px] uppercase font-bold text-gray-600">Fev</p>
                </div>
                <div class="flex-1 bg-brand/20 rounded-t h-[50%] relative group">
                    <p class="absolute -bottom-6 left-1/2 -translate-x-1/2 text-[10px] uppercase font-bold text-gray-600">Mar</p>
                </div>
                <div class="flex-1 bg-brand/20 rounded-t h-[70%] relative group">
                    <p class="absolute -bottom-6 left-1/2 -translate-x-1/2 text-[10px] uppercase font-bold text-gray-600">Abr</p>
                </div>
                <div class="flex-1 bg-brand/20 rounded-t h-[65%] relative group">
                    <p class="absolute -bottom-6 left-1/2 -translate-x-1/2 text-[10px] uppercase font-bold text-gray-600">Mai</p>
                </div>
                <div class="flex-1 bg-brand rounded-t h-[85%] relative group">
                    <p class="absolute -bottom-6 left-1/2 -translate-x-1/2 text-[10px] uppercase font-bold text-brand">Jun</p>
                </div>
            </div>
        </div>

        <!-- Quick Access -->
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-white">Rápido Acesso</h3>
            </div>
            <div class="space-y-4">
                <a href="transacao_nova.php?tipo=entrada" class="w-full flex items-center gap-4 p-4 rounded-twelve bg-darkbg hover:bg-white/5 transition-all border border-darkborder group block">
                    <div class="size-10 rounded-full bg-brand flex items-center justify-center text-black shadow-lg shadow-brand/10">
                        <span class="material-symbols-outlined">add</span>
                    </div>
                    <div class="text-left">
                        <p class="font-bold text-sm text-white group-hover:text-brand transition-colors">Nova Entrada</p>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider">Dízimo ou Oferta</p>
                    </div>
                </a>
                <a href="transacao_nova.php?tipo=saida" class="w-full flex items-center gap-4 p-4 rounded-twelve bg-darkbg hover:bg-white/5 transition-all border border-darkborder group block">
                    <div class="size-10 rounded-full bg-red-500 flex items-center justify-center text-white">
                        <span class="material-symbols-outlined">remove</span>
                    </div>
                    <div class="text-left">
                        <p class="font-bold text-sm text-red-500">Nova Despesa</p>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider">Pagamento ou Custo</p>
                    </div>
                </a>
                <a href="financeiro_relatorio.php?mes=<?= $mes_atual ?>&ano=<?= $ano_atual ?>" target="_blank" class="w-full flex items-center gap-4 p-4 rounded-twelve bg-darkbg hover:bg-white/5 transition-all border border-darkborder group block">
                    <div class="size-10 rounded-full bg-gray-700 flex items-center justify-center text-gray-300">
                        <span class="material-symbols-outlined">description</span>
                    </div>
                    <div class="text-left">
                        <p class="font-bold text-sm text-gray-300 group-hover:text-white transition-colors">Gerar PDF / Imprimir</p>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider">Relatório mensal</p>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Transactions Table -->
    <div class="bg-darkcard rounded-twelve border border-darkborder shadow-sm overflow-hidden mt-8">
        <div class="px-6 py-4 border-b border-darkborder flex items-center justify-between">
            <h3 class="text-lg font-bold text-white">Transações Recentes</h3>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <input class="bg-darkbg border-darkborder focus:border-brand focus:ring-brand rounded-twelve text-xs px-4 py-2 w-48 text-gray-300" placeholder="Filtrar..." type="text"/>
                </div>
                <button class="text-brand text-xs font-semibold hover:underline uppercase tracking-widest">Ver tudo</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-white/5 text-gray-400 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 font-semibold">Categoria</th>
                    <th class="px-6 py-4 font-semibold text-center">Tipo</th>
                    <th class="px-6 py-4 font-semibold">Data</th>
                    <th class="px-6 py-4 font-semibold text-right">Valor</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-darkborder">
                <?php if (count($transacoes) > 0): ?>
                    <?php foreach ($transacoes as $t): ?>
                        <tr class="hover:bg-white/[0.02] transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <?php if ($t['tipo'] == 'entrada'): ?>
                                        <div class="size-8 rounded bg-brand/10 flex items-center justify-center text-brand">
                                            <span class="material-symbols-outlined text-sm">volunteer_activism</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="size-8 rounded bg-red-500/10 flex items-center justify-center text-red-500">
                                            <span class="material-symbols-outlined text-sm">bolt</span>
                                        </div>
                                    <?php endif; ?>
                                    <span class="font-medium text-gray-300 group-hover:text-brand transition-colors">
                                        <?= htmlspecialchars($t['categoria']) ?> 
                                        <?php if (!empty($t['membro_nome']) || !empty($t['nome_identificado'])): ?>
                                            <span class="ml-2 px-2 py-0.5 rounded text-[10px] bg-brand/20 text-brand"><?= htmlspecialchars($t['membro_nome'] ?? $t['nome_identificado']) ?></span>
                                        <?php endif; ?>
                                        <br>
                                        <span class="text-[10px] text-gray-500"><?= htmlspecialchars($t['descricao']) ?></span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($t['tipo'] == 'entrada'): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-green-500/10 text-green-400 border border-green-500/20">Entrada</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-red-500/10 text-red-500 border border-red-500/20">Despesa</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= date('d/m/Y', strtotime($t['data_transacao'])) ?></td>
                            <td class="px-6 py-4 text-right font-bold text-white">
                                <?php if ($t['tipo'] == 'entrada'): ?>
                                    <span class="text-green-500">+ R$ <?= number_format($t['valor'], 2, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="text-red-500">- R$ <?= number_format($t['valor'], 2, ',', '.') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">Nenhuma transação registrada.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
