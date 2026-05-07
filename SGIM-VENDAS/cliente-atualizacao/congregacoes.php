<?php
ob_start();
session_start();
require_once 'config/db.php';

// Verificação de Autenticação e Conexão de Banco
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = 'SGIM - Congregações';
$current_page = 'congregacoes';

require_once 'includes/header.php';
?>

    <div class="flex flex-col gap-1 mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-2xl font-bold text-white tracking-tight">Gerenciamento de Campo</h3>
                <p class="text-gray-500 text-sm">Visualize e administre todas as igrejas e seus respectivos responsáveis de forma centralizada.</p>
            </div>
            <a href="congregacao_nova.php" class="flex items-center gap-2 bg-brand hover:bg-brand-dark text-black px-5 py-2.5 rounded-twelve font-semibold text-sm transition-all shadow-lg shadow-brand/10 active:scale-[0.98]">
                <span class="material-symbols-outlined text-[20px]">add_circle</span>
                Nova Congregação
            </a>
        </div>
    </div>

    <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 1): ?>
        <div class="mb-6 p-4 rounded-twelve bg-green-500/10 border border-green-500/20 text-green-400 flex items-center gap-3">
            <span class="material-symbols-outlined">check_circle</span>
            <p class="text-sm font-semibold">Congregação cadastrada com sucesso!</p>
        </div>
    <?php endif; ?>

    <section class="bg-darkcard border border-darkborder rounded-twelve overflow-hidden shadow-sm shadow-black/40">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-white/5 text-gray-400 text-xs uppercase tracking-widest font-bold">
                <tr>
                    <th class="px-6 py-4 font-semibold">Nome da Congregação</th>
                    <th class="px-6 py-4 font-semibold">Pastor Responsável</th>
                    <th class="px-6 py-4 font-semibold">Endereço</th>
                    <th class="px-6 py-4 font-semibold">Telefone</th>
                    <th class="px-6 py-4 font-semibold text-center">Ações</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-darkborder">
                <?php
                require_once 'config/db.php';
                $stmt = $pdo->query("SELECT * FROM congregacoes ORDER BY nome ASC");
                $congregacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($congregacoes) > 0) {
                    foreach ($congregacoes as $c) {
                        echo '<tr class="hover:bg-white/[0.02] transition-colors group">';
                        echo '<td class="px-6 py-4">';
                        echo '<div class="flex items-center gap-3">';
                        echo '<div class="w-10 h-10 rounded-twelve bg-brand/10 flex items-center justify-center text-brand font-bold text-xs">' . htmlspecialchars($c['sigla']) . '</div>';
                        echo '<span class="text-sm font-medium text-white group-hover:text-brand transition-colors">' . htmlspecialchars($c['nome']) . '</span>';
                        echo '</div>';
                        echo '</td>';

                        echo '<td class="px-6 py-4">';
                        echo '<div class="flex items-center gap-2 text-gray-300">';
                        echo '<span class="material-symbols-outlined text-brand text-sm">person</span>';
                        echo '<span class="text-sm">' . htmlspecialchars($c['pastor']) . '</span>';
                        echo '</div>';
                        echo '</td>';

                        echo '<td class="px-6 py-4">';
                        echo '<div class="flex items-center gap-2 text-gray-500">';
                        echo '<span class="material-symbols-outlined text-sm">location_on</span>';
                        echo '<span class="text-sm truncate max-w-[200px]">' . htmlspecialchars($c['endereco']) . '</span>';
                        echo '</div>';
                        echo '</td>';

                        echo '<td class="px-6 py-4">';
                        echo '<span class="text-sm font-mono text-gray-300">' . htmlspecialchars($c['telefone']) . '</span>';
                        echo '</td>';

                        echo '<td class="px-6 py-4">';
                        echo '<div class="flex items-center justify-center gap-3">';
                        echo '<a href="congregacao_editar.php?id=' . $c['id'] . '" class="text-gray-500 hover:text-brand transition-all p-1" title="Editar"><span class="material-symbols-outlined text-xl">edit_square</span></a>';
                        echo '<button onclick="confirmarExclusao(' . $c['id'] . ')" class="text-gray-500 hover:text-red-400 transition-all p-1" title="Excluir"><span class="material-symbols-outlined text-xl">delete</span></button>';
                        echo '</div>';
                        echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Nenhuma congregação encontrada.</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="p-4 border-t border-darkborder flex items-center justify-between bg-white/[0.01]">
            <span class="text-xs text-gray-500 uppercase font-medium">Mostrando registros</span>
            <div class="flex items-center gap-1">
                <button class="px-3 py-1.5 text-xs font-semibold text-gray-400 bg-darkbg border border-darkborder rounded-md hover:text-brand hover:border-brand transition-all disabled:opacity-30" disabled><span class="material-symbols-outlined text-sm leading-none">chevron_left</span></button>
                <button class="px-4 py-1.5 text-xs font-bold text-black bg-brand rounded-md">1</button>
                <button class="px-3 py-1.5 text-xs font-semibold text-gray-400 bg-darkbg border border-darkborder rounded-md hover:text-brand hover:border-brand transition-all"><span class="material-symbols-outlined text-sm leading-none">chevron_right</span></button>
            </div>
        </div>
    </section>

<script>
function confirmarExclusao(id) {
    if (confirm('Deseja realmente excluir esta congregação? Os dados serão arquivados com segurança.')) {
        window.location.href = 'processar_exclusao.php?id=' + id + '&type=congregacao';
    }
}
</script>

<?php
require_once 'includes/footer.php';
?>
