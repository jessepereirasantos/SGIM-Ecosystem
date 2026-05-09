<?php
ob_start();
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = 'SGIM - Crescimento & Discipulado';
$current_page = 'crescimento';
$ano_atual = $_GET['ano'] ?? date('Y');

try {
    // Total Membros Ativos
    $stmtAtivos = $pdo->query("SELECT COUNT(id) FROM membros WHERE status = 'Ativo'");
    $total_membros = $stmtAtivos ? $stmtAtivos->fetchColumn() : 0;

    // Conversões no Ano
    $stmtConver = $pdo->prepare("SELECT COUNT(id) FROM membros WHERE YEAR(data_conversao) = ?");
    $stmtConver->execute([$ano_atual]);
    $total_conversoes = $stmtConver->fetchColumn();

    // Batismos no Ano
    $stmtBati = $pdo->prepare("SELECT COUNT(id) FROM membros WHERE YEAR(data_batismo) = ?");
    $stmtBati->execute([$ano_atual]);
    $total_batismos = $stmtBati->fetchColumn();

    // Chart Data (Conversions per month)
    $dados_meses = array_fill(1, 12, 0);
    $stmtGrafico = $pdo->prepare("SELECT MONTH(data_conversao) as m, COUNT(id) as c FROM membros WHERE YEAR(data_conversao) = ? AND data_conversao IS NOT NULL GROUP BY MONTH(data_conversao)");
    $stmtGrafico->execute([$ano_atual]);
    while($row = $stmtGrafico->fetch(PDO::FETCH_ASSOC)) {
        $dados_meses[$row['m']] = $row['c'];
    }

    $dados_meses_batismo = array_fill(1, 12, 0);
    $stmtGraficoB = $pdo->prepare("SELECT MONTH(data_batismo) as m, COUNT(id) as c FROM membros WHERE YEAR(data_batismo) = ? AND data_batismo IS NOT NULL GROUP BY MONTH(data_batismo)");
    $stmtGraficoB->execute([$ano_atual]);
    while($row = $stmtGraficoB->fetch(PDO::FETCH_ASSOC)) {
        $dados_meses_batismo[$row['m']] = $row['c'];
    }
} catch (Throwable $t) {
    // Em caso de banco desatualizado (sqlite vs mysql ou coluna faltando)
    $total_membros = 0; $total_conversoes = 0; $total_batismos = 0;
    $dados_meses = array_fill(1, 12, 0);
    $dados_meses_batismo = array_fill(1, 12, 0);
}

// Convert data to JSON for Chart.js
$js_conversoes = json_encode(array_values($dados_meses));
$js_batismos = json_encode(array_values($dados_meses_batismo));

$extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
require_once 'includes/header.php';
?>

    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight">Crescimento & Discipulado</h2>
            <p class="text-sm text-gray-500 mt-1">Monitore o avanço espiritual e numérico da congregação.</p>
        </div>
        <form method="GET" class="flex items-center gap-4">
            <select name="ano" onchange="this.form.submit()" class="bg-darkbg border border-darkborder rounded-twelve px-4 py-2 text-white focus:border-brand outline-none">
                <?php $ano_inicio = date('Y') - 5; for($a = date('Y') + 1; $a >= $ano_inicio; $a--): ?>
                    <option value="<?= $a ?>" <?= $a == $ano_atual ? 'selected' : '' ?>><?= $a ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder hover:border-blue-500/30 transition-all group shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-blue-500/10 rounded-lg text-blue-500">
                    <span class="material-symbols-outlined">group_add</span>
                </div>
            </div>
            <h3 class="text-gray-400 text-sm font-medium">Novas Conversões</h3>
            <p class="text-3xl font-bold mt-1 text-white"><?= $total_conversoes ?></p>
            <p class="text-[11px] text-gray-500 mt-2">Almas alcançadas no ano de <?= $ano_atual ?></p>
        </div>
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder hover:border-brand/30 transition-all group shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-brand/10 rounded-lg text-brand">
                    <span class="material-symbols-outlined">water_drop</span>
                </div>
            </div>
            <h3 class="text-gray-400 text-sm font-medium">Batismos</h3>
            <p class="text-3xl font-bold mt-1 text-white"><?= $total_batismos ?></p>
            <p class="text-[11px] text-gray-500 mt-2">Membros batizados no ano de <?= $ano_atual ?></p>
        </div>
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder hover:border-green-500/30 transition-all group shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="p-2 bg-green-500/10 rounded-lg text-green-500">
                    <span class="material-symbols-outlined">emoji_people</span>
                </div>
            </div>
            <h3 class="text-gray-400 text-sm font-medium">Membros Ativos (Total)</h3>
            <p class="text-3xl font-bold mt-1 text-white"><?= $total_membros ?></p>
            <p class="text-[11px] text-gray-500 mt-2">Quadro geral atualizado</p>
        </div>
    </div>

    <!-- Main Grid: Chart & Transactions -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Chart Area -->
        <div class="lg:col-span-2 bg-darkcard p-6 rounded-twelve border border-darkborder shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-white">Evolução Anual (<?= $ano_atual ?>)</h3>
            </div>
            <div class="relative h-72 w-full">
                <canvas id="growthChart"></canvas>
            </div>
        </div>

        <!-- Quick Access -->
        <div class="bg-darkcard p-6 rounded-twelve border border-darkborder shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-white">Ações Diretas</h3>
            </div>
            <div class="space-y-4">
                <a href="membro_novo.php" class="w-full flex items-center gap-4 p-4 rounded-twelve bg-darkbg hover:bg-white/5 transition-all border border-darkborder group block">
                    <div class="size-10 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-500 shadow-lg">
                        <span class="material-symbols-outlined">person_add</span>
                    </div>
                    <div class="text-left">
                        <p class="font-bold text-sm text-white group-hover:text-blue-500 transition-colors">Cadastrar Decisão</p>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider">Registrar novo convertido</p>
                    </div>
                </a>
                
                <a href="crescimento_relatorio.php?ano=<?= $ano_atual ?>" target="_blank" class="w-full flex items-center gap-4 p-4 rounded-twelve bg-darkbg hover:bg-white/5 transition-all border border-darkborder group block">
                    <div class="size-10 rounded-full bg-brand flex items-center justify-center text-black">
                        <span class="material-symbols-outlined">picture_as_pdf</span>
                    </div>
                    <div class="text-left">
                        <p class="font-bold text-sm text-white group-hover:text-brand transition-colors">Gerar Relatório Profissional</p>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider">Dossiê de Crescimento (PDF/Excel)</p>
                    </div>
                </a>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('growthChart').getContext('2d');
    
    // Gradiente Conversões
    let gradientC = ctx.createLinearGradient(0, 0, 0, 400);
    gradientC.addColorStop(0, 'rgba(59, 130, 246, 0.5)'); // Blue
    gradientC.addColorStop(1, 'rgba(59, 130, 246, 0.0)');
    
    // Gradiente Batismos
    let gradientB = ctx.createLinearGradient(0, 0, 0, 400);
    gradientB.addColorStop(0, 'rgba(255, 193, 7, 0.5)'); // Brand
    gradientB.addColorStop(1, 'rgba(255, 193, 7, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
            datasets: [
                {
                    label: 'Conversões',
                    data: <?= $js_conversoes ?>,
                    borderColor: '#3B82F6',
                    backgroundColor: gradientC,
                    borderWidth: 3,
                    pointBackgroundColor: '#3B82F6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Batismos',
                    data: <?= $js_batismos ?>,
                    borderColor: '#FFC107',
                    backgroundColor: gradientB,
                    borderWidth: 3,
                    pointBackgroundColor: '#FFC107',
                    pointBorderColor: '#000',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#9CA3AF', font: { family: 'Inter', size: 12 } }
                },
                tooltip: {
                    backgroundColor: '#1E1E1E',
                    titleColor: '#fff',
                    bodyColor: '#ccc',
                    borderColor: '#333',
                    borderWidth: 1,
                    padding: 12
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: '#6B7280', stepSize: 1 },
                    grid: { color: '#1E1E1E', drawBorder: false }
                },
                x: {
                    ticks: { color: '#6B7280' },
                    grid: { display: false, drawBorder: false }
                }
            }
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
