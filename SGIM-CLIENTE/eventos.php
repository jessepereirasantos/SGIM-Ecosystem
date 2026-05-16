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

// Fetch statistics
$mes_atual = date('m');
$ano_atual = date('Y');

$is_sqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');

try {
    if ($is_sqlite) {
        $stmtCount = $pdo->query("SELECT COUNT(*) FROM eventos WHERE strftime('%m', data_inicio) = '{$mes_atual}' AND strftime('%Y', data_inicio) = '{$ano_atual}'");
        $stmtNext = $pdo->query("SELECT titulo, data_inicio, local FROM eventos WHERE data_inicio >= datetime('now') AND status = 'Agendado' ORDER BY data_inicio ASC LIMIT 1");
    } else {
        $stmtCount = $pdo->query("SELECT COUNT(*) FROM eventos WHERE MONTH(data_inicio) = {$mes_atual} AND YEAR(data_inicio) = {$ano_atual}");
        $stmtNext = $pdo->query("SELECT titulo, data_inicio, local FROM eventos WHERE data_inicio >= NOW() AND status = 'Agendado' ORDER BY data_inicio ASC LIMIT 1");
    }

    $eventos_mes = $stmtCount ? $stmtCount->fetchColumn() : 0;
    $proximo_evento = $stmtNext ? $stmtNext->fetch(PDO::FETCH_ASSOC) : null;

    // Fetch eventos list
    $stmtEventos = $pdo->query("SELECT * FROM eventos ORDER BY data_inicio ASC");
    $eventos = $stmtEventos ? $stmtEventos->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $t) {
    error_log("Eventos Data Error: " . $t->getMessage());
    $eventos_mes = 0;
    $proximo_evento = null;
    $eventos = [];
}

$page_title = 'SGIM - Lista de Eventos';
$current_page = 'eventos';

require_once __DIR__ . '/includes/header.php';
?>
    <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 1): ?>
        <div class="mb-6 p-4 rounded-twelve bg-green-500/10 border border-green-500/20 text-green-400 flex items-center gap-3">
            <span class="material-symbols-outlined">check_circle</span>
            <p class="text-sm font-semibold">Evento criado com sucesso!</p>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-darkcard p-6 rounded-xl border border-darkborder shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-brand/10 rounded-lg text-brand">
                    <span class="material-symbols-outlined">calendar_month</span>
                </div>
            </div>
            <p class="text-sm text-gray-500 font-medium font-bold uppercase tracking-widest">Eventos no Mês</p>
            <span class="text-3xl font-black text-white"><?= $eventos_mes ?></span>
        </div>
        <div class="bg-darkcard p-6 rounded-xl border border-darkborder shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-blue-500/10 rounded-lg text-blue-500">
                    <span class="material-symbols-outlined">visibility</span>
                </div>
            </div>
            <p class="text-xs text-gray-500 font-bold uppercase tracking-widest">Publicados no Portal</p>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-white"><?= count(array_filter($eventos, fn($e) => $e['publico'])) ?></span>
            </div>
        </div>
        <div class="bg-darkcard p-6 rounded-xl border border-darkborder border-l-4 border-l-brand relative overflow-hidden">
            <div class="absolute -right-4 -bottom-4 opacity-5">
                <span class="material-symbols-outlined text-8xl text-brand">campaign</span>
            </div>
            <p class="text-[10px] text-brand font-black uppercase tracking-widest mb-1">Próximo Destaque</p>
            <div class="flex flex-col">
                <?php if ($proximo_evento): ?>
                    <span class="text-lg font-black truncate text-white"><?= htmlspecialchars($proximo_evento['titulo']) ?></span>
                    <span class="text-[11px] text-gray-400 font-bold uppercase mt-1"><?= date('d M, H:i', strtotime($proximo_evento['data_inicio'])) ?> • <?= htmlspecialchars($proximo_evento['local']) ?></span>
                <?php else: ?>
                    <span class="text-lg font-black truncate text-white">Nenhum evento</span>
                    <span class="text-[11px] text-gray-500 uppercase font-bold mt-1 tracking-widest">Tudo em dia</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="bg-darkcard rounded-twelve border border-darkborder overflow-hidden shadow-sm">
        <div class="p-6 flex items-center justify-between border-b border-darkborder bg-white/[0.02]">
            <div>
                <h2 class="text-xl font-black text-white">Agenda de Atividades</h2>
                <p class="text-xs text-gray-500 uppercase tracking-wider">Gestão centralizada de cultos, reuniões e congressos</p>
            </div>
            <a href="evento_novo.php" class="bg-brand hover:bg-yellow-500 text-black px-8 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition-all flex items-center gap-2 shadow-xl shadow-brand/10">
                <span class="material-symbols-outlined text-sm">add</span>
                Novo Evento
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                <tr class="bg-white/5">
                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Evento / Banner</th>
                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Data e Hora</th>
                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Local</th>
                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400">Portal</th>
                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-400 text-right">Ações</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-darkborder">
                <?php if (count($eventos) > 0): ?>
                    <?php foreach ($eventos as $e): ?>
                        <tr class="hover:bg-white/[0.02] transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4">
                                    <div class="size-16 rounded-lg bg-darkbg border border-darkborder overflow-hidden flex-shrink-0 flex items-center justify-center">
                                        <?php if (!empty($e['banner_url'])): ?>
                                            <img src="<?= $e['banner_url'] ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <span class="material-symbols-outlined text-gray-700">image</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-white group-hover:text-brand transition-colors"><?= htmlspecialchars($e['titulo']) ?></div>
                                        <div class="text-[10px] text-gray-500 uppercase font-bold tracking-tight"><?= htmlspecialchars($e['tipo'] ?? 'Outro') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-bold text-gray-300"><?= date('d/m/Y', strtotime($e['data_inicio'])) ?></span><br>
                                <span class="text-xs text-gray-500"><?= date('H:i', strtotime($e['data_inicio'])) ?>h</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-400 font-medium"><?= htmlspecialchars($e['local']) ?></td>
                            <td class="px-6 py-4">
                                <?php if ($e['publico']): ?>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase bg-green-500/10 text-green-400 border border-green-500/20">
                                        <span class="size-1.5 rounded-full bg-green-400 animate-pulse"></span>
                                        Visível
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase bg-gray-500/10 text-gray-500 border border-darkborder">
                                        Privado
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button class="p-2 text-gray-500 hover:text-brand transition-all hover:bg-brand/10 rounded-lg">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <button class="p-2 text-gray-500 hover:text-red-500 transition-all hover:bg-red-500/10 rounded-lg">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <span class="material-symbols-outlined text-5xl text-gray-800">event_busy</span>
                                <p class="text-gray-500 font-medium">Nenhum evento registrado no momento.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
