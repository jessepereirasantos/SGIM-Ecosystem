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

// Proteção contra PDO nulo
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
    exit;
}

// 🛡️ Inicializa o AccessManager para proteção de rota antecipada
if (!class_exists('SGIM\\Auth\\AccessManager')) {
    $amPath = __DIR__ . '/src/Auth/AccessManager.php';
    if (file_exists($amPath)) require_once $amPath;
}
$access = new \SGIM\Auth\AccessManager($pdo, $_SESSION['user_id']);

// Validação antecipada de leitura
if ($access && !$access->can('carteirinhas', 'visualizar')) {
    echo "<script>alert('Acesso Negado: Você não tem permissão para gerenciar carteirinhas.'); window.location.href='dashboard.php';</script>";
    exit;
}

// LÓGICA DE RENOVAÇÃO DE CARTEIRINHA
if (isset($_GET['action']) && $_GET['action'] === 'renew' && isset($_GET['id'])) {
    // Validação de gravação
    if ($access && !$access->can('carteirinhas', 'gerenciar')) {
        echo "<script>alert('Acesso Negado: Você não tem permissão para renovar carteirinhas.'); window.location.href='carteirinha_digital.php';</script>";
        exit;
    }

    $membro_id = intval($_GET['id']);
    
    // Se for escopo local, valida se o membro pertence à congregação do usuário logado
    if ($access && !$access->isGlobal()) {
        $stmtCheck = $pdo->prepare("SELECT congregacao_id FROM membros WHERE id = ?");
        $stmtCheck->execute([$membro_id]);
        if ($stmtCheck->fetchColumn() != $access->getCongregacaoId()) {
            echo "<script>alert('Acesso Negado: Este membro pertence a outra congregação.'); window.location.href='carteirinha_digital.php';</script>";
            exit;
        }
    }

    $novo_hash = hash('sha256', $membro_id . time() . uniqid());
    $nova_validade = date('Y-m-d', strtotime('+1 year'));
    
    try {
        $stmt = $pdo->prepare("UPDATE membros SET hash_carteirinha = ?, carteirinha_valida_ate = ? WHERE id = ?");
        $stmt->execute([$novo_hash, $nova_validade, $membro_id]);
        
        header("Location: carteirinha_digital.php?renewed=1&id=" . $membro_id . "&hash=" . $novo_hash);
        exit;
    } catch (Exception $e) {
        header("Location: carteirinha_digital.php?renew_error=1");
        exit;
    }
}

$mensagem = '';
$erro = false;
$membro_renovado = null;

if (isset($_GET['renewed']) && isset($_GET['id'])) {
    $stmtM = $pdo->prepare("SELECT nome FROM membros WHERE id = ?");
    $stmtM->execute([intval($_GET['id'])]);
    $membro_renovado = $stmtM->fetch(PDO::FETCH_ASSOC);
}

$page_title = 'SGIM - Gestão de Carteirinhas';
$current_page = 'carteirinhas';
require_once 'includes/header.php';

