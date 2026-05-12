<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

$mensagem = '';
$erro = false;

// 1. BUSCA CATÁLOGO DE PERMISSÕES
$permissoes_disponiveis = [];
try {
    // Tenta buscar da nova tabela (se a migration já tiver rodado)
    $stmt = $pdo->query("SELECT * FROM permissoes ORDER BY modulo ASC");
    $permissoes_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Se a tabela não existir ainda, a migration rodará no próximo OTA
    // Mas para evitar que a tela quebre no primeiro acesso local:
    $mensagem = "Aviso: Estrutura de permissões não detectada. Execute a atualização via OTA.";
}

// 2. PROCESSAMENTO DO FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $escopo = $_POST['escopo'] ?? 'local';
    $perms_selecionadas = $_POST['perms'] ?? []; // Array de IDs de permissão
    $departamento_id = !empty($_POST['departamento_id']) ? $_POST['departamento_id'] : null;

    if (empty($nome)) {
        $erro = true;
        $mensagem = "O nome do cargo é obrigatório.";
    } else {
        try {
            $pdo->beginTransaction();

            // Insere o cargo
            $stmt = $pdo->prepare("INSERT INTO cargos (nome, escopo, departamento_id, status) VALUES (?, ?, ?, 'Ativo')");
            $stmt->execute([$nome, $escopo, $departamento_id]);
            $cargo_id = $pdo->lastInsertId();

            // Insere as permissões vinculadas
            if (!empty($perms_selecionadas)) {
                $stmtPerm = $pdo->prepare("INSERT INTO cargo_permissoes (cargo_id, permissao_id) VALUES (?, ?)");
                foreach ($perms_selecionadas as $pid) {
                    $stmtPerm->execute([$cargo_id, $pid]);
                }
            }

            $pdo->commit();
            header("Location: departamentos.php?sucesso=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = true;
            $mensagem = "Erro ao criar cargo: " . $e->getMessage();
        }
    }
}

$page_title = 'SGIM - Novo Cargo Ministerial';
$current_page = 'departamentos';

require_once 'includes/header.php';
?>

