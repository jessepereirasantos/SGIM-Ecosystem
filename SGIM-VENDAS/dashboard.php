<?php
/**
 * SGIM MASTER - DASHBOARD v7.8 (ESTABILIZAÇÃO TOTAL)
 */
session_start();

// Proteção de Acesso: Se não estiver logado, vai para o login
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

$current_page = 'dashboard';
require_once 'templates/header.php';

// Inicialização segura
$total_clientes = 0;
$licencas_ativas = 0;
$vendas_mes = 0;
$receita_total = 0;
$ultimos_clientes = [];
$error_sql = null;

try {
    // Verificação de conexão
    if (!isset($pdo)) {
        throw new Exception("Variável de conexão PDO não definida.");
    }

    $total_clientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn() ?: 0;
    $licencas_ativas = $pdo->query("SELECT COUNT(*) FROM licencas WHERE status = 'ativa'")->fetchColumn() ?: 0;
    $vendas_mes = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE MONTH(data_venda) = MONTH(CURRENT_DATE()) AND YEAR(data_venda) = YEAR(CURRENT_DATE())")->fetchColumn() ?: 0;
    
    try {
        $receita_total = $pdo->query("SELECT SUM(valor) FROM pedidos WHERE status IN ('approved', 'pago', 'APROVADO')")->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $receita_total = 0;
    }
    
    $ultimos_clientes = $pdo->query("SELECT * FROM clientes ORDER BY id DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_sql = $e->getMessage();
}

// Alerta de Erro SQL (Apenas para diagnóstico)
if ($error_sql): ?>
    <div style="background: #fff5f5; border: 1px solid #feb2b2; color: #c53030; padding: 1.5rem; border-radius: 0.5rem; margin-bottom: 2rem; font-family: sans-serif;">
        <h3 style="margin-top:0">🚨 Erro de Banco de Dados</h3>
        <p>O Dashboard carregou, mas não conseguiu puxar os dados:</p>
        <code><?= htmlspecialchars($error_sql) ?></code>
        <p style="font-size: 0.9rem; margin-bottom:0">Dica: Verifique se as tabelas <code>clientes</code> e <code>pedidos</code> existem no banco.</p>
    </div>
<?php endif; ?>

<!-- Restante do HTML Original -->
<div class="flex justify-between items-end mb-10">
    <div>
        <h2 class="text-display-lg font-display-lg text-on-surface mb-2">Panorama Geral</h2>
        <p class="text-on-surface-variant font-body-md opacity-80">Bem-vindo de volta, <?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Admin') ?>.</p>
    </div>
    <div class="flex gap-3">
        <button onclick="window.location.reload()" class="px-5 py-2.5 rounded-lg border border-outline-variant/20 text-on-surface font-title-sm hover:bg-surface-variant/10 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">refresh</span>
            Atualizar
        </button>
        <button onclick="window.location.href='publish_master.php'" class="px-5 py-2.5 rounded-lg bg-primary text-on-primary font-title-sm hover:opacity-90 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">upload</span>
            Publicar Update
        </button>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-10">
    <div class="glass-card p-8 rounded-xl relative overflow-hidden group">
        <p class="text-label-caps font-label-caps text-on-surface-variant/60 uppercase mb-1">Clientes</p>
        <p class="text-headline-md font-headline-md text-on-surface"><?= $total_clientes ?></p>
    </div>
    <div class="glass-card p-8 rounded-xl relative overflow-hidden group">
        <p class="text-label-caps font-label-caps text-on-surface-variant/60 uppercase mb-1">Licenças</p>
        <p class="text-headline-md font-headline-md text-on-surface"><?= $licencas_ativas ?></p>
    </div>
    <div class="glass-card p-8 rounded-xl relative overflow-hidden group">
        <p class="text-label-caps font-label-caps text-on-surface-variant/60 uppercase mb-1">Vendas (Mês)</p>
        <p class="text-headline-md font-headline-md text-on-surface"><?= $vendas_mes ?></p>
    </div>
    <div class="glass-card p-8 rounded-xl relative overflow-hidden group">
        <p class="text-label-caps font-label-caps text-on-surface-variant/60 uppercase mb-1">Receita</p>
        <p class="text-headline-md font-headline-md text-on-surface">R$ <?= number_format($receita_total, 2, ',', '.') ?></p>
    </div>
</div>

<div class="glass-card rounded-xl overflow-hidden p-8">
    <h3 class="text-title-sm font-title-sm text-on-surface mb-4">Últimos Clientes</h3>
    <table class="w-full text-left">
        <thead>
            <tr class="bg-surface-container-low/50">
                <th class="px-4 py-3 text-label-caps font-label-caps text-on-surface-variant/50 uppercase">Nome</th>
                <th class="px-4 py-3 text-label-caps font-label-caps text-on-surface-variant/50 uppercase">Data</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ultimos_clientes as $c): ?>
            <tr class="border-t border-outline-variant/10">
                <td class="px-4 py-3"><?= htmlspecialchars($c['nome']) ?></td>
                <td class="px-4 py-3"><?= date('d/m/Y', strtotime($c['data_cadastro'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($ultimos_clientes)): ?>
                <tr><td colspan="2" class="px-4 py-8 text-center opacity-50">Nenhum registro.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'templates/footer.php'; ?>