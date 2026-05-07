<?php
require_once 'config/db.php';

$mensagem = '';
$erro = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $req_id = $_POST['id'] ?? '';
    
    if ($acao == 'aprovar' && !empty($req_id)) {
        try {
            $pdo->beginTransaction();
            
            // Get request info
            $stmtReq = $pdo->prepare("SELECT * FROM activation_requests WHERE id = ?");
            $stmtReq->execute([$req_id]);
            $req = $stmtReq->fetch(PDO::FETCH_ASSOC);
            
            if ($req && $req['status'] == 'pending') {
                // Check if license exists
                $stmtLic = $pdo->prepare("SELECT id FROM licencas WHERE chave_licenca = ?");
                $stmtLic->execute([$req['license_key']]);
                $lic = $stmtLic->fetch(PDO::FETCH_ASSOC);
                
                if ($lic) {
                    // Update request
                    $stmtUpdateReq = $pdo->prepare("UPDATE activation_requests SET status = 'approved' WHERE id = ?");
                    $stmtUpdateReq->execute([$req_id]);
                    
                    // Update license
                    $stmtUpdateLic = $pdo->prepare("UPDATE licencas SET dominio = ?, status = 'ativa' WHERE id = ?");
                    $stmtUpdateLic->execute([$req['domain'], $lic['id']]);
                    
                    $pdo->commit();
                    $mensagem = "Ativação aprovada com sucesso!";
                } else {
                    $pdo->rollBack();
                    $erro = true;
                    $mensagem = "Chave de licença não encontrada no sistema.";
                }
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $erro = true;
            $mensagem = "Erro ao aprovar: " . $e->getMessage();
        }
    } elseif ($acao == 'rejeitar' && !empty($req_id)) {
        try {
            $stmt = $pdo->prepare("UPDATE activation_requests SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$req_id]);
            $mensagem = "Ativação rejeitada.";
        } catch(PDOException $e) {
            $erro = true;
            $mensagem = "Erro ao rejeitar: " . $e->getMessage();
        }
    } elseif ($acao == 'excluir_pedido' && !empty($req_id)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM activation_requests WHERE id = ?");
            $stmt->execute([$req_id]);
            $mensagem = "Registro de pedido excluído.";
        } catch(PDOException $e) {
            $erro = true;
            $mensagem = "Erro ao excluir pedido: " . $e->getMessage();
        }
    }
}

// Fetch pending requests
$stmtPending = $pdo->query("SELECT * FROM activation_requests WHERE status = 'pending' ORDER BY created_at ASC");
$pendentes = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

// Fetch history
$stmtHistory = $pdo->query("SELECT * FROM activation_requests WHERE status != 'pending' ORDER BY created_at DESC LIMIT 20");
$historico = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'SGIM Central - Pedidos de Ativação';
$current_page = 'pedidos';

require_once 'includes/header.php';
?>

<div class="mb-8 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-white tracking-tight">Pedidos de Ativação</h1>
        <p class="text-gray-400 mt-1">Aprove ou rejeite a instalação do SGIM nos clientes.</p>
    </div>
</div>

<?php if ($mensagem): ?>
    <div class="mb-6 p-4 rounded-twelve <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3">
        <span class="material-symbols-outlined"><?= $erro ? 'error' : 'check_circle' ?></span>
        <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem) ?></p>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-8 mb-8">
    <!-- Pedidos Pendentes -->
    <div class="bg-darkcard rounded-twelve border border-darkborder shadow-sm overflow-hidden">
        <div class="p-6 border-b border-brand/50 bg-brand/5 flex justify-between items-center">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-brand">notifications_active</span>
                Aguardando Aprovação
            </h2>
            <span class="bg-brand text-black font-bold px-3 py-1 rounded-full text-xs"><?= count($pendentes) ?> pedidos</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-white/5 text-gray-400 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 font-semibold">Cliente Solicitante</th>
                    <th class="px-6 py-4 font-semibold">Domínio / URL</th>
                    <th class="px-6 py-4 font-semibold">Chave Informada</th>
                    <th class="px-6 py-4 font-semibold">Data</th>
                    <th class="px-6 py-4 font-semibold text-right">Ações</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-darkborder">
                <?php if (count($pendentes) > 0): ?>
                    <?php foreach($pendentes as $p): ?>
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-6 py-4">
                                <p class="text-sm font-bold text-white"><?= htmlspecialchars($p['nome']) ?></p>
                                <p class="text-[11px] text-gray-500"><?= htmlspecialchars($p['email']) ?> • <?= htmlspecialchars($p['telefone']) ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-blue-400 font-medium"><?= htmlspecialchars($p['domain']) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-mono text-brand bg-brand/10 px-2 py-1 rounded border border-brand/20">
                                    <?= htmlspecialchars($p['license_key']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-400">
                                <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" class="inline-flex gap-2">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" name="acao" value="aprovar" class="bg-green-600 hover:bg-green-500 text-white font-bold py-1.5 px-3 rounded text-xs transition-colors shadow shadow-green-600/20">Aprovar</button>
                                    <button type="submit" name="acao" value="rejeitar" class="bg-red-600/20 hover:bg-red-500 hover:text-white border border-red-500 text-red-500 font-bold py-1.5 px-3 rounded text-xs transition-colors">Rejeitar</button>
                                    <button type="submit" name="acao" value="excluir_pedido" onclick="return confirm('Excluir este pedido permanentemente?');" class="text-gray-500 hover:text-red-500 transition-colors p-1" title="Excluir Definitivamente">
                                        <span class="material-symbols-outlined text-lg">delete_forever</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500 font-medium">Nenhum pedido de ativação pendente. Tudo limpo!</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Histórico de Pedidos -->
    <div class="bg-darkcard rounded-twelve border border-darkborder shadow-sm overflow-hidden">
        <div class="p-6 border-b border-darkborder">
            <h2 class="text-lg font-bold text-gray-300">Histórico de Análises</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-white/5 text-gray-500 text-xs tracking-wider">
                <tr>
                    <th class="px-6 py-3">Domínio</th>
                    <th class="px-6 py-3">Chave Validada</th>
                    <th class="px-6 py-3 text-center">Status Final</th>
                    <th class="px-6 py-3 text-right">Ações</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-darkborder">
                <?php if (count($historico) > 0): ?>
                    <?php foreach($historico as $h): ?>
                        <tr class="opacity-75">
                            <td class="px-6 py-3 text-xs text-gray-300">
                                <?= htmlspecialchars($h['domain']) ?>
                                <span class="block text-[10px] text-gray-500"><?= htmlspecialchars($h['nome']) ?></span>
                            </td>
                            <td class="px-6 py-3 text-xs font-mono text-gray-400">
                                <?= htmlspecialchars($h['license_key']) ?>
                            </td>
                            <td class="px-6 py-3 text-center">
                                <?php if($h['status'] == 'approved'): ?>
                                    <span class="text-[10px] bg-green-500/10 text-green-500 px-2 py-0.5 rounded font-bold uppercase">Aprovado</span>
                                <?php else: ?>
                                    <span class="text-[10px] bg-red-500/10 text-red-500 px-2 py-0.5 rounded font-bold uppercase">Rejeitado</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <form method="POST" onsubmit="return confirm('Remover este registro do histórico?');" class="inline">
                                    <input type="hidden" name="id" value="<?= $h['id'] ?>">
                                    <input type="hidden" name="acao" value="excluir_pedido">
                                    <button type="submit" class="text-gray-600 hover:text-red-500 transition-colors">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="px-6 py-4 text-center text-xs text-gray-600">Sem histórico no momento.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
