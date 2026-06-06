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

require_once 'config/database.php';
require_once 'src/autoload.php';

use App\Controllers\CarteirinhaController;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Busca o membro
$sql = "SELECT m.*, c.nome as cargo_nome, con.nome as congregacao_nome 
        FROM membros m 
        LEFT JOIN cargos c ON m.cargo_id = c.id 
        LEFT JOIN congregacoes con ON m.congregacao_id = con.id 
        WHERE m.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$m) {
    die("Membro não encontrado.");
}

// Inicializa o controller e busca o template correspondente ao cargo do membro
$controller = new CarteirinhaController($pdo);
$template = $controller->getTemplateForMember($m['id']);

$page_title = 'SGIM - Gerar Carteirinha';
$current_page = 'carteirinhas';

require_once 'includes/header.php';
?>

<?php if ($template): ?>
    <!-- LAYOUT DINÂMICO DO CANVA -->
    <?php
    $elementos = json_decode($template['elementos_json'], true) ?: [];
    
    // Função auxiliar para substituir as tags dinâmicas nos textos salvos
    function substituirTags($val, $m) {
        $data_emissao = date('d/m/Y');
        $valida_ate = date('d/m/Y', strtotime($m['data_cadastro'] . ' + 2 years'));
        
        $substituicoes = [
            '{Nome do Membro}' => $m['nome'],
            '{nome_membro}' => $m['nome'],
            '{Cargo / Função}' => $m['cargo_nome'] ?? 'Membro',
            '{nome_cargo}' => $m['cargo_nome'] ?? 'Membro',
            '{Congregação}' => $m['congregacao_nome'] ?? 'Sede Central',
            '{nome_congregacao}' => $m['congregacao_nome'] ?? 'Sede Central',
            '{CPF}' => $m['cpf'] ?? '---',
            '{cpf_membro}' => $m['cpf'] ?? '---',
            '{Data Emissão}' => $data_emissao,
            '{data_emissao}' => $data_emissao,
            '{Válida Até}' => $valida_ate,
            '{valida_ate}' => $valida_ate
        ];
        
        return str_replace(array_keys($substituicoes), array_values($substituicoes), $val);
    }
    
    $qr_code_data = "SGIM-VERIFY-" . $m['id'];
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_code_data);
    ?>

    <div class="flex flex-col gap-8 items-center">
        <div class="w-full max-w-4xl flex justify-between items-center bg-darkcard p-6 rounded-twelve border border-darkborder no-print shadow-lg">
            <div>
                <h2 class="text-2xl font-black text-white tracking-tighter">Carteirinha Gerada</h2>
                <p class="text-xs text-gray-500 uppercase font-bold tracking-widest mt-1">Modelo Aplicado: <?= htmlspecialchars($template['nome']) ?></p>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="flex items-center gap-2 px-6 py-2.5 bg-brand hover:bg-brand-dark text-black rounded-twelve text-sm font-bold shadow-lg shadow-brand/20 transition-all">
                    <span class="material-symbols-outlined text-[18px]">print</span>
                    Imprimir / Exportar PDF
                </button>
            </div>
        </div>

        <!-- Renderizador do Cartão Canva -->
        <div id="carteirinha-print-area" class="relative w-[450px] h-[280px] bg-[#0A0A0A] rounded-2xl border border-brand/20 shadow-2xl overflow-hidden p-0"
             style="<?= $template['fundo_url'] ? 'background-image: url(' . $template['fundo_url'] . '); background-size: cover; background-position: center;' : '' ?>">
            
            <?php foreach ($elementos as $el): ?>
                <?php
                $style = sprintf(
                    "left: %dpx; top: %dpx; color: %s; font-size: %dpx; font-weight: %s; font-family: %s; z-index: 20;",
                    $el['x'],
                    $el['y'],
                    $el['color'] ?? '#ffffff',
                    $el['size'] ?? 12,
                    ($el['bold'] ?? false) ? 'bold' : 'normal',
                    ($el['type'] === 'text' || $el['type'] === 'dynamic') ? 'Inter, sans-serif' : 'monospace'
                );
                ?>
                <div class="absolute p-0.5 select-none whitespace-nowrap" style="<?= $style ?>">
                    <?php if ($el['type'] === 'text'): ?>
                        <span><?= htmlspecialchars(substituirTags($el['value'], $m)) ?></span>
                    
                    <?php elseif ($el['type'] === 'dynamic'): ?>
                        <span><?= htmlspecialchars(substituirTags($el['value'], $m)) ?></span>
                        
                    <?php elseif ($el['type'] === 'foto_membro'): ?>
                        <div class="size-20 bg-darkbg border border-brand/40 overflow-hidden flex items-center justify-center rounded-lg shadow-md">
                            <?php if ($m['foto'] && file_exists('uploads/membros/' . $m['foto'])): ?>
                                <img src="uploads/membros/<?= htmlspecialchars($m['foto']) ?>" class="w-full h-full object-cover">
                            <?php elseif ($m['foto'] && file_exists($m['foto'])): ?>
                                <img src="<?= htmlspecialchars($m['foto']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="material-symbols-outlined text-gray-700 text-5xl">person</span>
                            <?php endif; ?>
                        </div>
                        
                    <?php elseif ($el['type'] === 'qr_code'): ?>
                        <div class="size-10 bg-white p-0.5 rounded shadow flex items-center justify-center">
                            <img src="<?= $qr_url ?>" class="w-full h-full object-contain">
                        </div>
                        
                    <?php elseif ($el['type'] === 'logo'): ?>
                        <?php if ($template['logo_url'] && file_exists($template['logo_url'])): ?>
                            <img src="<?= htmlspecialchars($template['logo_url']) ?>" class="max-h-12 object-contain">
                        <?php else: ?>
                            <div class="size-10 bg-brand rounded-lg flex items-center justify-center text-black shadow">
                                <span class="material-symbols-outlined text-2xl font-bold">church</span>
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif ($el['type'] === 'assinatura'): ?>
                        <div class="flex flex-col items-center">
                            <?php if ($template['assinatura_url'] && file_exists($template['assinatura_url'])): ?>
                                <img src="<?= htmlspecialchars($template['assinatura_url']) ?>" class="max-h-10 object-contain">
                            <?php else: ?>
                                <span class="text-xs italic text-gray-500 font-serif">Presidência</span>
                            <?php endif; ?>
                            <div class="w-20 h-px bg-white/20 my-0.5"></div>
                            <span class="text-[5px] uppercase font-bold text-gray-500 tracking-wider">Assinatura</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <p class="text-sm text-gray-500 max-w-lg text-center mt-4 no-print">
            <span class="text-brand font-bold">Dica:</span> Pressione Ctrl+P (ou Cmd+P) no seu teclado para salvar como PDF ou imprimir.
        </p>
    </div>

