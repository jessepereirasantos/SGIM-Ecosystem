<?php
require_once 'config/database.php';
$current_page = 'clientes';

// Processamento de Ações (Exclusão em Cascata)
$msg = "";
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        // 1. Remove pedidos vinculados
        $pdo->prepare("DELETE FROM pedidos WHERE cliente_id = ?")->execute([$id]);
        // 2. Remove vendas vinculadas (se a tabela existir)
        try { $pdo->prepare("DELETE FROM vendas WHERE cliente_id = ?")->execute([$id]); } catch(Exception $e){}
        // 3. Remove o cliente
        $pdo->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id]);
        
        $pdo->commit();
        header("Location: clientes.php?success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "<div class='p-4 bg-error/10 text-error rounded-xl mb-6 text-xs font-bold border border-error/20'>🛑 Erro ao remover: " . $e->getMessage() . "</div>";
    }
}

if (isset($_GET['success'])) {
    $msg = "<div class='p-4 bg-secondary/10 text-secondary rounded-xl mb-6 text-xs font-bold border border-secondary/20'>✅ Cliente removido com sucesso.</div>";
}

// Busca de Clientes (Preservando query original)
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM clientes";
if (!empty($search)) {
    $query .= " WHERE nome LIKE :s OR email LIKE :s OR dominio LIKE :s";
}
$query .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query);
if (!empty($search)) {
    $stmt->bindValue(':s', "%$search%");
}
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'templates/header.php';
?>

<div class="flex">
    <?php include 'sidebar.php'; ?>

    <main class="ml-[280px] min-h-screen flex-1">
        <!-- Top Navigation (Padrão Oficial) -->
        <header class="h-16 flex items-center justify-between px-8 bg-surface/80 backdrop-blur-md sticky top-0 z-40 border-b border-outline-variant/10">
            <div class="flex items-center gap-4 bg-surface-container-low px-4 py-2 rounded-lg w-full max-w-md border border-outline-variant/20">
                <span class="material-symbols-outlined text-on-surface-variant">search</span>
                <form action="" method="GET" class="w-full">
                    <input name="search" value="<?= htmlspecialchars($search) ?>" class="bg-transparent border-none focus:ring-0 text-body-sm w-full placeholder:text-on-surface-variant/40 outline-none text-white" placeholder="Buscar clientes ou domínios..." type="text"/>
                </form>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-right">
                    <p class="text-body-sm font-bold text-on-surface leading-tight">Admin SGIM</p>
                    <p class="text-[10px] uppercase tracking-widest text-primary font-bold">Gerenciamento de Clientes</p>
                </div>
                <div class="size-10 bg-surface-container-highest border border-primary/20 rounded-lg flex items-center justify-center text-primary font-black">C</div>
            </div>
        </header>

        <div class="p-10 max-w-[1600px] mx-auto">
            <!-- Header Section -->
            <div class="flex justify-between items-end mb-10">
                <div>
                    <h2 class="text-display-lg font-bold text-on-surface tracking-tighter">Gestão de <span class="text-primary">Clientes</span></h2>
                    <p class="text-on-surface-variant font-body-md opacity-60">Controle total sobre a base instalada e domínios autorizados.</p>
                </div>
                <button class="px-5 py-2.5 rounded-lg bg-primary text-on-primary font-bold hover:opacity-90 transition-all flex items-center gap-2 text-sm shadow-xl shadow-primary/20">
                    <span class="material-symbols-outlined text-sm">person_add</span>
                    Novo Cliente
                </button>
            </div>

            <?= $msg ?>

            <!-- Tabela Estilo Glass-Card (Padrão Oficial) -->
            <div class="glass-card rounded-xl overflow-hidden">
                <div class="p-8 border-b border-outline-variant/10">
                    <h3 class="text-title-sm font-bold text-on-surface">Base de Dados de Parceiros</h3>
                    <p class="text-body-sm text-on-surface-variant opacity-60">Listagem de acessos ativos e chaves de licença.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-surface-container-low/50 text-[10px] uppercase tracking-widest text-on-surface-variant/50">
                                <th class="px-8 py-4 font-bold">Identificação</th>
                                <th class="px-8 py-4 font-bold">Domínio Autorizado</th>
                                <th class="px-8 py-4 font-bold">Licença Ativa</th>
                                <th class="px-8 py-4 font-bold text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/10">
                            <?php if (count($clientes) > 0): ?>
                                <?php foreach ($clientes as $c): ?>
                                <tr class="hover:bg-surface-variant/5 transition-colors group">
                                    <td class="px-8 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-surface-container-highest flex items-center justify-center font-bold text-primary"><?= strtoupper(substr($c['nome'],0,2)) ?></div>
                                            <div>
                                                <p class="text-body-md font-bold text-on-surface leading-tight"><?= htmlspecialchars($c['nome']) ?></p>
                                                <p class="text-xs text-on-surface-variant opacity-60"><?= htmlspecialchars($c['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="flex flex-col">
                                            <span class="text-body-sm text-on-surface font-mono"><?= htmlspecialchars($c['dominio'] ?? 'pendente') ?></span>
                                            <span class="text-[9px] text-on-surface-variant uppercase font-bold tracking-tighter mt-1 italic">Status: Ativo</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="bg-surface-container px-3 py-2 rounded-lg border border-outline-variant/20 flex items-center justify-between group-hover:border-primary/30 transition-all">
                                            <span class="text-[10px] text-primary font-mono"><?= substr($c['license_key'] ?? 'N/A', 0, 20) ?>...</span>
                                            <span class="material-symbols-outlined text-[14px] text-secondary">verified</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="flex items-center justify-center gap-3">
                                            <a href="editar_cliente.php?id=<?= $c['id'] ?>" class="p-2 bg-surface-container rounded-lg text-on-surface-variant hover:text-primary transition-all">
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                            </a>
                                            <a href="?delete=<?= $c['id'] ?>" onclick="return confirm('Deseja realmente excluir este cliente?')" 
                                               class="p-2 bg-surface-container rounded-lg text-on-surface-variant hover:text-error transition-all">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="p-20 text-center">
                                        <span class="material-symbols-outlined text-6xl text-surface-container-highest mb-4">search_off</span>
                                        <p class="text-on-surface-variant text-sm">Nenhum cliente encontrado.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'templates/footer.php'; ?>
