<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');
$acao = $_GET['acao'] ?? 'print';

$meses = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];
$nome_mes = $meses[$mes] ?? 'Mês ' . $mes;

// Query
$stmt = $pdo->prepare("
    SELECT ft.*, m.nome as membro_nome 
    FROM financeiro_transacoes ft 
    LEFT JOIN membros m ON ft.membro_id = m.id 
    WHERE MONTH(ft.data_transacao) = ? AND YEAR(ft.data_transacao) = ?
    ORDER BY ft.data_transacao ASC
");
$stmt->execute([$mes, $ano]);
$transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_entrada = 0;
$total_saida = 0;

foreach ($transacoes as $t) {
    if ($t['tipo'] == 'entrada') $total_entrada += $t['valor'];
    if ($t['tipo'] == 'saida') $total_saida += $t['valor'];
}
$saldo = $total_entrada - $total_saida;

// Exportar CSV
if ($acao === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Relatorio_Financeiro_' . $mes . '_' . $ano . '.csv');
    // Adiciona BOM para o Excel abrir UTF-8 corretamente
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Data', 'Tipo', 'Categoria', 'Identificacao (Membro/Outro)', 'Descricao', 'Valor (R$)']);
    foreach ($transacoes as $t) {
        $identificacao = !empty($t['membro_nome']) ? $t['membro_nome'] : (!empty($t['nome_identificado']) ? $t['nome_identificado'] : 'Nao identificado');
        fputcsv($output, [
            date('d/m/Y', strtotime($t['data_transacao'])),
            ucfirst($t['tipo']),
            $t['categoria'],
            $identificacao,
            $t['descricao'],
            number_format($t['valor'], 2, ',', '.')
        ]);
    }
    fputcsv($output, ['', '', '', '', 'Total Entradas:', number_format($total_entrada, 2, ',', '.')]);
    fputcsv($output, ['', '', '', '', 'Total Saidas:', number_format($total_saida, 2, ',', '.')]);
    fputcsv($output, ['', '', '', '', 'Saldo Liquido:', number_format($saldo, 2, ',', '.')]);
    fclose($output);
    exit;
}

// Configurações do Tema (Logos e Cores)
$cor_brand = '#FFC107';
$logo_url = '';
try {
    $stmtTema = $pdo->query("SELECT * FROM configuracoes_tema WHERE id=1");
    if ($stmtTema && $tema_db = $stmtTema->fetch(PDO::FETCH_ASSOC)) {
        $cor_brand = $tema_db['cor_brand'] ?? '#FFC107';
        $logo_url = !empty($tema_db['logo_url']) ? htmlspecialchars($tema_db['logo_url']) : '';
    }
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Financeiro - <?= $nome_mes ?>/<?= $ano ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');
        body { font-family: 'Inter', sans-serif; background: #050505; color: #e5e7eb; padding: 40px; margin: 0; }
        .header { text-align: center; margin-bottom: 40px; border-bottom: 1px solid #1e1e1e; padding-bottom: 30px; }
        .header h1 { margin: 0; font-size: 28px; color: #fff; font-weight: 800; letter-spacing: -0.025em; }
        .header p { margin: 8px 0 0; color: #9ca3af; font-size: 14px; text-transform: uppercase; tracking: 0.1em; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 40px; border: 1px solid #1e1e1e; border-radius: 12px; overflow: hidden; background: #121212; }
        th, td { padding: 16px; text-align: left; font-size: 13px; border-bottom: 1px solid #1e1e1e; }
        th { background: #1a1a1a; font-weight: 600; color: #9ca3af; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; }
        tr:last-child td { border-bottom: none; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .entrada { color: #10B981; font-weight: 700; }
        .saida { color: #EF4444; font-weight: 700; }
        .summary { display: flex; justify-content: flex-end; gap: 24px; margin-top: 30px; }
        .summary div { padding: 20px; border: 1px solid #1e1e1e; border-radius: 16px; background: #121212; min-width: 180px; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .summary .total-saldo { background: #FFC107; color: #000; border: none; }
        .summary .total-saldo div { background: transparent; border: none; box-shadow: none; color: inherit; min-width: 0; padding: 0; }
        .no-print { text-align: center; margin-bottom: 30px; display: flex; justify-content: center; gap: 12px; }
        .no-print button, .no-print a { padding: 12px 24px; background: #121212; color: #fff; border: 1px solid #1e1e1e; border-radius: 10px; cursor: pointer; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.2s; }
        .no-print button:hover, .no-print a:hover { background: #1a1a1a; border-color: #FFC107; color: #FFC107; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; background: #fff !important; color: #000 !important; }
            table, tr, td, th { border-color: #ddd !important; color: #000 !important; background: #fff !important; }
            .summary div { border-color: #ddd !important; background: #fff !important; color: #000 !important; }
            .summary .total-saldo { border: 1px solid #000 !important; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🖨️ Imprimir (Gerar PDF)</button>
        <a href="?mes=<?= $mes ?>&ano=<?= $ano ?>&acao=csv">📊 Exportar Excel (CSV)</a>
        <a href="financeiro.php" style="background:#ddd; color:#333;">Voltar</a>
    </div>

    <div class="header">
        <?php if (!empty($logo_url)): ?>
            <img src="<?= $logo_url ?>" alt="Logo Oficial" style="max-height: 70px; margin-bottom: 15px; object-fit: contain;" />
        <?php endif; ?>
        <h1>📊 Relatório Financeiro Consolidado</h1>
        <p>Período: <strong><?= $nome_mes ?> de <?= $ano ?></strong></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Categoria</th>
                <th>Identificação (Membro/Avulso)</th>
                <th>Descrição / Notas</th>
                <th class="text-right">Valor</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($transacoes) > 0): ?>
                <?php foreach ($transacoes as $t): ?>
                    <?php 
                        $identificacao = !empty($t['membro_nome']) ? $t['membro_nome'] : (!empty($t['nome_identificado']) ? $t['nome_identificado'] : '<span style="color:#999;font-style:italic">Anônimo</span>'); 
                        $classe_valor = $t['tipo'] == 'entrada' ? 'entrada' : 'saida';
                        $sinal = $t['tipo'] == 'entrada' ? '+' : '-';
                    ?>
                    <tr>
                        <td class="text-center"><?= date('d/m/Y', strtotime($t['data_transacao'])) ?></td>
                        <td><?= htmlspecialchars($t['categoria']) ?></td>
                        <td><?= $identificacao ?></td>
                        <td><?= htmlspecialchars($t['descricao']) ?></td>
                        <td class="text-right <?= $classe_valor ?>">
                            <?= $sinal ?> R$ <?= number_format($t['valor'], 2, ',', '.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center" style="padding: 30px; color: #666;">Nenhuma transação movimentada neste mês.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="summary">
        <div>
            <div style="font-size: 11px; color: #666; text-transform: uppercase;">Total Entradas</div>
            <div class="entrada">R$ <?= number_format($total_entrada, 2, ',', '.') ?></div>
        </div>
        <div>
            <div style="font-size: 11px; color: #666; text-transform: uppercase;">Total Saídas</div>
            <div class="saida">- R$ <?= number_format($total_saida, 2, ',', '.') ?></div>
        </div>
        <div class="total-saldo">
            <div style="font-size: 11px; opacity: 0.8; text-transform: uppercase;">Saldo Líquido</div>
            <div style="font-size: 20px;">R$ <?= number_format($saldo, 2, ',', '.') ?></div>
        </div>
    </div>
</body>
</html>