// Busca os membros ativos do banco respeitando o escopo de congregação
$scopeFilter = $access ? $access->getScopeFilter() : '';
$membros = $pdo->query("SELECT id, nome, cpf, hash_carteirinha, carteirinha_valida_ate FROM membros WHERE status = 'Ativo' $scopeFilter ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-darkcard p-6 rounded-xl border border-darkborder shadow-lg">
        <div>
            <h2 class="text-2xl font-black text-white tracking-tighter flex items-center gap-2">
                <span class="material-symbols-outlined text-brand text-3xl">badge</span>
                Gestão de Carteirinhas Digitais
            </h2>
            <p class="text-xs text-gray-500 uppercase font-bold tracking-widest mt-1">Controle de Validade, Emissão e QR Codes do Ministério</p>
        </div>
        <div>
            <?php if ($access && $access->can('carteirinhas', 'gerenciar')): ?>
            <a href="carteirinha_editor.php" class="flex items-center gap-2 px-6 py-3 bg-brand hover:bg-brand-dark text-black rounded-xl text-xs font-black uppercase tracking-widest shadow-lg shadow-brand/20 transition-all">
                <span class="material-symbols-outlined text-base">palette</span>
                Gerenciar Modelos (Canva)
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alertas de Renovação com Link de Compartilhamento -->
    <?php if ($membro_renovado && isset($_GET['hash'])): ?>
        <?php
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domain = $_SERVER['HTTP_HOST'];
        $share_url = $protocol . $domain . dirname($_SERVER['PHP_SELF']) . "/carteirinha_validar.php?hash=" . htmlspecialchars($_GET['hash']);
        $whatsapp_text = urlencode("Olá, sua Carteirinha Digital Ministerial do SGIM foi renovada e atualizada com sucesso! Você pode visualizar seu documento digital através deste link: " . $share_url);
        ?>
        <div class="p-6 rounded-2xl bg-brand/10 border border-brand/20 space-y-4 shadow-xl">
            <div class="flex items-center gap-3 text-brand">
                <span class="material-symbols-outlined">verified</span>
                <h3 class="font-bold text-sm">Carteirinha de <?= htmlspecialchars($membro_renovado['nome']) ?> Renovada com Sucesso!</h3>
            </div>
            <p class="text-xs text-gray-300 leading-relaxed">
                A validade foi estendida por mais **1 ano** e um novo QR Code de segurança foi gerado. O link público do documento já está disponível para envio:
            </p>
            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                <input id="share-link-input" type="text" readonly value="<?= $share_url ?>" 
                       class="flex-1 bg-black/50 border border-darkborder rounded-xl px-4 py-3 text-xs font-mono text-gray-400 focus:outline-none"/>
                <button onclick="copiarLink()" class="px-5 py-3 bg-white/5 border border-darkborder hover:bg-white/10 rounded-xl text-xs font-bold uppercase tracking-widest text-brand transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-sm">content_copy</span>
                    Copiar
                </button>
                <a href="https://api.whatsapp.com/send?text=<?= $whatsapp_text ?>" target="_blank" 
                   class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl text-xs font-bold uppercase tracking-widest transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-sm font-bold">chat</span>
                    WhatsApp
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['renew_error'])): ?>
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 flex items-center gap-3 shadow-lg">
            <span class="material-symbols-outlined">error</span>
            <p class="text-xs font-semibold">Falha ao processar a renovação no banco de dados. Tente novamente.</p>
        </div>
    <?php endif; ?>

    <div class="bg-darkcard border border-darkborder rounded-xl overflow-hidden shadow-lg">
        <table class="w-full text-left">
            <thead class="bg-white/5 text-xs uppercase text-gray-400 border-b border-darkborder">
                <tr>
                    <th class="px-6 py-4 font-black">Membro</th>
                    <th class="px-6 py-4 font-black">CPF</th>
                    <th class="px-6 py-4 font-black text-center">Status / Validade</th>
                    <th class="px-6 py-4 text-right font-black">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-darkborder text-sm">
                <?php if (empty($membros)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500 font-bold">Nenhum membro ativo localizado para o escopo selecionado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($membros as $m): ?>
                    <?php
                    $is_valid = true;
                    if (empty($m['carteirinha_valida_ate'])) {
                        $status_html = '<span class="px-2 py-0.5 rounded bg-yellow-500/10 border border-yellow-500/20 text-yellow-500 text-[10px] font-bold uppercase">Pendente Emissão</span>';
                    } elseif (strtotime($m['carteirinha_valida_ate']) < time()) {
                        $is_valid = false;
                        $status_html = '<span class="px-2 py-0.5 rounded bg-red-500/10 border border-red-500/20 text-red-500 text-[10px] font-bold uppercase">Expirada</span> <span class="text-[10px] text-gray-600 block mt-1 font-mono">' . date('d/m/Y', strtotime($m['carteirinha_valida_ate'])) . '</span>';
                    } else {
                        $status_html = '<span class="px-2 py-0.5 rounded bg-green-500/10 border border-green-500/20 text-green-400 text-[10px] font-bold uppercase">Ativa</span> <span class="text-[10px] text-gray-500 block mt-1 font-mono">Vence: ' . date('d/m/Y', strtotime($m['carteirinha_valida_ate'])) . '</span>';
                    }
                    ?>
                    <tr class="hover:bg-white/[0.02] transition-colors group">
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="font-bold text-white group-hover:text-brand transition-colors"><?= htmlspecialchars($m['nome']) ?></span>
                                <span class="text-[9px] text-gray-600 font-mono mt-0.5 uppercase">ID: #<?= str_pad($m['id'], 6, '0', STR_PAD_LEFT) ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-400 font-mono"><?= htmlspecialchars($m['cpf'] ?? '---') ?></td>
                        <td class="px-6 py-4 text-center"><?= $status_html ?></td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-4">
                                <?php if ($access && $access->can('carteirinhas', 'gerenciar')): ?>
                                <a href="carteirinha_digital.php?action=renew&id=<?= $m['id'] ?>" 
                                   onclick="return confirm('Deseja estender a validade de <?= htmlspecialchars($m['nome']) ?> por mais 1 ano e renovar o QR Code?')" 
                                   class="inline-flex items-center gap-1.5 text-gray-400 hover:text-brand font-bold text-xs uppercase tracking-wider transition-colors">
                                    <span class="material-symbols-outlined text-sm">history</span>
                                    Renovar
                                </a>
                                <?php endif; ?>
                                <a href="carteirinha_gerar.php?id=<?= $m['id'] ?>" target="_blank" 
                                   class="inline-flex items-center gap-1.5 text-brand hover:underline font-bold text-xs uppercase tracking-wider">
                                    <span class="material-symbols-outlined text-sm">badge</span>
                                    Visualizar
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function copiarLink() {
        const input = document.getElementById('share-link-input');
        input.select();
        input.setSelectionRange(0, 99999); // Para dispositivos móveis
        document.execCommand('copy');
        alert('Link da carteirinha copiado para a área de transferência!');
    }
</script>

<?php
require_once 'includes/footer.php';
?>