<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                <span class="material-symbols-outlined text-brand text-4xl">verified_user</span>
                Novo Cargo Ministerial
            </h2>
            <p class="text-sm text-gray-500 mt-1">Defina a hierarquia e os limites de acesso deste cargo no ecossistema.</p>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="mb-6 p-4 rounded-twelve <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-brand/10 border-brand/20 text-brand' ?> border flex items-center gap-3 animate-pulse">
            <span class="material-symbols-outlined"><?= $erro ? 'error' : 'info' ?></span>
            <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem) ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Coluna 1: Dados Básicos -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-darkcard rounded-2xl border border-darkborder p-6 shadow-xl relative overflow-hidden group">
                    <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                        <span class="material-symbols-outlined text-6xl">badge</span>
                    </div>
                    
                    <h3 class="text-lg font-bold text-white mb-6">Identidade</h3>
                    
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Nome do Cargo</label>
                            <input name="nome" required class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand focus:border-transparent outline-none transition-all" placeholder="Ex: Pastor Local" type="text"/>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Escopo de Visão</label>
                            <select name="escopo" class="w-full px-4 py-3 rounded-xl border border-darkborder bg-darkbg text-white focus:ring-2 focus:ring-brand outline-none appearance-none">
                                <option value="local">LOCAL (Apenas sua congregação)</option>
                                <option value="global">GLOBAL (Todo o Ministério)</option>
                            </select>
                            <p class="text-[10px] text-gray-500 italic">Cargos globais ignoram filtros de igreja.</p>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest">Preset de Nível (Sugestão)</label>
                            <select id="preset_nivel" onchange="applyPreset(this.value)" class="w-full px-4 py-3 rounded-xl border border-brand/20 bg-brand/5 text-brand font-bold focus:ring-2 focus:ring-brand outline-none appearance-none cursor-pointer">
                                <option value="">Personalizado</option>
                                <option value="admin_total">Admin Total</option>
                                <option value="admin_secretaria">Admin Secretaria</option>
                                <option value="admin_tesouraria">Admin Tesouraria</option>
                                <option value="pastor_local">Pastor Local</option>
                                <option value="secretario_local">Secretário Local</option>
                                <option value="tesoureiro_local">Tesoureiro Local</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="p-6 rounded-2xl bg-gradient-to-br from-brand/10 to-transparent border border-brand/20">
                    <h4 class="text-brand font-bold text-sm mb-2">Dica Operacional</h4>
                    <p class="text-xs text-gray-400 leading-relaxed">
                        Ao selecionar um **Nível Base**, o SGIM marcará automaticamente as permissões recomendadas. Você ainda poderá ajustá-las manualmente à direita.
                    </p>
                </div>
            </div>

            <!-- Coluna 2: Matriz de Permissões (Grid) -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-darkcard rounded-2xl border border-darkborder p-8 shadow-xl">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-xl font-bold text-white">Matriz de Permissões</h3>
                            <p class="text-sm text-gray-500">Marque os módulos que este cargo terá autoridade para acessar.</p>
                        </div>
                        <button type="button" onclick="toggleAll()" class="text-[10px] font-bold text-brand uppercase hover:underline">Marcar/Desmarcar Todos</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php 
                        $modulos = [];
                        foreach ($permissoes_disponiveis as $p) {
                            $modulos[$p['modulo']][] = $p;
                        }
                        
                        foreach ($modulos as $modulo => $acoes): 
                        ?>
                            <div class="p-5 rounded-xl bg-darkbg border border-darkborder group hover:border-brand/30 transition-all">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="size-8 rounded-lg bg-brand/10 flex items-center justify-center text-brand">
                                        <span class="material-symbols-outlined text-sm">
                                            <?= ($modulo == 'financeiro') ? 'payments' : (($modulo == 'membros') ? 'group' : 'settings') ?>
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-white capitalize"><?= $modulo ?></h4>
                                </div>
                                
                                <div class="space-y-3">
                                    <?php foreach ($acoes as $acao): ?>
                                        <label class="flex items-center gap-3 cursor-pointer group/item">
                                            <div class="relative flex items-center justify-center">
                                                <input type="checkbox" name="perms[]" value="<?= $acao['id'] ?>" 
                                                       data-modulo="<?= $modulo ?>" data-acao="<?= $acao['acao'] ?>"
                                                       class="peer appearance-none size-5 rounded-md border border-darkborder bg-darkcard checked:bg-brand checked:border-brand transition-all">
                                                <span class="material-symbols-outlined absolute text-[14px] text-black font-bold opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none">check</span>
                                            </div>
                                            <span class="text-sm text-gray-400 group-hover/item:text-gray-200 transition-colors">
                                                <?= ucfirst($acao['acao']) ?> <span class="text-[10px] opacity-40 ml-1">- <?= $acao['descricao'] ?></span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-4">
                    <a href="departamentos.php" class="px-8 py-4 rounded-xl border border-darkborder text-gray-400 font-bold hover:bg-white/5 transition-all">Cancelar</a>
                    <button type="submit" class="px-12 py-4 rounded-xl bg-brand hover:bg-brand-dark text-black font-black shadow-2xl shadow-brand/20 transition-all transform hover:scale-105 active:scale-95">
                        GRAVAR NOVO CARGO
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function toggleAll() {
        const checks = document.querySelectorAll('input[type="checkbox"]');
        const firstValue = checks[0].checked;
        checks.forEach(c => c.checked = !firstValue);
    }

    function applyPreset(level) {
        const checks = document.querySelectorAll('input[type="checkbox"]');
        const escopo = document.querySelector('select[name="escopo"]');
        
        // Reseta tudo
        checks.forEach(c => c.checked = false);
        
        switch(level) {
            case 'admin_total':
                checks.forEach(c => c.checked = true);
                escopo.value = 'global';
                break;
            case 'admin_secretaria':
                checks.forEach(c => {
                    if (['membros', 'comunicacao', 'eventos', 'congregacoes'].includes(c.dataset.modulo)) c.checked = true;
                });
                escopo.value = 'global';
                break;
            case 'admin_tesouraria':
                checks.forEach(c => {
                    if (['financeiro'].includes(c.dataset.modulo)) c.checked = true;
                });
                escopo.value = 'global';
                break;
            case 'pastor_local':
                checks.forEach(c => {
                    if (['membros', 'financeiro', 'eventos', 'comunicacao'].includes(c.dataset.modulo)) c.checked = true;
                });
                escopo.value = 'local';
                break;
            case 'secretario_local':
                checks.forEach(c => {
                    if (['membros', 'comunicacao', 'eventos'].includes(c.dataset.modulo)) c.checked = true;
                });
                escopo.value = 'local';
                break;
            case 'tesoureiro_local':
                checks.forEach(c => {
                    if (['financeiro'].includes(c.dataset.modulo)) c.checked = true;
                });
                escopo.value = 'local';
                break;
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
