<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

$ano = $_GET['ano'] ?? date('Y');
$acao = $_GET['acao'] ?? 'print';

// Queries
$stmtConver = $pdo->prepare("SELECT nome, data_conversao FROM membros WHERE YEAR(data_conversao) = ? AND status='Ativo' ORDER BY data_conversao ASC");
$stmtConver->execute([$ano]);
$conversoes = $stmtConver->fetchAll(PDO::FETCH_ASSOC);

$stmtBati = $pdo->prepare("SELECT nome, data_batismo FROM membros WHERE YEAR(data_batismo) = ? AND status='Ativo' ORDER BY data_batismo ASC");
$stmtBati->execute([$ano]);
$batismos = $stmtBati->fetchAll(PDO::FETCH_ASSOC);

// Exportar CSV
if ($acao === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Relatorio_Crescimento_' . $ano . '.csv');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['--- RELATORIO DE CONVERSOES (' . $ano . ') ---']);
    fputcsv($output, ['Data', 'Nome do Novo Convertido']);
    foreach ($conversoes as $c) {
        fputcsv($output, [date('d/m/Y', strtotime($c['data_conversao'])), $c['nome']]);
    }
    fputcsv($output, ['Total Conversões:', count($conversoes)]);
    fputcsv($output, []);
    
    fputcsv($output, ['--- RELATORIO DE BATISMOS (' . $ano . ') ---']);
    fputcsv($output, ['Data', 'Nome do Batizado']);
    foreach ($batismos as $b) {
        fputcsv($output, [date('d/m/Y', strtotime($b['data_batismo'])), $b['nome']]);
    }
    fputcsv($output, ['Total Batismos:', count($batismos)]);
    
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
    <title>Dossiê de Crescimento - <?= $ano ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');
        body { font-family: 'Inter', sans-serif; background: #050505; color: #e5e7eb; padding: 40px; margin: 0; }
        .header { text-align: center; margin-bottom: 40px; border-bottom: 1px solid #1e1e1e; padding-bottom: 30px; }
        .header h1 { margin: 0; font-size: 28px; color: #fff; font-weight: 800; letter-spacing: -0.025em; }
        .header p { margin: 8px 0 0; color: #9ca3af; font-size: 14px; text-transform: uppercase; tracking: 0.1em; }
        
        .section-title { font-size: 14px; font-weight: 800; color: #fff; border-left: 4px solid <?= $cor_brand ?>; padding-left: 12px; margin-top: 50px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.05em; }
        
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 40px; border: 1px solid #1e1e1e; border-radius: 12px; overflow: hidden; background: #121212; }
        th, td { padding: 16px; text-align: left; font-size: 13px; border-bottom: 1px solid #1e1e1e; }
        th { background: #1a1a1a; font-weight: 600; color: #9ca3af; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; }
        tr:last-child td { border-bottom: none; }
        .text-center { text-align: center; width: 150px; }
        
        .no-print { text-align: center; margin-bottom: 30px; display: flex; justify-content: center; gap: 12px; }
        .no-print button, .no-print a { padding: 12px 24px; background: #121212; color: #fff; border: 1px solid #1e1e1e; border-radius: 10px; cursor: pointer; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.2s; }
        .no-print button:hover, .no-print a:hover { background: #1a1a1a; border-color: <?= $cor_brand ?>; color: <?= $cor_brand ?>; }
        
        .summary-box { background: #121212; border: 1px solid #1e1e1e; padding: 24px; text-align: center; border-radius: 16px; flex: 1; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .summary-box h3 { margin: 0; font-size: 42px; color: #3B82F6; font-weight: 800; }
        .summary-box.bat h3 { color: #F59E0B; }
        .summary-box p { margin: 8px 0 0; font-size: 11px; text-transform: uppercase; color: #6b7280; font-weight: 700; letter-spacing: 0.1em; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; background: #fff !important; color: #000 !important; }
            table, tr, td, th { border-color: #ddd !important; color: #000 !important; background: #fff !important; }
            .section-title { color: #000 !important; border-color: #000 !important; }
            .summary-box { border: 1px solid #ddd !important; background: #fff !important; }
            .summary-box h3, .summary-box p { color: #000 !important; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🖨️ Imprimir Dossiê (PDF)</button>
        <a href="?ano=<?= $ano ?>&acao=csv">📊 Exportar Estrutura (CSV)</a>
        <a href="crescimento.php" style="background:#ddd; color:#333;">Voltar</a>
    </div>

    <div class="header">
        <?php if (!empty($logo_url)): ?>
            <img src="<?= $logo_url ?>" alt="Logo Oficial" style="max-height: 70px; margin-bottom: 15px; object-fit: contain;" />
        <?php endif; ?>
        <h1>📈 Dossiê Oficial de Crescimento</h1>
        <p>Relatório Consolidado Ministerial do Ano de <strong><?= $ano ?></strong></p>
    </div>
    
    <div style="display: flex; gap: 20px; margin-bottom: 40px;">
        <div class="summary-box">
            <h3><?= count($conversoes) ?></h3>
            <p>Almas Alcançadas</p>
        </div>
        <div class="summary-box bat">
            <h3><?= count($batismos) ?></h3>
            <p>Novos Batismos</p>
        </div>
    </div>

    <h2 class="section-title">Nomes - Registro de Novas Conversões</h2>
    <table>
        <thead>
            <tr>
                <th class="text-center">Data da Decisão</th>
                <th>Nome do Novo Convertido</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($conversoes) > 0): ?>
                <?php foreach ($conversoes as $c): ?>
                    <tr>
                        <td class="text-center"><?= date('d/m/Y', strtotime($c['data_conversao'])) ?></td>
                        <td><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="2" class="text-center" style="padding: 20px; color: #999;">Nenhum registro encontrado neste ano.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h2 class="section-title">Nomes - Registro de Batismos no Ano</h2>
    <table>
        <thead>
            <tr>
                <th class="text-center">Data do Batismo</th>
                <th>Nome do Membro</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($batismos) > 0): ?>
                <?php foreach ($batismos as $b): ?>
                    <tr>
                        <td class="text-center"><?= date('d/m/Y', strtotime($b['data_batismo'])) ?></td>
                        <td><strong><?= htmlspecialchars($b['nome']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="2" class="text-center" style="padding: 20px; color: #999;">Nenhum registro encontrado neste ano.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
