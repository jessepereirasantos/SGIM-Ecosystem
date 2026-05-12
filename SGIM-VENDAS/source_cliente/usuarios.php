<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

// 1. BUSCA LISTA DE USUÁRIOS COM SEUS VÍNCULOS
$sql = "SELECT u.*, c.nome as cargo_nome, co.nome as congregacao_nome 
        FROM usuarios u
        LEFT JOIN cargos c ON u.cargo_id = c.id
        LEFT JOIN congregacoes co ON u.congregacao_id = co.id
        ORDER BY u.nome ASC";
$stmt = $pdo->query($sql);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'SGIM - Gestão de Usuários';
$current_page = 'usuarios';

require_once 'includes/header.php';
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-3xl font-bold text-white tracking-tight">Usuários e Acessos</h2>
        <p class="text-sm text-gray-500 mt-1">Gerencie quem pode acessar o sistema e quais são seus limites.</p>
    </div>
    <a href="usuario_novo.php" class="px-6 py-3 rounded-xl bg-brand text-black font-bold flex items-center gap-2 hover:bg-brand-dark transition-all shadow-lg shadow-brand/10">
        <span class="material-symbols-outlined">person_add</span>
        Novo Usuário
    </a>
</div>

<div class="bg-darkcard rounded-2xl border border-darkborder overflow-hidden shadow-xl">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white/[0.02] border-b border-darkborder">
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Usuário</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Cargo / Nível</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Congregação</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Status</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-gray-500 uppercase tracking-widest">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-darkborder">
                <?php foreach ($usuarios as $u): ?>
                    <tr class="hover:bg-white/[0.01] transition-colors group">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-full bg-gradient-to-br from-darkbg to-darkcard border border-darkborder flex items-center justify-center text-brand font-bold">
                                    <?= strtoupper(substr($u['nome'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white group-hover:text-brand transition-colors"><?= htmlspecialchars($u['nome']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($u['email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($u['cargo_nome']): ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-brand/10 text-brand border border-brand/20">
                                    <?= htmlspecialchars($u['cargo_nome']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-[10px] text-gray-600 italic">Sem cargo definido</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-400">
                            <?= $u['congregacao_nome'] ? htmlspecialchars($u['congregacao_nome']) : '<span class="text-gray-600 italic">Sede / Global</span>' ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="flex items-center gap-1.5 text-xs <?= $u['ativo'] ? 'text-green-500' : 'text-red-500' ?>">
                                <span class="size-1.5 rounded-full bg-current"></span>
                                <?= $u['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <a href="usuario_editar.php?id=<?= $u['id'] ?>" class="p-2 rounded-lg bg-white/5 border border-darkborder text-gray-400 hover:text-brand hover:border-brand/30 transition-all inline-flex">
                                <span class="material-symbols-outlined text-sm">edit</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
