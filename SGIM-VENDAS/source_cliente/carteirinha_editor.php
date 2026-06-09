<?php
// AUTO-PONTE: Se existir uma versão mais nova ativa pelo OTA, desvia para ela
$bridge = __DIR__ . '/releases/current/' . basename(__FILE__);
if (file_exists($bridge) && strpos(__DIR__, 'releases') === false) {
    require_once $bridge;
    exit;
}

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/autoload.php';

// Proteção contra PDO nulo ou falhas de conexão
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
    exit;
}

// 🛡️ Inicializa o AccessManager para proteção de rota
if (!class_exists('SGIM\\Auth\\AccessManager')) {
    $amPath = __DIR__ . '/src/Auth/AccessManager.php';
    if (file_exists($amPath)) require_once $amPath;
}
$access = new \SGIM\Auth\AccessManager($pdo, $_SESSION['user_id']);

// Apenas administradores do Ministério (Global) podem gerenciar os modelos de carteirinha
if (!$access || !$access->isGlobal()) {
    echo "<script>alert('Acesso Negado: Apenas administradores globais do Ministério podem gerenciar modelos de carteirinhas.'); window.location.href='dashboard.php';</script>";
    exit;
}

use App\Controllers\CarteirinhaController;

$controller = new CarteirinhaController($pdo);
$mensagem = '';
$erro = false;

// Trata mensagens de redirecionamento com sucesso
if (isset($_GET['sucesso'])) {
    if ($_GET['sucesso'] == 1) {
        $mensagem = "Modelo de carteirinha salvo com sucesso!";
    } elseif ($_GET['sucesso'] == 2) {
        $mensagem = "Modelo de carteirinha excluído com sucesso!";
    }
}

// 1. Processamento de Ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao'])) {
        if ($_POST['acao'] === 'salvar') {
            $nome = $_POST['nome'] ?? '';
            $cargos = $_POST['cargos'] ?? [];
            $elementos = $_POST['elementos_json'] ?? '[]';
            $elementos_verso = $_POST['elementos_verso_json'] ?? '[]';
            $template_id = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;
            
            $fundo = (isset($_FILES['fundo']) && $_FILES['fundo']['error'] === UPLOAD_ERR_OK) ? $_FILES['fundo'] : null;
            $fundo_verso = (isset($_FILES['fundo_verso']) && $_FILES['fundo_verso']['error'] === UPLOAD_ERR_OK) ? $_FILES['fundo_verso'] : null;
            $logo = (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) ? $_FILES['logo'] : null;
            $assinatura = (isset($_FILES['assinatura']) && $_FILES['assinatura']['error'] === UPLOAD_ERR_OK) ? $_FILES['assinatura'] : null;

            $res = $controller->saveTemplate($nome, $cargos, $fundo, $logo, $assinatura, $elementos, $template_id, $fundo_verso, $elementos_verso);
            if ($res['success']) {
                header("Location: carteirinha_editor.php?sucesso=1");
                exit;
            } else {
                $erro = true;
                $mensagem = $res['message'];
            }
        } elseif ($_POST['acao'] === 'excluir') {
            $id = intval($_POST['id'] ?? 0);
            $res = $controller->deleteTemplate($id);
            if ($res['success']) {
                header("Location: carteirinha_editor.php?sucesso=2");
                exit;
            } else {
                $erro = true;
                $mensagem = $res['message'];
            }
        }
    }
}