<?php else: ?>
    <!-- LAYOUT DE FALLBACK (Design Padrão Amber Neon Antigo Melhorado) -->
    <div class="flex flex-col gap-8 items-center">
        <div class="w-full max-w-4xl flex justify-between items-center bg-darkcard p-6 rounded-twelve border border-darkborder no-print shadow-lg">
            <div>
                <h2 class="text-2xl font-black text-white tracking-tighter">Gerar Carteirinha</h2>
                <p class="text-xs text-gray-500 uppercase font-bold tracking-widest mt-1">Design de Fallback Ativo (Crie um modelo Canva no Editor)</p>
            </div>
            <div class="flex gap-3">
                <button onclick="window.print()" class="flex items-center gap-2 px-6 py-2.5 bg-brand hover:bg-brand-dark text-black rounded-twelve text-sm font-bold shadow-lg shadow-brand/20 transition-all">
                    <span class="material-symbols-outlined text-[18px]">print</span>
                    Imprimir / Exportar PDF
                </button>
            </div>
        </div>

        <div id="carteirinha-print-area" class="relative w-[450px] h-[280px] bg-[#0A0A0A] rounded-2xl border-2 border-brand/30 shadow-2xl overflow-hidden p-6 flex flex-col justify-between group">
            <div class="absolute -top-20 -right-20 w-64 h-64 bg-brand/5 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="flex items-center justify-between relative z-10">
                <div class="flex items-center gap-3">
                    <div class="size-10 bg-brand rounded-lg flex items-center justify-center text-black">
                        <span class="material-symbols-outlined text-2xl font-bold">church</span>
                    </div>
                    <div>
                        <h1 class="text-sm font-black text-white leading-tight uppercase tracking-tighter">SGIM CHURCH</h1>
                        <p class="text-[8px] text-gray-500 font-bold uppercase tracking-widest">Sistema de Gestão Integrada</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-[8px] text-brand font-black uppercase tracking-widest">Membro Oficial</p>
                    <p class="text-[10px] text-gray-400 font-mono">ID: 2024-<?= str_pad($m['id'], 5, '0', STR_PAD_LEFT) ?></p>
                </div>
            </div>

            <div class="flex gap-6 items-center mt-4 relative z-10">
                <div class="relative">
                    <div class="size-28 rounded-xl bg-darkbg border-2 border-brand overflow-hidden flex items-center justify-center shadow-lg">
                        <?php if ($m['foto']): ?>
                            <img src="uploads/membros/<?= htmlspecialchars($m['foto']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="material-symbols-outlined text-gray-700 text-5xl">person</span>
                        <?php endif; ?>
                    </div>
                    <div class="absolute -bottom-2 left-1/2 -translate-x-1/2 bg-brand text-black text-[8px] font-black px-2 py-0.5 rounded-full whitespace-nowrap shadow-md">
                        VALIDA ATÉ <?= date('m/y', strtotime('+2 years')) ?>
                    </div>
                </div>

                <div class="flex-1 space-y-3">
                    <div>
                        <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest mb-0.5">Nome do Membro</p>
                        <p class="text-sm font-black text-white uppercase tracking-tight"><?= htmlspecialchars($m['nome']) ?></p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest mb-0.5">Cargo / Função</p>
                            <p class="text-[10px] font-bold text-gray-200 uppercase"><?= htmlspecialchars($m['cargo_nome'] ?? 'Membro') ?></p>
                        </div>
                        <div>
                            <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest mb-0.5">Congregação</p>
                            <p class="text-[10px] font-bold text-gray-200 uppercase"><?= htmlspecialchars($m['congregacao_nome'] ?? 'Sede Central') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-white/5 pt-4 mt-4 relative z-10">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand text-[14px]">verified_user</span>
                    <span class="text-[8px] text-gray-500 font-bold uppercase tracking-widest">Autenticidade Digital</span>
                </div>
                <div class="size-10 bg-white p-1 rounded-sm shadow-inner">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=SGIM-VERIFY-<?= $m['id'] ?>" class="w-full h-full">
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
@media print {
    /* Oculta tudo que pertence ao painel */
    body, header, aside, button, nav, .no-print, p {
        display: none !important;
        visibility: hidden !important;
    }
    
    /* Configurações da página para tamanho exato */
    @page {
        size: auto;
        margin: 0;
    }
    
    /* Força exibição do container da carteirinha */
    #carteirinha-print-area, #carteirinha-print-area * {
        visibility: visible !important;
        display: block !important;
    }
    
    #carteirinha-print-area {
        position: absolute !important;
        left: 50% !important;
        top: 50% !important;
        transform: translate(-50%, -50%) !important;
        margin: 0 !important;
        box-shadow: none !important;
        border: none !important;
        page-break-inside: avoid;
        background-color: #0A0A0A !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Garante o posicionamento correto absoluto de cada elemento na impressão */
    #carteirinha-print-area .absolute {
        position: absolute !important;
        display: block !important;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
