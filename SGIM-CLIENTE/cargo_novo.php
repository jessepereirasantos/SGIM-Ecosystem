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
    $stmt = $pdo->query("SELECT * FROM permissoes ORDER BY modulo ASC");
    $permissoes_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $mensagem = "Aviso: Estrutura de permissões não detectada. Execute a atualização via OTA.";
}

// 2. PROCESSAMENTO DO FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $escopo = $_POST['escopo'] ?? 'local';
    $perms_selecionadas = $_POST['perms'] ?? [];
    $departamento_id = !empty($_POST['departamento_id']) ? $_POST['departamento_id'] : null;

    if (empty($nome)) {
        $erro = true;
        $mensagem = "O nome do cargo é obrigatório.";
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO cargos (nome, escopo, departamento_id, status) VALUES (?, ?, ?, 'Ativo')");
            $stmt->execute([$nome, $escopo, $departamento_id]);
            $cargo_id = $pdo->lastInsertId();

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

<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                <span class="material-symbols-outlined text-brand text-4xl">verified_user</span>
                Novo Cargo Ministerial
            </h2>
            <p class="text-sm text-gray-400 mt-1 uppercase tracking-widest">Definição de Hierarquia e Poderes</p>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="mb-6 p-4 rounded-xl <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-brand/10 border-brand/20 text-brand' ?> border flex items-center gap-3">
            <span class="material-symbols-outlined"><?= $erro ? 'error' : 'info' ?></span>
            <p class="text-sm font-bold"><?= htmlspecialchars($mensagem) ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Coluna 1: Dados Básicos (4 colunas) -->
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-darkcard rounded-2xl border border-darkborder p-6 shadow-2xl">
                    <h3 class="text-xs font-black text-brand uppercase tracking-[0.3em] mb-6 border-b border-white/5 pb-4">Identidade do Cargo</h3>
                    
                    <div class="space-y-6">
                        <div class="group">
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2 group-focus-within:text-brand transition-colors">Nome do Cargo</label>
                            <input name="nome" required class="w-full px-4 py-4 rounded-xl border border-darkborder bg-black text-white focus:ring-2 focus:ring-brand focus:border-transparent outline-none transition-all placeholder:text-gray-700" placeholder="Ex: Pastor Local" type="text"/>
                        </div>

                        <div class="group">
                            <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2 group-focus-within:text-brand transition-colors">Escopo de Visão</label>
                            <select name="escopo" class="w-full px-4 py-4 rounded-xl border border-darkborder bg-black text-white focus:ring-2 focus:ring-brand outline-none appearance-none cursor-pointer">
                                <option value="local" class="bg-darkcard py-4">LOCAL (Filtro por congregação)</option>
                                <option value="global" class="bg-darkcard py-4">GLOBAL (Acesso Total)</option>
                            </select>
                        </div>

                        <div class="group">
                            <label class="block text-[10px] font-bold text-brand uppercase tracking-widest mb-2">Preset de Nível</label>
                            <select id="preset_nivel" onchange="applyPreset(this.value)" class="w-full px-4 py-4 rounded-xl border-2 border-brand/30 bg-brand/5 text-brand font-black focus:ring-2 focus:ring-brand outline-none appearance-none cursor-pointer hover:bg-brand/10 transition-all">
                                <option value="" class="bg-darkcard">Personalizado</option>
                                <option value="admin_total" class="bg-darkcard">Admin Total</option>
                                <option value="admin_secretaria" class="bg-darkcard">Admin Secretaria</option>
                                <option value="admin_tesouraria" class="bg-darkcard">Admin Tesouraria</option>
                                <option value="pastor_local" class="bg-darkcard">Pastor Local</option>
                                <option value="secretario_local" class="bg-darkcard">Secretário Local</option>
                                <option value="tesoureiro_local" class="bg-darkcard">Tesoureiro Local</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="p-6 rounded-2xl bg-gradient-to-br from-brand/20 to-transparent border border-brand/20 shadow-lg shadow-brand/5">
                    <h4 class="text-brand font-black text-sm mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">security</span>
                        Segurança Ministerial
                    </h4>
                    <p class="text-xs text-gray-300 leading-relaxed">
                        Ao selecionar um nível pré-definido, o sistema ajusta as permissões críticas. Você pode refiná-las manualmente na matriz ao lado.
                    </p>
                </div>
            </div>

            <!-- Coluna 2: Matriz de Permissões (8 colunas) -->
            <div class="lg:col-span-8 space-y-6">
                <div class="bg-darkcard rounded-2xl border border-darkborder p-8 shadow-2xl">
                    <div class="flex items-center justify-between mb-10 border-b border-white/5 pb-6">
                        <div>
                            <h3 class="text-xl font-bold text-white tracking-tighter">Matriz de Autoridade</h3>
                            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1">Concessão de privilégios por módulo</p>
                        </div>
                        <button type="button" onclick="toggleAll()" class="px-5 py-2.5 rounded-xl bg-white/5 border border-darkborder text-[10px] font-black text-brand uppercase hover:bg-brand/20 transition-all border-dashed">
                            Alternar Seleção Global
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php 
                        $modulos = [];
                        foreach ($permissoes_disponiveis as $p) {
                            $modulos[$p['modulo']][] = $p;
                        }
                        
                        foreach ($modulos as $modulo => $acoes): 
                        ?>
                            <div class="p-6 rounded-2xl bg-black/40 border border-darkborder group hover:border-brand/40 transition-all">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="size-10 rounded-xl bg-brand/10 flex items-center justify-center text-brand group-hover:bg-brand group-hover:text-black transition-all duration-500">
                                        <span class="material-symbols-outlined text-xl">
                                            <?= ($modulo == 'financeiro') ? 'payments' : (($modulo == 'membros') ? 'group' : 'settings_accessibility') ?>
                                        </span>
                                    </div>
                                    <h4 class="font-black text-white uppercase tracking-widest text-xs"><?= $modulo ?></h4>
                                </div>
                                
                                <div class="space-y-4">
                                    <?php foreach ($acoes as $acao): ?>
                                        <label class="flex items-center gap-4 cursor-pointer group/item">
                                            <div class="relative flex items-center justify-center">
                                                <input type="checkbox" name="perms[]" value="<?= $acao['id'] ?>" 
                                                       data-modulo="<?= $modulo ?>" data-acao="<?= $acao['acao'] ?>"
                                                       class="peer appearance-none size-6 rounded-lg border-2 border-darkborder bg-black checked:bg-brand checked:border-brand transition-all cursor-pointer">
                                                <span class="material-symbols-outlined absolute text-[16px] text-black font-black opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none">check</span>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-bold text-gray-100 group-hover/item:text-brand transition-colors">
                                                    <?= ucfirst($acao['acao']) ?>
                                                </span>
                                                <span class="text-[10px] text-gray-500 font-bold uppercase tracking-tighter opacity-80">
                                                    <?= $acao['descricao'] ?>
                                                </span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-6 pt-4">
                    <a href="departamentos.php" class="px-8 py-4 rounded-xl border border-darkborder text-gray-500 font-bold hover:bg-white/5 hover:text-white transition-all uppercase text-xs tracking-widest">Cancelar</a>
                    <button type="submit" class="px-14 py-5 rounded-2xl bg-brand hover:bg-brand-dark text-black font-black shadow-2xl shadow-brand/40 transition-all transform hover:-translate-y-1 active:scale-95 uppercase tracking-widest">
                        Gravar Cargo Ministerial
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
