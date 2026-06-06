<?php
ob_start();
session_start();
require_once __DIR__ . '/config/database.php';

// Verificação de Autenticação e Conexão de Banco
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 🛡️ Inicializa o AccessManager para proteção de rota antecipada
if (!class_exists('SGIM\\Auth\\AccessManager')) {
    $amPath = __DIR__ . '/src/Auth/AccessManager.php';
    if (file_exists($amPath)) require_once $amPath;
}
$access = new \SGIM\Auth\AccessManager($pdo, $_SESSION['user_id']);

// Validação antecipada de leitura
if ($access && !$access->can('membros', 'visualizar')) {
    echo "<script>alert('Acesso Negado: Você não tem permissão para ver Membros.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Lógica de Aprovação/Rejeição/Exclusão (Gravação)
if (isset($_GET['action']) && isset($_GET['id'])) {
    // Validação antecipada de gravação
    if ($access && !$access->can('membros', 'editar') && !$access->can('membros', 'excluir')) {
        echo "<script>alert('Acesso Negado: Você não tem permissão para alterar ou excluir membros.'); window.location.href='membros.php';</script>";
        exit;
    }

    $action = $_GET['action'];
    $id = intval($_GET['id']);
    
    // Para segurança extra, se for escopo LOCAL, verifica se o membro pertence à congregação do usuário
    if ($access && !$access->isGlobal()) {
        $stmtCheck = $pdo->prepare("SELECT congregacao_id FROM membros WHERE id = ?");
        $stmtCheck->execute([$id]);
        $memberCongId = $stmtCheck->fetchColumn();
        if ($memberCongId != $access->getCongregacaoId()) {
            echo "<script>alert('Acesso Negado: Este membro pertence a outra congregação.'); window.location.href='membros.php';</script>";
            exit;
        }
    }
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE membros SET status = 'Ativo' WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: membros.php?sucesso_aprovacao=1');
        exit;
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("DELETE FROM membros WHERE id = ? AND status = 'Inativo'");
        $stmt->execute([$id]);
        header('Location: membros.php?sucesso_rejeicao=1');
        exit;
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM membros WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: membros.php?sucesso_exclusao=1');
        exit;
    }
}

$page_title = 'SGIM - Lista de Membros';
$current_page = 'membros';

$filter_status = $_GET['status'] ?? 'Ativo';