// 2. Busca lista de cargos para o formulário
$cargos_list = $pdo->query("SELECT id, nome FROM cargos WHERE status = 'Ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Determina o modo de exibição (Lista ou Editor)
$modo = 'lista';
$template_atual = null;

if (isset($_GET['novo'])) {
    $modo = 'editor';
} elseif (isset($_GET['editar'])) {
    $modo = 'editor';
    $template_atual = $controller->getTemplate(intval($_GET['editar']));
}

$page_title = 'SGIM - Editor de Carteirinhas';
$current_page = 'carteirinhas';

require_once 'includes/header.php';
?>

<div class="flex flex-col h-[calc(100vh-140px)] overflow-hidden rounded-xl border border-darkborder bg-darkcard">
    
    <!-- Editor Header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-darkborder bg-white/[0.02]">
        <div class="flex items-center gap-4">
            <div class="flex items-center justify-center size-10 rounded-xl bg-brand text-black shadow-lg shadow-brand/20">
                <span class="material-symbols-outlined font-bold">badge</span>
            </div>
            <div>
                <h2 class="text-white text-lg font-black tracking-tighter leading-tight">
                    <?= $modo === 'lista' ? 'Modelos de Carteirinhas' : ($template_atual ? 'Editar Modelo: ' . htmlspecialchars($template_atual['nome']) : 'Novo Modelo de Carteirinha') ?>
                </h2>
                <p class="text-gray-500 text-[10px] uppercase tracking-widest font-bold">Ambiente Canva & Vinculação de Cargos</p>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <a href="carteirinha_digital.php" class="flex items-center gap-2 px-4 py-2 rounded-xl border border-darkborder bg-darkbg text-gray-300 text-xs font-bold hover:border-brand/50 transition-all">
                <span class="material-symbols-outlined text-base">badge</span>
                Membros
            </a>
            <?php if ($modo === 'lista'): ?>
                <a href="carteirinha_editor.php?novo=1" class="flex items-center gap-2 px-6 py-2 rounded-xl bg-brand text-black text-xs font-black hover:bg-brand-light transition-colors shadow-lg shadow-brand/20">
                    <span class="material-symbols-outlined text-base">add</span>
                    Novo Modelo
                </a>
            <?php else: ?>
                <a href="carteirinha_editor.php" class="flex items-center gap-2 px-4 py-2 rounded-xl border border-darkborder bg-darkbg text-gray-300 text-xs font-bold hover:border-brand/50 transition-all">
                    <span class="material-symbols-outlined text-base">close</span>
                    Cancelar
                </a>
                <button type="button" onclick="document.getElementById('form-save-template').click()" class="flex items-center gap-2 px-6 py-2 rounded-xl bg-brand text-black text-xs font-black hover:bg-brand-light transition-colors shadow-lg shadow-brand/20">
                    <span class="material-symbols-outlined text-base">save</span>
                    Salvar Modelo
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mensagens de Alerta -->
    <?php if (!empty($mensagem)): ?>
        <div class="p-4 mx-6 mt-4 rounded-xl border <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-400' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> text-xs font-bold flex items-center justify-between">
            <span class="flex items-center gap-2">
                <span class="material-symbols-outlined text-sm"><?= $erro ? 'error' : 'check_circle' ?></span>
                <?= htmlspecialchars($mensagem) ?>
            </span>
            <button onclick="this.parentElement.remove()" class="hover:text-white">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($modo === 'lista'): ?>
        <!-- MODO LISTAGEM -->
        <div class="flex-1 p-6 overflow-y-auto">
            <?php 
            $templates = $controller->getTemplates();
            if (empty($templates)): 
            ?>
                <div class="flex flex-col items-center justify-center h-64 border border-dashed border-darkborder rounded-2xl bg-white/[0.01]">
                    <span class="material-symbols-outlined text-5xl text-gray-600 mb-3">badge</span>
                    <h3 class="text-white font-bold text-sm">Nenhum modelo de carteirinha cadastrado</h3>
                    <p class="text-gray-500 text-xs mt-1">Crie seu primeiro modelo de carteirinha e vincule aos cargos da igreja.</p>
                    <a href="carteirinha_editor.php?novo=1" class="mt-4 px-6 py-2 bg-brand text-black text-xs font-black rounded-xl hover:bg-brand-light transition-all shadow-md">Criar Modelo</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($templates as $t): ?>
                        <div class="border border-darkborder bg-darkbg rounded-2xl p-5 flex flex-col justify-between hover:border-brand/40 transition-all">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-white text-sm font-black tracking-tight leading-tight"><?= htmlspecialchars($t['nome']) ?></h3>
                                    <span class="bg-brand/10 text-brand text-[9px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider">
                                        <?= $t['status'] ?>
                                    </span>
                                </div>
                                
                                <div class="h-28 bg-[#0c0c0c] border border-white/5 rounded-xl overflow-hidden relative flex items-center justify-center">
                                    <?php if ($t['fundo_url']): ?>
                                        <img src="<?= htmlspecialchars($t['fundo_url']) ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="text-center text-gray-700">
                                            <span class="material-symbols-outlined text-3xl">image</span>
                                            <p class="text-[9px] uppercase font-bold mt-1">Fundo Padrão</p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Pequena miniatura de logo -->
                                    <?php if ($t['logo_url']): ?>
                                        <img src="<?= htmlspecialchars($t['logo_url']) ?>" class="absolute top-2 left-2 max-h-6 object-contain bg-black/40 p-0.5 rounded">
                                    <?php endif; ?>
                                </div>

                                <div class="space-y-1">
                                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider">Cargos Vinculados</p>
                                    <p class="text-xs font-medium text-gray-300">
                                        <?= $t['cargos_nomes'] ? htmlspecialchars($t['cargos_nomes']) : '<span class="text-brand/60 font-bold">Geral (Todos os Cargos)</span>' ?>
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 mt-6 pt-4 border-t border-darkborder">
                                <a href="carteirinha_editor.php?editar=<?= $t['id'] ?>" class="flex-1 py-2 text-center rounded-xl bg-white/5 border border-white/5 hover:border-brand/50 text-gray-300 hover:text-brand text-xs font-bold transition-all">
                                    Editar Visual
                                </a>
                                <form action="carteirinha_editor.php" method="POST" onsubmit="return confirm('Deseja realmente excluir este modelo? Todos os cargos vinculados perderão este layout.');">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="p-2 rounded-xl bg-red-500/10 border border-red-500/10 hover:border-red-500 text-red-400 hover:bg-red-500/20 text-xs font-bold transition-all flex items-center justify-center">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- MODO EDITOR (CANVA) -->
        <div class="flex flex-1 overflow-hidden" x-data="canvaEditor(<?= htmlspecialchars($template_atual ? $template_atual['elementos_json'] : '[]') ?>, <?= htmlspecialchars(($template_atual && $template_atual['elementos_verso_json']) ? $template_atual['elementos_verso_json'] : '[]') ?>)">
            
            <!-- Element Sidebar (Painel Esquerdo) -->
            <aside class="w-80 border-r border-darkborder bg-darkcard flex flex-col shrink-0 overflow-y-auto">
                <form id="form-template" action="carteirinha_editor.php" method="POST" enctype="multipart/form-data" class="p-5 space-y-6">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="template_id" value="<?= $template_atual ? $template_atual['id'] : '' ?>">
                    <input type="hidden" name="elementos_json" :value="JSON.stringify(elements)">
                    <input type="hidden" name="elementos_verso_json" :value="JSON.stringify(versoElements)">

                    <!-- Detalhes do Modelo -->
                    <div class="space-y-3">
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block">Configuração do Modelo</label>
                        <input type="text" name="nome" value="<?= $template_atual ? htmlspecialchars($template_atual['nome']) : '' ?>" placeholder="Ex: Modelo Geral, Carteira Pastor" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-2.5 text-xs text-white placeholder-gray-600 focus:border-brand focus:ring-0" required>
                    </div>

                    <!-- Vínculo por Cargos -->
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Vincular a Cargos</label>
                            <button type="button" @click="toggleSelectAllCargos" class="text-[9px] text-brand hover:underline font-bold uppercase">Inverter</button>
                        </div>
                        <div class="bg-darkbg border border-darkborder rounded-xl p-3 max-h-40 overflow-y-auto space-y-2">
                            <label class="flex items-center gap-2.5 text-xs font-bold text-gray-400 hover:text-white cursor-pointer select-none">
                                <input type="checkbox" id="cargo-geral-chk" @change="chkGeralChanged" class="rounded border-darkborder bg-darkbg text-brand focus:ring-0 size-4">
                                <span>Geral (Todos os Cargos)</span>
                            </label>
                            <hr class="border-darkborder my-1">
                            <?php 
                            $cargos_selecionados = $template_atual ? $template_atual['cargos'] : [];
                            foreach ($cargos_list as $cargo): 
                                $checked = in_array($cargo['id'], $cargos_selecionados) ? 'checked' : '';
                            ?>
                                <label class="flex items-center gap-2.5 text-xs text-gray-400 hover:text-white cursor-pointer select-none pl-2 cargo-item-label">
                                    <input type="checkbox" name="cargos[]" value="<?= $cargo['id'] ?>" <?= $checked ?> class="cargo-chk rounded border-darkborder bg-darkbg text-brand focus:ring-0 size-4">
                                    <span><?= htmlspecialchars($cargo['nome']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Assets de Upload -->
                    <div class="space-y-4">
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block">Upload de Recursos</label>
                        
                        <!-- Fundo -->
                        <div class="space-y-1">
                            <span class="text-[10px] text-gray-400 font-bold block">Fundo do Cartão (Recomendado: 450x280)</span>
                            <div class="flex items-center gap-2">
                                <input type="file" name="fundo" id="fundo-input" accept="image/*" class="hidden" @change="fundoUploaded">
                                <button type="button" onclick="document.getElementById('fundo-input').click()" class="flex-1 flex items-center justify-center gap-2 py-2 px-3 bg-darkbg border border-darkborder rounded-xl text-xs text-brand font-bold hover:bg-brand/5">
                                    <span class="material-symbols-outlined text-sm">upload</span>
                                    <span>Selecionar Fundo</span>
                                </button>
                                <?php if ($template_atual && $template_atual['fundo_url']): ?>
                                    <button type="button" @click="previewResource('<?= htmlspecialchars($template_atual['fundo_url']) ?>')" class="p-2 bg-darkbg border border-darkborder text-gray-400 hover:text-brand rounded-xl flex items-center justify-center">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Fundo do Verso -->
                        <div class="space-y-1">
                            <span class="text-[10px] text-gray-400 font-bold block">Fundo do Verso (Recomendado: 450x280)</span>
                            <div class="flex items-center gap-2">
                                <input type="file" name="fundo_verso" id="fundo-verso-input" accept="image/*" class="hidden" @change="fundoVersoUploaded">
                                <button type="button" onclick="document.getElementById('fundo-verso-input').click()" class="flex-1 flex items-center justify-center gap-2 py-2 px-3 bg-darkbg border border-darkborder rounded-xl text-xs text-brand font-bold hover:bg-brand/5">
                                    <span class="material-symbols-outlined text-sm">upload</span>
                                    <span>Selecionar Fundo Verso</span>
                                </button>
                                <?php if ($template_atual && $template_atual['fundo_verso_url']): ?>
                                    <button type="button" @click="previewResource('<?= htmlspecialchars($template_atual['fundo_verso_url']) ?>')" class="p-2 bg-darkbg border border-darkborder text-gray-400 hover:text-brand rounded-xl flex items-center justify-center">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Logo -->
                        <div class="space-y-1">
                            <span class="text-[10px] text-gray-400 font-bold block">Logotipo do Ministério</span>
                            <div class="flex items-center gap-2">
                                <input type="file" name="logo" id="logo-input" accept="image/*" class="hidden" @change="logoUploaded">
                                <button type="button" onclick="document.getElementById('logo-input').click()" class="flex-1 flex items-center justify-center gap-2 py-2 px-3 bg-darkbg border border-darkborder rounded-xl text-xs text-brand font-bold hover:bg-brand/5">
                                    <span class="material-symbols-outlined text-sm">upload</span>
                                    <span>Adicionar Logo</span>
                                </button>
                                <button type="button" @click="injectLogo" class="p-2 bg-darkbg border border-darkborder text-brand hover:bg-brand-light hover:text-black rounded-xl flex items-center justify-center" title="Inserir no Canvas">
                                    <span class="material-symbols-outlined text-sm">add</span>
                                </button>
                            </div>
                        </div>

                        <!-- Assinatura -->
                        <div class="space-y-1">
                            <span class="text-[10px] text-gray-400 font-bold block">Assinatura do Presidente</span>
                            <div class="flex items-center gap-2">
                                <input type="file" name="assinatura" id="assinatura-input" accept="image/*" class="hidden" @change="assinaturaUploaded">
                                <button type="button" onclick="document.getElementById('assinatura-input').click()" class="flex-1 flex items-center justify-center gap-2 py-2 px-3 bg-darkbg border border-darkborder rounded-xl text-xs text-brand font-bold hover:bg-brand/5">
                                    <span class="material-symbols-outlined text-sm">upload</span>
                                    <span>Adicionar Assinatura</span>
                                </button>
                                <button type="button" @click="injectAssinatura" class="p-2 bg-darkbg border border-darkborder text-brand hover:bg-brand-light hover:text-black rounded-xl flex items-center justify-center" title="Inserir no Canvas">
                                    <span class="material-symbols-outlined text-sm">add</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Inserir Texto Customizado -->
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block">Inserir Texto Customizado</label>
                        <div class="flex gap-2">
                            <input type="text" x-model="customText" placeholder="Escreva o texto aqui..." class="flex-1 bg-darkbg border border-darkborder rounded-xl px-3 py-2 text-xs text-white placeholder-gray-600 focus:border-brand focus:ring-0">
                            <button type="button" @click="addCustomText" class="p-2.5 bg-brand text-black hover:bg-brand-light rounded-xl flex items-center justify-center">
                                <span class="material-symbols-outlined text-sm font-bold">add</span>
                            </button>
                        </div>
                    </div>

                    <!-- Botão de salvamento escondido -->
                    <button type="submit" id="form-save-template" class="hidden"></button>
                </form>

                <!-- Elementos Dinâmicos de Clique Rápido -->
                <div class="p-5 border-t border-darkborder space-y-4">
                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest block">Campos Dinâmicos (Canva)</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="injectDynamic('nome_membro', '{Nome do Membro}')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">title</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">Nome</span>
                        </button>
                        <button type="button" @click="injectDynamic('nome_cargo', '{Cargo / Função}')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">assignment_ind</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">Cargo</span>
                        </button>
                        <button type="button" @click="injectDynamic('nome_congregacao', '{Congregação}')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">church</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">Congregação</span>
                        </button>
                        <button type="button" @click="injectDynamic('cpf_membro', '{CPF}')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">badge</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">CPF</span>
                        </button>
                        <button type="button" @click="injectDynamic('data_emissao', '{Data Emissão}')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">calendar_month</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">Emissão</span>
                        </button>
                        <button type="button" @click="injectDynamic('valida_ate', '{Válida Até}')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">event_busy</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">Validade</span>
                        </button>
                        <button type="button" @click="injectDynamic('qr_code', 'qr_code')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">qr_code_2</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">QR Code</span>
                        </button>
                        <button type="button" @click="injectDynamic('foto_membro', 'foto_membro')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">account_circle</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">Foto Membro</span>
                        </button>
                        <button type="button" @click="injectDynamic('rg_membro', '{RG}')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">id_card</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">RG</span>
                        </button>
                        <button type="button" @click="injectDynamic('telefone_membro', '{Telefone}')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">call</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">Telefone</span>
                        </button>
                        <button type="button" @click="injectDynamic('email_membro', '{E-mail}')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">mail</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">E-mail</span>
                        </button>
                        <button type="button" @click="injectDynamic('endereco_membro', '{Endereço}')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">home_pin</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">Endereço</span>
                        </button>
                        <button type="button" @click="injectDynamic('nascimento_membro', '{Nascimento}')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">cake</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">Nascimento</span>
                        </button>
                        <button type="button" @click="injectDynamic('batismo_membro', '{Data Batismo}')" class="flex items-center gap-2 p-2.5 rounded-xl bg-darkbg border border-darkborder hover:border-brand/40 text-left transition-all">
                            <span class="material-symbols-outlined text-sm text-brand">water_drop</span>
                            <span class="text-[10px] text-gray-400 font-bold whitespace-nowrap">Batismo</span>
                        </button>
                    </div>
                </div>
            </aside>

            <!-- Canvas Area (Centro) -->
            <main class="flex-1 bg-darkbg relative overflow-hidden flex flex-col p-8 select-none">
                <!-- Abas de Alternância Frente e Verso -->
                <div class="flex items-center justify-center gap-4 mb-6 relative z-10 no-print">
                    <button type="button" 
                            @click="viewMode = 'frente'; deselectAll();" 
                            class="px-8 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition-all flex items-center gap-2"
                            :class="viewMode === 'frente' ? 'bg-brand text-black shadow-lg shadow-brand/20' : 'bg-[#0c0c0c] border border-white/5 text-gray-400 hover:text-white'">
                        <span class="material-symbols-outlined text-sm">badge</span>
                        Frente do Cartão
                    </button>
                    <button type="button" 
                            @click="viewMode = 'verso'; deselectAll();" 
                            class="px-8 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition-all flex items-center gap-2"
                            :class="viewMode === 'verso' ? 'bg-brand text-black shadow-lg shadow-brand/20' : 'bg-[#0c0c0c] border border-white/5 text-gray-400 hover:text-white'">
                        <span class="material-symbols-outlined text-sm">settings_backup_restore</span>
                        Verso do Cartão
                    </button>
                </div>

                <div class="flex-1 flex items-center justify-center relative">
                    <div class="canvas-grid absolute inset-0 opacity-20 pointer-events-none"></div>
                    
                    <!-- Canvas Real da Carteirinha -->
                    <div id="card-canvas" 
                         class="relative w-[450px] h-[280px] bg-[#0A0A0A] rounded-2xl border-2 border-brand/30 shadow-2xl overflow-hidden"
                         :style="viewMode === 'frente' ? 
                                 (fundoSrc ? 'background-image: url(' + fundoSrc + '); background-size: cover; background-position: center;' : '') : 
                                 (fundoVersoSrc ? 'background-image: url(' + fundoVersoSrc + '); background-size: cover; background-position: center;' : '')"
                         @click="deselectAll"
                         @dragover.prevent
                         @drop="onDrop">
                         
                        <div class="absolute -top-20 -right-20 w-64 h-64 bg-brand/5 rounded-full blur-3xl pointer-events-none" x-show="viewMode === 'frente' ? !fundoSrc : !fundoVersoSrc"></div>
                        
                        <!-- Elementos inseridos dinamicamente -->
                        <template x-for="(el, index) in activeElements" :key="el.id">
                            <div class="absolute p-1 border select-none group"
                                 :class="selectedId === el.id ? 'border-brand shadow-lg outline outline-1 outline-brand' : 'border-transparent hover:border-white/20'"
                                 :style="{
                                     left: el.x + 'px', 
                                     top: el.y + 'px', 
                                     color: el.color || '#ffffff', 
                                     fontSize: (el.size || 12) + 'px', 
                                     fontWeight: el.bold ? 'bold' : 'normal',
                                     fontFamily: el.type === 'text' || el.type === 'dynamic' ? 'Inter, sans-serif' : 'monospace',
                                     cursor: 'move',
                                     zIndex: 20
                                 }"
                                 @mousedown="startDrag($event, el)"
                                 @click.stop="selectElement(el)">
                                 
                                 <!-- RENDERIZAÇÃO POR TIPO -->
                                 <template x-if="el.type === 'text'">
                                     <span x-text="el.value"></span>
                                 </template>
                                 
                                 <template x-if="el.type === 'dynamic'">
                                     <span class="font-bold border border-brand/30 bg-brand/5 px-1 py-0.5 rounded text-[0.8em]" x-text="el.value"></span>
                                 </template>

                                 <template x-if="el.type === 'foto_membro'">
                                     <div class="size-20 bg-darkbg border border-brand/50 rounded-lg flex flex-col items-center justify-center text-gray-500 shadow-inner">
                                         <span class="material-symbols-outlined text-2xl text-brand/60">person</span>
                                         <span class="text-[6px] uppercase font-black tracking-wider text-gray-500 mt-0.5">Foto do Membro</span>
                                     </div>
                                 </template>

                                 <template x-if="el.type === 'qr_code'">
                                     <div class="size-10 bg-white p-1 rounded flex items-center justify-center shadow">
                                         <span class="material-symbols-outlined text-black text-2xl font-bold">qr_code_2</span>
                                     </div>
                                 </template>

                                 <template x-if="el.type === 'logo'">
                                     <img :src="logoSrc || 'assets/images/logo.png'" 
                                          :style="{ width: (el.width || 120) + 'px', height: (el.height || 50) + 'px', objectFit: 'contain' }"
                                          class="pointer-events-none">
                                 </template>

                                 <template x-if="el.type === 'assinatura'">
                                     <div class="flex flex-col items-center">
                                         <img :src="assinaturaSrc || 'assets/images/signature.png'" 
                                              :style="{ width: (el.width || 100) + 'px', height: (el.height || 40) + 'px', objectFit: 'contain' }"
                                              class="pointer-events-none">
                                         <div class="w-20 h-px bg-white/20 my-0.5"></div>
                                         <span class="text-[5px] uppercase font-bold text-gray-500 tracking-wider">Assinatura</span>
                                     </div>
                                 </template>

                                 <!-- Alça de Redimensionamento Visual no canto inferior direito -->
                                 <template x-if="selectedId === el.id && (el.type === 'logo' || el.type === 'assinatura')">
                                     <div class="absolute -bottom-1.5 -right-1.5 size-3.5 bg-brand border-2 border-black rounded-full cursor-se-resize z-30 shadow-md hover:scale-125 transition-transform"
                                          @mousedown.stop.prevent="startResize($event, el)">
                                     </div>
                                 </template>

                                 <!-- Botão rápido de exclusão do elemento -->
                                 <button type="button" @click.stop="deleteElement(el)" class="absolute -top-2 -right-2 bg-red-500 text-white size-4 rounded-full text-[10px] hidden group-hover:flex items-center justify-center hover:bg-red-600 shadow-md">
                                     &times;
                                 </button>
                            </div>
                        </template>
                    </div>
                </div>
            </main>

            <!-- Properties Panel (Painel Direito) -->
            <aside class="w-72 border-l border-darkborder bg-darkcard flex flex-col shrink-0 p-6">
                <div class="flex-1 space-y-6">
                    <h3 class="text-xs font-black text-white flex items-center gap-2 mb-4 border-b border-darkborder pb-3">
                        <span class="material-symbols-outlined text-brand text-sm">settings</span>
                        Propriedades do Elemento
                    </h3>

                    <template x-if="selectedElement">
                        <div class="space-y-5">
                            <div>
                                <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider mb-1">Tipo de Elemento</p>
                                <span class="text-xs font-bold text-white uppercase bg-white/5 border border-white/5 px-2 py-1 rounded" x-text="selectedElement.type"></span>
                            </div>

                            <!-- Posicionamento Fino -->
                            <div class="grid grid-cols-2 gap-3">
                                 <div class="bg-darkbg border border-darkborder rounded-xl p-3">
                                     <p class="text-[9px] text-gray-600 font-bold uppercase mb-1">Posição X</p>
                                     <input type="number" x-model.number="selectedElement.x" @input="limitCoordinates" class="w-full bg-transparent border-none p-0 text-sm font-mono text-gray-300 focus:ring-0">
                                 </div>
                                 <div class="bg-darkbg border border-darkborder rounded-xl p-3">
                                     <p class="text-[9px] text-gray-600 font-bold uppercase mb-1">Posição Y</p>
                                     <input type="number" x-model.number="selectedElement.y" @input="limitCoordinates" class="w-full bg-transparent border-none p-0 text-sm font-mono text-gray-300 focus:ring-0">
                                 </div>
                            </div>

                            <!-- Dimensões para Elementos de Imagem -->
                            <template x-if="selectedElement.type === 'logo' || selectedElement.type === 'assinatura'">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="bg-darkbg border border-darkborder rounded-xl p-3">
                                        <p class="text-[9px] text-gray-600 font-bold uppercase mb-1">Largura (px)</p>
                                        <input type="number" x-model.number="selectedElement.width" class="w-full bg-transparent border-none p-0 text-sm font-mono text-gray-300 focus:ring-0">
                                    </div>
                                    <div class="bg-darkbg border border-darkborder rounded-xl p-3">
                                        <p class="text-[9px] text-gray-600 font-bold uppercase mb-1">Altura (px)</p>
                                        <input type="number" x-model.number="selectedElement.height" class="w-full bg-transparent border-none p-0 text-sm font-mono text-gray-300 focus:ring-0">
                                    </div>
                                </div>
                            </template>

                            <!-- Estilo de Texto (Só para Textos e Dinâmicos) -->
                            <template x-if="selectedElement.type === 'text' || selectedElement.type === 'dynamic'">
                                <div class="space-y-4">
                                    <!-- Cor do Texto -->
                                    <div class="space-y-1.5">
                                        <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider">Cor do Texto</p>
                                        <div class="flex items-center gap-2 bg-darkbg border border-darkborder rounded-xl px-3 py-1.5">
                                            <input type="color" x-model="selectedElement.color" class="bg-transparent border-none size-6 p-0 cursor-pointer">
                                            <input type="text" x-model="selectedElement.color" class="flex-1 bg-transparent border-none p-0 text-xs text-white focus:ring-0 uppercase font-mono">
                                        </div>
                                    </div>

                                    <!-- Tamanho do Texto -->
                                    <div class="space-y-1.5">
                                        <div class="flex justify-between items-center text-[9px] text-gray-500 font-bold uppercase">
                                            <span>Tamanho da Fonte</span>
                                            <span class="text-brand font-mono font-bold" x-text="selectedElement.size + 'px'"></span>
                                        </div>
                                        <input type="range" min="8" max="28" x-model.number="selectedElement.size" class="w-full accent-brand bg-darkbg h-1.5 rounded-lg appearance-none">
                                    </div>

                                    <!-- Estilo Negrito -->
                                    <div class="flex items-center justify-between py-1 border-t border-b border-darkborder/50">
                                        <span class="text-xs text-gray-400 font-medium">Fonte Negrito</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" x-model="selectedElement.bold" class="sr-only peer">
                                            <div class="w-9 h-5 bg-darkbg peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-gray-600 peer-checked:after:bg-black peer-checked:after:border-brand after:rounded-full after:h-4 after:w-4 after:transition-all border border-darkborder peer-checked:bg-brand"></div>
                                        </label>
                                    </div>

                                    <!-- Campo de valor customizado (Apenas texto) -->
                                    <template x-if="selectedElement.type === 'text'">
                                        <div class="space-y-1.5">
                                            <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider">Conteúdo do Texto</p>
                                            <textarea x-model="selectedElement.value" rows="2" class="w-full bg-darkbg border border-darkborder rounded-xl p-3 text-xs text-white placeholder-gray-600 focus:border-brand focus:ring-0 resize-none"></textarea>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <button type="button" @click="deleteElement(selectedElement)" class="w-full py-3 mt-4 border border-red-500/30 hover:border-red-500/60 bg-red-500/5 hover:bg-red-500/10 text-red-400 rounded-xl text-xs font-black uppercase tracking-wider transition-all">
                                Remover Elemento
                            </button>
                        </div>
                    </template>

                    <template x-if="!selectedElement">
                        <div class="flex flex-col items-center justify-center h-48 border border-dashed border-darkborder rounded-2xl bg-white/[0.01]">
                            <span class="material-symbols-outlined text-3xl text-gray-600 mb-2">touch_app</span>
                            <p class="text-gray-500 text-[10px] uppercase font-bold text-center">Selecione um elemento<br>no canvas para editar</p>
                        </div>
                    </template>
                </div>

                <div class="pt-6 border-t border-darkborder">
                    <button type="button" @click="document.getElementById('form-save-template').click()" class="w-full py-4 bg-brand hover:bg-brand-dark text-black rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-brand/20">
                        Salvar Template
                    </button>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>

<!-- Modal para Visualização de Asset Existente -->
<div id="resource-preview-modal" class="fixed inset-0 bg-black/85 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-6">
    <div class="relative bg-darkcard border border-darkborder rounded-2xl p-6 max-w-2xl max-h-[80vh] overflow-hidden flex flex-col justify-between">
        <button onclick="document.getElementById('resource-preview-modal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-white text-xl font-bold">&times;</button>
        <h3 class="text-white text-sm font-black uppercase tracking-wider mb-4">Visualização do Recurso</h3>
        <div class="flex-1 overflow-auto flex items-center justify-center bg-black/50 p-4 rounded-xl border border-white/5">
            <img id="resource-preview-img" src="" class="max-w-full max-h-[50vh] object-contain">
        </div>
    </div>
</div>

<style>
.canvas-grid {
    background-image: radial-gradient(circle, #2e2e2e 1.2px, transparent 1.2px);
    background-size: 15px 15px;
}
</style>

<script>
    // Gerenciador de Estado dos Cargos
    function chkGeralChanged(e) {
        const isChecked = e.target.checked;
        const chks = document.querySelectorAll('.cargo-chk');
        chks.forEach(chk => {
            chk.checked = isChecked;
            chk.disabled = isChecked; // Desativa se estiver em modo Geral
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Inicializa estado das checkboxes de cargos baseadas no valor de 'Geral'
        const isGeralSaved = <?= ($template_atual && empty($template_atual['cargos'])) ? 'true' : 'false' ?>;
        if (isGeralSaved) {
            const chkGeral = document.getElementById('cargo-geral-chk');
            if (chkGeral) {
                chkGeral.checked = true;
                chkGeral.dispatchEvent(new Event('change'));
            }
        }
    });

    function toggleSelectAllCargos() {
        const chkGeral = document.getElementById('cargo-geral-chk');
        if (chkGeral && chkGeral.checked) return; // Se for Geral, não faz sentido inverter
        
        const chks = document.querySelectorAll('.cargo-chk');
        chks.forEach(chk => {
            chk.checked = !chk.checked;
        });
    }

    function previewResource(url) {
        const modal = document.getElementById('resource-preview-modal');
        const img = document.getElementById('resource-preview-img');
        img.src = url;
        modal.classList.remove('hidden');
    }

    // Alpine.js Canva Logic
    function canvaEditor(initialElements, initialVersoElements) {
        return {
            viewMode: 'frente',
            elements: initialElements || [],
            versoElements: initialVersoElements || [],
            get activeElements() {
                return this.viewMode === 'frente' ? this.elements : this.versoElements;
            },
            selectedId: null,
            selectedElement: null,
            customText: '',
            fundoSrc: '<?= ($template_atual && $template_atual['fundo_url']) ? htmlspecialchars($template_atual['fundo_url']) : '' ?>',
            fundoVersoSrc: '<?= ($template_atual && $template_atual['fundo_verso_url']) ? htmlspecialchars($template_atual['fundo_verso_url']) : '' ?>',
            logoSrc: '<?= ($template_atual && $template_atual['logo_url']) ? htmlspecialchars($template_atual['logo_url']) : '' ?>',
            assinaturaSrc: '<?= ($template_atual && $template_atual['assinatura_url']) ? htmlspecialchars($template_atual['assinatura_url']) : '' ?>',
            dragInfo: { isDragging: false, el: null, startX: 0, startY: 0, initialX: 0, initialY: 0 },
            resizeInfo: { isResizing: false, el: null, startX: 0, startY: 0, initialW: 0, initialH: 0 },

            init() {
                // Remove qualquer outline/focus ao apertar Esc
                window.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') this.deselectAll();
                });
            },

            selectElement(el) {
                this.selectedId = el.id;
                this.selectedElement = el;
            },

            deselectAll() {
                this.selectedId = null;
                this.selectedElement = null;
            },

            deleteElement(el) {
                if (this.viewMode === 'frente') {
                    this.elements = this.elements.filter(item => item.id !== el.id);
                } else {
                    this.versoElements = this.versoElements.filter(item => item.id !== el.id);
                }
                if (this.selectedId === el.id) {
                    this.deselectAll();
                }
            },

            limitCoordinates() {
                if (!this.selectedElement) return;
                const canvasW = 450;
                const canvasH = 280;
                this.selectedElement.x = Math.max(0, Math.min(this.selectedElement.x, canvasW - 40));
                this.selectedElement.y = Math.max(0, Math.min(this.selectedElement.y, canvasH - 20));
            },

            // Upload Previews dinâmicos (Object URLs locais temporários antes de salvar)
            fundoUploaded(e) {
                const file = e.target.files[0];
                if (file) {
                    this.fundoSrc = URL.createObjectURL(file);
                }
            },

            fundoVersoUploaded(e) {
                const file = e.target.files[0];
                if (file) {
                    this.fundoVersoSrc = URL.createObjectURL(file);
                }
            },

            logoUploaded(e) {
                const file = e.target.files[0];
                if (file) {
                    this.logoSrc = URL.createObjectURL(file);
                    // Injeta automaticamente no Canva se não existir
                    if (!this.activeElements.some(el => el.type === 'logo')) {
                        this.injectLogo();
                    }
                }
            },

            assinaturaUploaded(e) {
                const file = e.target.files[0];
                if (file) {
                    this.assinaturaSrc = URL.createObjectURL(file);
                    // Injeta automaticamente se não existir
                    if (!this.activeElements.some(el => el.type === 'assinatura')) {
                        this.injectAssinatura();
                    }
                }
            },

            // Injeção de Elementos
            injectLogo() {
                if (this.activeElements.some(el => el.type === 'logo')) {
                    alert('A Logo já está inserida neste lado do canvas.');
                    return;
                }
                const newEl = {
                    id: 'logo_' + Date.now(),
                    type: 'logo',
                    x: 20,
                    y: 20,
                    width: 120,
                    height: 50
                };
                this.activeElements.push(newEl);
                this.selectElement(newEl);
            },

            injectAssinatura() {
                if (this.activeElements.some(el => el.type === 'assinatura')) {
                    alert('A Assinatura já está inserida neste lado do canvas.');
                    return;
                }
                const newEl = {
                    id: 'assinatura_' + Date.now(),
                    type: 'assinatura',
                    x: 250,
                    y: 210,
                    width: 100,
                    height: 40
                };
                this.activeElements.push(newEl);
                this.selectElement(newEl);
            },

            addCustomText() {
                if (!this.customText.trim()) return;
                const newEl = {
                    id: 'text_' + Date.now(),
                    type: 'text',
                    value: this.customText,
                    x: 50,
                    y: 80,
                    color: '#ffffff',
                    size: 13,
                    bold: true
                };
                this.activeElements.push(newEl);
                this.selectElement(newEl);
                this.customText = '';
            },

            injectDynamic(field, placeholderName) {
                // Impede duplicados do mesmo campo dinâmico
                if (this.activeElements.some(el => el.type === 'dynamic' && el.field === field)) {
                    alert('Este campo dinâmico já está inserido neste lado do canvas.');
                    return;
                }
                if (this.activeElements.some(el => el.type === field)) {
                    alert('Este campo dinâmico já está inserido neste lado do canvas.');
                    return;
                }

                let newEl;
                if (field === 'qr_code') {
                    newEl = { id: 'qr_' + Date.now(), type: 'qr_code', x: 380, y: 210 };
                } else if (field === 'foto_membro') {
                    newEl = { id: 'foto_' + Date.now(), type: 'foto_membro', x: 20, y: 80 };
                } else {
                    newEl = {
                        id: 'dyn_' + Date.now(),
                        type: 'dynamic',
                        field: field,
                        value: placeholderName,
                        x: 120,
                        y: 80,
                        color: '#ffffff',
                        size: 12,
                        bold: true
                    };
                }
                
                this.activeElements.push(newEl);
                this.selectElement(newEl);
            },

            // Lógica de Movimentação Fina (Mouse Drag-and-Drop)
            startDrag(e, el) {
                this.selectElement(el);
                
                this.dragInfo.isDragging = true;
                this.dragInfo.el = el;
                this.dragInfo.startX = e.clientX;
                this.dragInfo.startY = e.clientY;
                this.dragInfo.initialX = el.x;
                this.dragInfo.initialY = el.y;

                const moveHandler = (moveEvent) => this.onDrag(moveEvent);
                const upHandler = () => {
                    this.dragInfo.isDragging = false;
                    document.removeEventListener('mousemove', moveHandler);
                    document.removeEventListener('mouseup', upHandler);
                };

                document.addEventListener('mousemove', moveHandler);
                document.addEventListener('mouseup', upHandler);
            },

            onDrag(e) {
                if (!this.dragInfo.isDragging) return;
                
                const deltaX = e.clientX - this.dragInfo.startX;
                const deltaY = e.clientY - this.dragInfo.startY;
                
                const canvasW = 450;
                const canvasH = 280;
                
                // Calcula as novas coordenadas e as limita ao tamanho do canvas
                let newX = this.dragInfo.initialX + deltaX;
                let newY = this.dragInfo.initialY + deltaY;

                this.dragInfo.el.x = Math.max(0, Math.min(newX, canvasW - 20));
                this.dragInfo.el.y = Math.max(0, Math.min(newY, canvasH - 15));
            },

            startResize(e, el) {
                this.selectElement(el);
                
                if (el.width === undefined) el.width = el.type === 'logo' ? 120 : 100;
                if (el.height === undefined) el.height = el.type === 'logo' ? 50 : 40;

                this.resizeInfo.isResizing = true;
                this.resizeInfo.el = el;
                this.resizeInfo.startX = e.clientX;
                this.resizeInfo.startY = e.clientY;
                this.resizeInfo.initialW = el.width;
                this.resizeInfo.initialH = el.height;

                const moveHandler = (moveEvent) => this.onResize(moveEvent);
                const upHandler = () => {
                    this.resizeInfo.isResizing = false;
                    document.removeEventListener('mousemove', moveHandler);
                    document.removeEventListener('mouseup', upHandler);
                };

                document.addEventListener('mousemove', moveHandler);
                document.addEventListener('mouseup', upHandler);
            },

            onResize(e) {
                if (!this.resizeInfo.isResizing) return;
                
                const deltaX = e.clientX - this.resizeInfo.startX;
                const deltaY = e.clientY - this.resizeInfo.startY;
                
                let newW = this.resizeInfo.initialW + deltaX;
                let newH = this.resizeInfo.initialH + deltaY;

                this.resizeInfo.el.width = Math.max(20, Math.min(newW, 400));
                this.resizeInfo.el.height = Math.max(15, Math.min(newH, 250));
            }
        };
    }
</script>

<?php require_once 'includes/footer.php'; ?>
