<?php
/**
 * Geração de Carteirinha Digital (v1.1.87)
 * Redireciona para o renderizador dinâmico ou lista membros
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';
require_once 'src/autoload.php';

use App\Controllers\CarteirinhaController;
$carteirinhaCtrl = new CarteirinhaController($pdo);


// Redireciona se for chamada de geração individual para o novo carteirinha_gerar.php
if (isset($_GET['id'])) {
    header('Location: carteirinha_gerar.php?id=' . intval($_GET['id']));
    exit;
}

$page_title = 'Selecionar Membro - Carteirinha';
$current_page = 'carteirinhas';
require_once 'includes/header.php';

// Busca os membros ativos do banco
$membros = $pdo->query("SELECT id, nome, cpf FROM membros WHERE status = 'Ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-darkcard p-6 rounded-xl border border-darkborder shadow-lg">
        <div>
            <h2 class="text-2xl font-black text-white tracking-tighter">Selecionar Membro para Carteirinha</h2>
            <p class="text-xs text-gray-500 uppercase font-bold tracking-widest mt-1">Gere o documento oficial com base no cargo do membro</p>
        </div>
        <div>
            <a href="carteirinha_editor.php" class="flex items-center gap-2 px-6 py-3 bg-brand hover:bg-brand-dark text-black rounded-xl text-xs font-black uppercase tracking-widest shadow-lg shadow-brand/20 transition-all">
                <span class="material-symbols-outlined text-base">palette</span>
                Gerenciar Modelos (Canva)
            </a>
        </div>
    </div>

    <div class="bg-darkcard border border-darkborder rounded-xl overflow-hidden shadow-lg">
        <table class="w-full text-left">
            <thead class="bg-white/5 text-xs uppercase text-gray-400">
                <tr>
                    <th class="px-6 py-4 font-black">Nome do Membro</th>
                    <th class="px-6 py-4 font-black">CPF</th>
                    <th class="px-6 py-4 text-right font-black">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-darkborder text-sm">
                <?php if (empty($membros)): ?>
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-gray-500 font-bold">Nenhum membro ativo cadastrado no sistema.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($membros as $m): ?>
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="px-6 py-4 font-semibold text-white"><?= htmlspecialchars($m['nome']) ?></td>
                        <td class="px-6 py-4 text-gray-400 font-mono"><?= htmlspecialchars($m['cpf'] ?? '---') ?></td>
                        <td class="px-6 py-4 text-right">
                            <a href="carteirinha_gerar.php?id=<?= $m['id'] ?>" target="_blank" class="inline-flex items-center gap-2 text-brand hover:underline font-bold text-xs uppercase tracking-wider">
                                <span class="material-symbols-outlined text-sm">badge</span>
                                Gerar Carteirinha
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
