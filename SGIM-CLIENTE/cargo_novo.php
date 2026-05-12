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
            <h2 class="text-3xl font-black text-white tracking-tighter flex items-center gap-3">
                <span class="material-symbols-outlined text-brand text-4xl">verified_user</span>
                GERENCIAR CARGOS (v1.1.30)
            </h2>
            <p class="text-[10px] text-brand font-black uppercase tracking-[0.3em] mt-1">Hierarquia e Controle de Acessos</p>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="mb-6 p-4 rounded-xl bg-brand/10 border border-brand/20 text-brand flex items-center gap-3">
            <span class="material-symbols-outlined">info</span>
            <p class="text-sm font-bold"><?= htmlspecialchars($mensagem) ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Coluna 1: Dados Básicos -->
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-darkcard rounded-2xl border border-darkborder p-8 shadow-2xl">
                    <h3 class="text-[10px] font-black text-brand uppercase tracking-widest mb-8 border-b border-white/5 pb-4">Configuração Base</h3>
                    
                    <div class="space-y-8">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Nome do Cargo</label>
                            <input name="nome" required class="w-full px-5 py-4 rounded-xl border-2 border-darkborder bg-black text-white focus:border-brand outline-none transition-all placeholder:text-gray-800 font-bold" placeholder="Ex: Pastor Local" type="text"/>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Escopo Ministerial</label>
                            <select name="escopo" class="w-full px-5 py-4 rounded-xl border-2 border-darkborder bg-black text-white focus:border-brand outline-none cursor-pointer font-bold">
                                <option value="local">VISÃO LOCAL (Apenas Congregação)</option>
                                <option value="global">VISÃO GLOBAL (Todo o Ministério)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-brand uppercase tracking-widest mb-3">Preset Inteligente</label>
                            <select id="preset_nivel" onchange="applyPreset(this.value)" class="w-full px-5 py-4 rounded-xl border-2 border-brand/40 bg-brand/10 text-brand font-black focus:border-brand outline-none cursor-pointer hover:bg-brand/20 transition-all">
                                <option value="" class="bg-darkcard">Personalizado</option>
                                <option value="admin_total" class="bg-darkcard">Admin Total</option>
                                <option value="pastor_local" class="bg-darkcard">Pastor Local</option>
                                <option value="secretario_local" class="bg-darkcard">Secretário Local</option>
                                <option value="tesoureiro_local" class="bg-darkcard">Tesoureiro Local</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna 2: Matriz de Autoridade -->
            <div class="lg:col-span-8">
                <div class="bg-darkcard rounded-3xl border border-darkborder p-10 shadow-2xl relative overflow-hidden">
                    <!-- Detalhe decorativo lateral -->
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-brand"></div>

                    <div class="flex items-center justify-between mb-12">
                        <div>
                            <h3 class="text-2xl font-black text-white tracking-tighter">MATRIZ DE AUTORIDADE</h3>
                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2">Defina os poderes deste cargo no sistema</p>
                        </div>
                        <button type="button" onclick="toggleAll()" class="px-6 py-3 rounded-xl bg-white/5 border border-darkborder text-[10px] font-black text-brand uppercase hover:bg-brand/20 transition-all">Selecionar Tudo</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php 
                        $modulos = [];
                        foreach ($permissoes_disponiveis as $p) {
                            $modulos[$p['modulo']][] = $p;
                        }
                        
                        foreach ($modulos as $modulo => $acoes): 
                        ?>
                            <div class="p-6 rounded-2xl bg-black border border-darkborder hover:border-brand/30 transition-all group">
                                <div class="flex items-center gap-4 mb-6 border-b border-white/5 pb-4">
                                    <div class="size-10 rounded-xl bg-brand/5 flex items-center justify-center text-brand group-hover:bg-brand group-hover:text-black transition-all">
                                        <span class="material-symbols-outlined text-xl">
                                            <?= ($modulo == 'financeiro') ? 'payments' : (($modulo == 'membros') ? 'group' : 'shield_person') ?>
                                        </span>
                                    </div>
                                    <h4 class="font-black text-white uppercase tracking-widest text-xs"><?= $modulo ?></h4>
                                </div>
                                
                                <div class="space-y-5">
                                    <?php foreach ($acoes as $acao): ?>
                                        <label class="flex items-center gap-4 cursor-pointer group/item">
                                            <div class="relative flex items-center justify-center">
                                                <input type="checkbox" name="perms[]" value="<?= $acao['id'] ?>" 
                                                       data-modulo="<?= $modulo ?>" data-acao="<?= $acao['acao'] ?>"
                                                       class="peer appearance-none size-6 rounded-lg border-2 border-darkborder bg-darkcard checked:bg-brand checked:border-brand transition-all cursor-pointer">
                                                <span class="material-symbols-outlined absolute text-[16px] text-black font-black opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none">check</span>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-black text-white group-hover/item:text-brand transition-colors tracking-tight">
                                                    <?= ucfirst($acao['acao']) ?>
                                                </span>
                                                <span class="text-[9px] text-gray-400 font-bold uppercase opacity-60 group-hover/item:opacity-100">
                                                    <?= $acao['descricao'] ?>
                                                </span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-12 flex items-center justify-end gap-6 pt-8 border-t border-white/5">
                        <a href="departamentos.php" class="text-xs font-black text-gray-500 uppercase tracking-widest hover:text-white transition-colors">Cancelar</a>
                        <button type="submit" class="px-16 py-5 rounded-2xl bg-brand hover:bg-brand-dark text-black font-black shadow-2xl shadow-brand/20 transition-all transform hover:-translate-y-1 active:scale-95 uppercase tracking-[0.2em] text-sm">
                            Gravar Autoridade
                        </button>
                    </div>
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
            case 'pastor_local':
                checks.forEach(c => {
                    if (['membros', 'financeiro', 'eventos'].includes(c.dataset.modulo)) c.checked = true;
                });
                escopo.value = 'local';
                break;
            case 'secretario_local':
                checks.forEach(c => {
                    if (['membros', 'eventos'].includes(c.dataset.modulo)) c.checked = true;
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
