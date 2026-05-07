<?php
/**
 * Geração de Carteirinha Digital (v1.0)
 * Gera um HTML formatado para impressão ou salvamento como PDF
 */
require_once 'config/db.php';

if (!isset($_GET['id'])) {
    // Se não houver ID, mostra uma lista simples de membros para selecionar
    $page_title = 'Selecionar Membro - Carteirinha';
    require_once 'includes/header.php';
    
    $membros = $pdo->query("SELECT id, nome, cpf FROM membros WHERE status = 'Ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="max-w-4xl mx-auto space-y-6">
        <h2 class="text-2xl font-bold text-white">Selecionar Membro para Carteirinha</h2>
        <div class="bg-darkcard border border-darkborder rounded-xl overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-white/5 text-xs uppercase text-gray-400">
                    <tr>
                        <th class="px-6 py-4">Nome</th>
                        <th class="px-6 py-4">CPF</th>
                        <th class="px-6 py-4 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-darkborder text-sm">
                    <?php foreach ($membros as $m): ?>
                    <tr class="hover:bg-white/[0.02]">
                        <td class="px-6 py-4 font-medium"><?= htmlspecialchars($m['nome']) ?></td>
                        <td class="px-6 py-4 text-gray-500"><?= htmlspecialchars($m['cpf'] ?? '---') ?></td>
                        <td class="px-6 py-4 text-right">
                            <a href="carteirinha_digital.php?id=<?= $m['id'] ?>" class="text-brand hover:underline font-bold">Gerar Carteirinha</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    require_once 'includes/footer.php';
    exit;
}

$id = intval($_GET['id']);

$sql = "SELECT m.*, c.nome as cargo_nome, con.nome as congregacao_nome 
        FROM membros m 
        LEFT JOIN cargos c ON m.cargo_id = c.id 
        LEFT JOIN congregacoes con ON m.congregacao_id = con.id 
        WHERE m.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$m) {
    die('Membro não encontrado.');
}

$nome_igreja = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'nome_igreja'")->fetchColumn() ?: 'Minha Igreja';
$qr_code_data = "SGIM-MEMBRO-ID-" . $m['id'];
$qr_url = "https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=" . urlencode($qr_code_data);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Carteirinha - <?= htmlspecialchars($m['nome']) ?></title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f0f0f0; }
        .card { width: 350px; height: 220px; background: #fff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); display: flex; overflow: hidden; position: relative; border: 1px solid #ddd; }
        .stripe { width: 15px; height: 100%; background: #FFC107; }
        .content { flex: 1; padding: 20px; display: flex; flex-direction: column; }
        .header { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .header h1 { font-size: 14px; margin: 0; color: #333; text-transform: uppercase; }
        .info { flex: 1; }
        .info h2 { font-size: 16px; margin: 0 0 5px 0; color: #000; }
        .info p { font-size: 10px; margin: 2px 0; color: #666; font-weight: bold; }
        .info span { color: #333; font-weight: normal; }
        .qr-area { width: 80px; display: flex; flex-direction: column; align-items: center; justify-content: center; border-left: 1px dashed #eee; padding: 10px; }
        .qr-area img { width: 70px; height: 70px; }
        .qr-area span { font-size: 8px; color: #999; margin-top: 5px; font-weight: bold; }
        @media print {
            body { background: white; }
            .card { box-shadow: none; border: 1px solid #000; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="stripe"></div>
        <div class="content">
            <div class="header">
                <h1><?= htmlspecialchars($nome_igreja) ?></h1>
            </div>
            <div class="info">
                <h2><?= htmlspecialchars($m['nome']) ?></h2>
                <p>CARGO: <span><?= htmlspecialchars($m['cargo_nome'] ?? 'Membro') ?></span></p>
                <p>CONGREGAÇÃO: <span><?= htmlspecialchars($m['congregacao_nome'] ?? 'Sede') ?></span></p>
                <p>CADASTRO: <span><?= date('d/m/Y', strtotime($m['data_cadastro'])) ?></span></p>
            </div>
        </div>
        <div class="qr-area">
            <img src="<?= $qr_url ?>" alt="QR Code">
            <span>VALIDAÇÃO DIGITAL</span>
        </div>
    </div>
    
    <div class="no-print" style="position: absolute; bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #FFC107; border: none; border-radius: 5px; font-weight: bold; cursor: pointer;">Imprimir Carteirinha</button>
    </div>
</body>
</html>
