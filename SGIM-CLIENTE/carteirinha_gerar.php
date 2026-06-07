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
    $elementos_verso = json_decode($template['elementos_verso_json'] ?? '[]', true) ?: [];
    
    // 🛡️ Garante hash e validade
    if (empty($m['hash_carteirinha'])) {
        $novo_hash = hash('sha256', $m['id'] . time() . uniqid());
        $validade = date('Y-m-d', strtotime('+1 year'));
        try {
            $stmtUpdate = $pdo->prepare("UPDATE membros SET hash_carteirinha = ?, carteirinha_valida_ate = ? WHERE id = ?");
            $stmtUpdate->execute([$novo_hash, $validade, $m['id']]);
            $m['hash_carteirinha'] = $novo_hash;
            $m['carteirinha_valida_ate'] = $validade;
        } catch (Exception $e) {}
    }

    $valida_ate_br = $m['carteirinha_valida_ate'] ? date('d/m/Y', strtotime($m['carteirinha_valida_ate'])) : date('d/m/Y', strtotime('+1 year'));

    // Função auxiliar para substituir as tags dinâmicas nos textos salvos
    function substituirTags($val, $m, $valida_ate_br) {
        $data_emissao = date('d/m/Y', strtotime($m['data_cadastro']));
        
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
            '{Válida Até}' => $valida_ate_br,
            '{valida_ate}' => $valida_ate_br,
            '{RG}' => $m['rg'] ?? '---',
            '{rg_membro}' => $m['rg'] ?? '---',
            '{Telefone}' => $m['telefone'] ?? '---',
            '{telefone_membro}' => $m['telefone'] ?? '---',
            '{E-mail}' => $m['email'] ?? '---',
            '{email_membro}' => $m['email'] ?? '---',
            '{Endereço}' => trim(($m['endereco'] ?? '') . ' ' . ($m['numero'] ?? '') . ' ' . ($m['bairro'] ?? '')),
            '{endereco_membro}' => trim(($m['endereco'] ?? '') . ' ' . ($m['numero'] ?? '') . ' ' . ($m['bairro'] ?? '')),
            '{Nascimento}' => $m['data_nascimento'] ? date('d/m/Y', strtotime($m['data_nascimento'])) : '---',
            '{nascimento_membro}' => $m['data_nascimento'] ? date('d/m/Y', strtotime($m['data_nascimento'])) : '---',
            '{Data Batismo}' => $m['data_batismo'] ? date('d/m/Y', strtotime($m['data_batismo'])) : '---',
            '{batismo_membro}' => $m['data_batismo'] ? date('d/m/Y', strtotime($m['data_batismo'])) : '---',
        ];
        
        return str_replace(array_keys($substituicoes), array_values($substituicoes), $val);
    }
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'];
    $valida_url = $protocol . $domain . dirname($_SERVER['PHP_SELF']) . "/carteirinha_validar.php?hash=" . $m['hash_carteirinha'];
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($valida_url);
    ?>

    <!-- Biblioteca html2pdf.js via CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <div class="flex flex-col gap-8 items-center">
        <div class="w-full max-w-4xl flex justify-between items-center bg-darkcard p-6 rounded-twelve border border-darkborder no-print shadow-lg">
            <div>
                <h2 class="text-2xl font-black text-white tracking-tighter">Carteirinha Gerada</h2>
                <p class="text-xs text-gray-500 uppercase font-bold tracking-widest mt-1">Modelo Aplicado: <?= htmlspecialchars($template['nome']) ?></p>
            </div>
            <div class="flex gap-3">
                <button onclick="downloadPDF()" class="flex items-center gap-2 px-6 py-2.5 bg-yellow-500 hover:bg-yellow-600 text-black rounded-twelve text-sm font-bold shadow-lg shadow-yellow-500/20 transition-all">
                    <span class="material-symbols-outlined text-[18px]">download_for_offline</span>
                    Baixar em PDF (Gráficas)
                </button>
                <button onclick="window.print()" class="flex items-center gap-2 px-6 py-2.5 bg-brand hover:bg-brand-dark text-black rounded-twelve text-sm font-bold shadow-lg shadow-brand/20 transition-all">
                    <span class="material-symbols-outlined text-[18px]">print</span>
                    Imprimir / Exportar
                </button>
            </div>
        </div>

        <!-- Wrapper unificado para Download do PDF -->
        <div id="carteirinha-pdf-wrapper" class="flex flex-col gap-8 items-center bg-transparent p-0">
            
            <!-- FRENTE -->
            <div id="carteirinha-frente" class="carteirinha-card relative w-[450px] h-[280px] bg-[#0A0A0A] rounded-2xl border border-brand/20 shadow-2xl overflow-hidden p-0"
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
                            <span><?= htmlspecialchars(substituirTags($el['value'], $m, $valida_ate_br)) ?></span>
                        
                        <?php elseif ($el['type'] === 'dynamic'): ?>
                            <span><?= htmlspecialchars(substituirTags($el['value'], $m, $valida_ate_br)) ?></span>
                            
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
                                <?php
                                $logoStyle = (isset($el['width']) && isset($el['height'])) ? sprintf("width: %dpx; height: %dpx; object-fit: contain;", $el['width'], $el['height']) : "max-height: 48px; object-fit: contain;";
                                ?>
                                <img src="<?= htmlspecialchars($template['logo_url']) ?>" style="<?= $logoStyle ?>">
                            <?php else: ?>
                                <div class="size-10 bg-brand rounded-lg flex items-center justify-center text-black shadow">
                                    <span class="material-symbols-outlined text-2xl font-bold">church</span>
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif ($el['type'] === 'assinatura'): ?>
                            <div class="flex flex-col items-center">
                                <?php if ($template['assinatura_url'] && file_exists($template['assinatura_url'])): ?>
                                    <?php
                                    $sigStyle = (isset($el['width']) && isset($el['height'])) ? sprintf("width: %dpx; height: %dpx; object-fit: contain;", $el['width'], $el['height']) : "max-height: 40px; object-fit: contain;";
                                    ?>
                                    <img src="<?= htmlspecialchars($template['assinatura_url']) ?>" style="<?= $sigStyle ?>">
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

            <!-- Elemento de Quebra de Página para o html2pdf.js -->
            <div class="html2pdf__page-break"></div>

            <!-- VERSO -->
            <div id="carteirinha-verso" class="carteirinha-card relative w-[450px] h-[280px] bg-[#0A0A0A] rounded-2xl border border-brand/20 shadow-2xl overflow-hidden p-0"
                 style="<?= (!empty($template['fundo_verso_url'])) ? 'background-image: url(' . $template['fundo_verso_url'] . '); background-size: cover; background-position: center;' : '' ?>">
                
                <?php foreach ($elementos_verso as $el): ?>
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
                            <span><?= htmlspecialchars(substituirTags($el['value'], $m, $valida_ate_br)) ?></span>
                        
                        <?php elseif ($el['type'] === 'dynamic'): ?>
                            <span><?= htmlspecialchars(substituirTags($el['value'], $m, $valida_ate_br)) ?></span>
                            
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
                                <?php
                                $logoStyle = (isset($el['width']) && isset($el['height'])) ? sprintf("width: %dpx; height: %dpx; object-fit: contain;", $el['width'], $el['height']) : "max-height: 48px; object-fit: contain;";
                                ?>
                                <img src="<?= htmlspecialchars($template['logo_url']) ?>" style="<?= $logoStyle ?>">
                            <?php else: ?>
                                <div class="size-10 bg-brand rounded-lg flex items-center justify-center text-black shadow">
                                    <span class="material-symbols-outlined text-2xl font-bold">church</span>
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif ($el['type'] === 'assinatura'): ?>
                            <div class="flex flex-col items-center">
                                <?php if ($template['assinatura_url'] && file_exists($template['assinatura_url'])): ?>
                                    <?php
                                    $sigStyle = (isset($el['width']) && isset($el['height'])) ? sprintf("width: %dpx; height: %dpx; object-fit: contain;", $el['width'], $el['height']) : "max-height: 40px; object-fit: contain;";
                                    ?>
                                    <img src="<?= htmlspecialchars($template['assinatura_url']) ?>" style="<?= $sigStyle ?>">
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
        </div>

        <script>
            function downloadPDF() {
                const element = document.getElementById('carteirinha-pdf-wrapper');
                const cleanName = <?= json_encode(preg_replace('/[^a-zA-Z0-9_-]/', '', $m['nome'])) ?>;
                const opt = {
                    margin:       0,
                    filename:     'carteirinha_' + cleanName + '.pdf',
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { scale: 3, useCORS: true, letterRendering: true },
                    jsPDF:        { unit: 'in', format: 'credit-card', orientation: 'landscape' }
                };
                html2pdf().set(opt).from(element).save();
            }
        </script>
        
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
/* Quebra de página para o PDF do html2pdf.js */
.html2pdf__page-break {
    page-break-after: always;
    break-after: page;
    height: 0;
    overflow: hidden;
}

@media print {
    /* Oculta tudo que pertence ao painel e partes administrativas */
    body, header, aside, button, nav, .no-print, p, footer {
        display: none !important;
        visibility: hidden !important;
        background: transparent !important;
    }
    
    body {
        background: white !important;
        color: black !important;
    }
    
    /* Configurações da página para tamanho exato */
    @page {
        size: landscape;
        margin: 0;
    }
    
    /* Centraliza e exibe os containers de impressão */
    #carteirinha-print-area, 
    #carteirinha-pdf-wrapper {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        visibility: visible !important;
    }

    #carteirinha-frente, 
    #carteirinha-verso,
    #carteirinha-print-area {
        display: block !important;
        visibility: visible !important;
        position: relative !important;
        page-break-inside: avoid;
        break-inside: avoid;
        margin: 0 auto !important;
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        width: 450px !important;
        height: 280px !important;
    }

    #carteirinha-frente {
        page-break-after: always;
        break-after: page;
    }

    /* Garante o posicionamento correto absoluto de cada elemento na impressão */
    #carteirinha-pdf-wrapper .absolute,
    #carteirinha-print-area .absolute {
        position: absolute !important;
        display: block !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