require_once __DIR__ . '/includes/header.php';
?>

    <div>
        <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 1): ?>
            <div class="mb-6 p-4 rounded-twelve bg-green-500/10 border border-green-500/20 text-green-400 flex items-center gap-3">
                <span class="material-symbols-outlined">check_circle</span>
                <p class="text-sm font-semibold">Membro cadastrado com sucesso!</p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['sucesso_exclusao'])): ?>
            <div class="mb-6 p-4 rounded-twelve bg-red-500/10 border border-red-500/20 text-red-400 flex items-center gap-3">
                <span class="material-symbols-outlined">delete_forever</span>
                <p class="text-sm font-semibold">Membro excluído com sucesso!</p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['sucesso_rejeicao'])): ?>
            <div class="mb-6 p-4 rounded-twelve bg-orange-500/10 border border-orange-500/20 text-orange-400 flex items-center gap-3">
                <span class="material-symbols-outlined">person_remove</span>
                <p class="text-sm font-semibold">Cadastro rejeitado com sucesso!</p>
            </div>
        <?php endif; ?>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-3xl font-bold text-white tracking-tight">Lista de Membros</h2>
                <p class="text-sm text-gray-500 mt-1">Gerencie a base de dados centralizada da congregação.</p>
            </div>
            <a href="membro_novo.php" class="flex items-center gap-2 bg-brand hover:bg-brand-dark text-black px-4 py-2.5 rounded-twelve font-semibold text-sm transition-all shadow-lg shadow-brand/10">
                <span class="material-symbols-outlined text-sm font-bold">add</span>
                Novo Membro
            </a>
        </div>

        <!-- Filters Section -->
        <div class="flex flex-wrap items-center gap-4 mb-8">
            <div class="flex items-center gap-1 bg-darkcard border border-darkborder p-1.5 rounded-twelve">
                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 px-3">Status:</span>
                <select onchange="window.location.href='membros.php?status=' + this.value" class="bg-transparent border-none text-xs font-semibold text-gray-300 focus:ring-0 cursor-pointer">
                    <option value="Ativo" <?= $filter_status == 'Ativo' ? 'selected' : '' ?> class="bg-darkcard">Ativos</option>
                    <option value="Inativo" <?= $filter_status == 'Inativo' ? 'selected' : '' ?> class="bg-darkcard">Pendentes / Inativos</option>
                </select>
            </div>
        </div>

        <!-- Standardized Members Table -->
        <section class="bg-darkcard border border-darkborder rounded-twelve overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-white/5 text-gray-400 text-[10px] uppercase tracking-widest font-bold">
                    <tr>
                        <th class="px-6 py-4">Nome</th>
                        <th class="px-6 py-4">Cargo</th>
                        <th class="px-6 py-4">Congregação</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-center">Ações</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-darkborder">
                    <?php
                    $filter_status = $_GET['status'] ?? 'Ativo';

                    // 🔍 FILTRO DE ESCOPO (Global vs Local)
                    $scopeFilter = $access ? $access->getScopeFilter('m') : '';
                    
                    $where = "WHERE m.status = ? $scopeFilter";
                    $params = [$filter_status];
                    
                    $sql = "SELECT m.*, c.nome as cargo_nome, con.nome as congregacao_nome 
                            FROM membros m 
                            LEFT JOIN cargos c ON m.cargo_id = c.id 
                            LEFT JOIN congregacoes con ON m.congregacao_id = con.id 
                            $where 
                            ORDER BY m.id DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (count($membros) > 0) {
                        foreach ($membros as $m) {
                            echo '<tr class="hover:bg-white/[0.02] transition-colors group">';
                            echo '<td class="px-6 py-4">';
                            echo '<div class="flex items-center gap-3">';
                            echo '<div class="w-10 h-10 rounded-full bg-cover bg-center border-2 border-brand/20 group-hover:border-brand transition-colors flex items-center justify-center font-bold text-brand bg-brand/10">' . substr($m['nome'], 0, 2) . '</div>';
                            echo '<div class="flex flex-col">';
                            echo '<span class="text-sm font-semibold text-white group-hover:text-brand transition-colors">' . htmlspecialchars($m['nome']) . '</span>';
                            echo '<span class="text-[10px] text-gray-500 uppercase">ID: #' . str_pad($m['id'], 6, '0', STR_PAD_LEFT) . '</span>';
                            echo '</div>';
                            echo '</div>';
                            echo '</td>';
                            
                            echo '<td class="px-6 py-4">';
                            echo '<span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-white/5 text-gray-400 border border-darkborder">' . htmlspecialchars($m['cargo_nome'] ?? 'Membro') . '</span>';
                            echo '</td>';
                            
                            echo '<td class="px-6 py-4 text-sm text-gray-400">' . htmlspecialchars($m['congregacao_nome'] ?? 'Sede Central') . '</td>';
                            
                            echo '<td class="px-6 py-4 text-center">';
                            if ($m['status'] == 'Ativo') {
                                echo '<span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-green-500/10 text-green-400 border border-green-500/20">Ativo</span>';
                            } else {
                                echo '<span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-yellow-500/10 text-yellow-400 border border-yellow-500/20">Pendente</span>';
                            }
                            echo '</td>';
                            
                            echo '<td class="px-6 py-4 text-center">';
                            ?>
                            <div class="flex items-center justify-center gap-2">
                                <?php if ($m['status'] == 'Inativo'): ?>
                                    <a href="membros.php?action=approve&id=<?= $m['id'] ?>" class="text-green-500 hover:scale-110 transition-all p-1" title="Aprovar Membro">
                                        <span class="material-symbols-outlined text-lg">check_circle</span>
                                    </a>
                                    <a href="membros.php?action=reject&id=<?= $m['id'] ?>" onclick="return confirm('Deseja realmente rejeitar este cadastro?')" class="text-red-500 hover:scale-110 transition-all p-1" title="Rejeitar">
                                        <span class="material-symbols-outlined text-lg">cancel</span>
                                    </a>
                                <?php else: ?>
                                    <a href="carteirinha_digital.php?id=<?= $m['id'] ?>" target="_blank" class="text-brand hover:scale-110 transition-all p-1" title="Carteirinha Digital">
                                        <span class="material-symbols-outlined text-lg">badge</span>
                                    </a>
                                    <a href="membro_perfil.php?id=<?= $m['id'] ?>" class="text-gray-500 hover:text-brand transition-all p-1" title="Ver Perfil">
                                        <span class="material-symbols-outlined text-lg">account_circle</span>
                                    </a>
                                    <a href="membro_novo.php?id=<?= $m['id'] ?>" class="text-gray-500 hover:text-brand transition-all p-1" title="Editar">
                                        <span class="material-symbols-outlined text-lg">edit</span>
                                    </a>
                                    <a href="membros.php?action=delete&id=<?= $m['id'] ?>" onclick="return confirm('Deseja realmente excluir este membro? Esta ação é irreversível.')" class="text-gray-600 hover:text-red-500 transition-all p-1" title="Excluir">
                                        <span class="material-symbols-outlined text-lg">delete</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Nenhum membro encontrado.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
