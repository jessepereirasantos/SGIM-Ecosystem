<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

// 1. Verificação de Segurança de Conexão
if (!isset($pdo) || $pdo === null) {
    die("Erro de Conexão: O banco de dados não foi configurado corretamente ou o arquivo de configuração está ausente.");
}

// Fetch departamentos
try {
    $stmtDept = $pdo->query("SELECT * FROM departamentos ORDER BY nome ASC");
    $departamentos = $stmtDept ? $stmtDept->fetchAll(PDO::FETCH_ASSOC) : [];

    // Fetch cargos with department names
    $stmtCargos = $pdo->query("SELECT c.*, d.nome as departamento_nome FROM cargos c LEFT JOIN departamentos d ON c.departamento_id = d.id ORDER BY c.nome ASC");
    $cargos = $stmtCargos ? $stmtCargos->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $departamentos = [];
    $cargos = [];
}

$page_title = 'SGIM - Departamentos e Cargos';
$current_page = 'departamentos';

require_once 'includes/header.php';
?>

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold tracking-tight text-white">Departamentos e Cargos</h1>
            <p class="text-gray-500 text-sm max-w-xl">Gerencie a estrutura organizacional da igreja, defina hierarquias e organize suas equipes.</p>
        </div>
        <div class="flex gap-3">
            <a href="departamento_novo.php" class="flex items-center gap-2 bg-brand hover:bg-brand-dark text-black px-5 py-2.5 rounded-twelve font-semibold text-sm transition-all shadow-lg shadow-brand/10">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Novo Departamento
            </a>
        </div>
    </div>
    
    <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 1): ?>
        <div class="mb-6 p-4 rounded-twelve bg-green-500/10 border border-green-500/20 text-green-400 flex items-center gap-3">
            <span class="material-symbols-outlined">check_circle</span>
            <p class="text-sm font-semibold">Operação realizada com sucesso!</p>
        </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-2 gap-8">
        <!-- Departments Section -->
        <section class="flex flex-col gap-4">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand">corporate_fare</span>
                    <h2 class="text-lg font-semibold text-white">Departamentos</h2>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 bg-white/5 border border-darkborder rounded text-gray-400 uppercase tracking-widest"><?= count($departamentos) ?> Ativos</span>
            </div>

            <div class="space-y-4">
                <?php if (count($departamentos) > 0): ?>
                    <?php foreach ($departamentos as $d): ?>
                        <div class="bg-darkcard border border-darkborder p-5 rounded-twelve flex items-center justify-between hover:border-brand/30 transition-all group relative">
                            <div class="flex items-center gap-4">
                                <div class="size-12 rounded-lg bg-brand/10 flex items-center justify-center text-brand">
                                    <span class="material-symbols-outlined"><?= htmlspecialchars($d['icone'] ? $d['icone'] : 'group') ?></span>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-white group-hover:text-brand transition-colors"><?= htmlspecialchars($d['nome']) ?></h3>
                                    <p class="text-xs text-gray-500 truncate max-w-[200px]"><?= htmlspecialchars($d['descricao']) ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <?php if (strtolower($d['status'] ?? '') == 'ativo'): ?>
                                    <span class="bg-green-500/10 text-green-400 border border-green-500/20 text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded">Ativo</span>
                                <?php else: ?>
                                    <span class="bg-red-500/10 text-red-500 border border-red-500/20 text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded">Inativo</span>
                                <?php endif; ?>
                                
                                <div class="relative inline-block text-left" x-data="{ open: false }" @click.away="open = false">
                                    <button @click="open = !open" class="text-gray-500 hover:text-white transition-colors p-1">
                                        <span class="material-symbols-outlined">more_vert</span>
                                    </button>
                                    <div x-show="open" 
                                         style="display: none;"
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="transform opacity-0 scale-95"
                                         x-transition:enter-end="transform opacity-100 scale-100"
                                         class="absolute right-0 mt-2 w-48 rounded-xl shadow-2xl bg-darkcard border border-darkborder z-50 py-2">
                                        <a href="departamento_editar.php?id=<?= $d['id'] ?>" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-brand transition-all">
                                            <span class="material-symbols-outlined text-[18px]">edit</span>
                                            Editar
                                        </a>
                                        <button onclick="confirmDelete('departamento', <?= $d['id'] ?>)" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-500 hover:bg-red-500/5 transition-all">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                            Excluir
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-500 bg-white/5 rounded-twelve">
                        Nenhum departamento cadastrado.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Roles Section -->
        <section class="flex flex-col gap-4">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand">badge</span>
                    <h2 class="text-lg font-semibold text-white">Cargos (Roles)</h2>
                </div>
                <a href="cargo_novo.php" class="flex items-center gap-1 text-black bg-brand hover:bg-brand-dark px-3 py-1.5 rounded-lg font-bold text-[11px] transition-all uppercase tracking-wider">
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    Adicionar Cargo
                </a>
            </div>

            <div class="bg-darkcard border border-darkborder rounded-twelve overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-white/5 text-gray-400 text-[10px] uppercase tracking-widest font-semibold">
                    <tr>
                        <th class="px-6 py-4">Cargo</th>
                        <th class="px-6 py-4">Acesso</th>
                        <th class="px-6 py-4 text-center">Ações</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-darkborder">
                        <?php if (count($cargos) > 0): ?>
                            <?php foreach ($cargos as $c): ?>
                                <tr class="hover:bg-white/[0.02] transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-semibold text-white group-hover:text-brand transition-colors"><?= htmlspecialchars($c['nome']) ?></span>
                                            <span class="text-[11px] text-gray-500 uppercase tracking-wide"><?= htmlspecialchars($c['departamento_nome'] ?? 'Geral') ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="bg-white/5 text-gray-400 border border-darkborder px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider"><?= htmlspecialchars($c['nivel_acesso']) ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="cargo_editar.php?id=<?= $c['id'] ?>" class="text-gray-500 hover:text-brand transition-all p-1">
                                                <span class="material-symbols-outlined text-[20px]">edit</span>
                                            </a>
                                            <button onclick="confirmDelete('cargo', <?= $c['id'] ?>)" class="text-gray-500 hover:text-red-500 transition-all p-1">
                                                <span class="material-symbols-outlined text-[20px]">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-gray-500">Nenhum cargo cadastrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

<?php
require_once 'includes/footer.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmDelete(type, id) {
    const title = type === 'departamento' ? 'Excluir Departamento?' : 'Excluir Cargo?';
    const text = type === 'departamento' ? 'Isso pode afetar cargos vinculados.' : 'Esta ação não pode ser desfeita.';
    
    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EAB308',
        cancelButtonColor: '#1F2937',
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar',
        background: '#121212',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `processar_exclusao.php?type=${type}&id=${id}`;
        }
    });
}
</script>
