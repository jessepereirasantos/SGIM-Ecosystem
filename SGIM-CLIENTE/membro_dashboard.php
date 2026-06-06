<?php
// AUTO-PONTE: Se existir uma versão mais nova ativa pelo OTA, desvia para ela
$bridge = __DIR__ . '/releases/current/' . basename(__FILE__);
if (file_exists($bridge) && strpos(__DIR__, 'releases') === false) {
    require_once $bridge;
    exit;
}

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/autoload.php';

// Proteção contra PDO nulo
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
    exit;
}

// Verifica se o membro está logado
if (!isset($_SESSION['membro_id'])) {
    header('Location: membro_login.php');
    exit;
}

$membro_id = (int)$_SESSION['membro_id'];

// Busca dados completos do membro
$sql = "SELECT m.*, c.nome as cargo_nome, con.nome as congregacao_nome 
        FROM membros m 
        LEFT JOIN cargos c ON m.cargo_id = c.id 
        LEFT JOIN congregacoes con ON m.congregacao_id = con.id 
        WHERE m.id = ? AND m.status = 'Ativo'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$membro_id]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$m) {
    // Caso o membro tenha sido inativado ou excluído
    session_destroy();
    header('Location: membro_login.php');
    exit;
}

// 🛡️ GERA HASH DE CARTEIRINHA E VALIDADE CASO NÃO EXISTAM
if (empty($m['hash_carteirinha'])) {
    $novo_hash = hash('sha256', $m['id'] . time() . uniqid());
    $validade = date('Y-m-d', strtotime('+1 year'));
    
    try {
        $stmtUpdate = $pdo->prepare("UPDATE membros SET hash_carteirinha = ?, carteirinha_valida_ate = ? WHERE id = ?");
        $stmtUpdate->execute([$novo_hash, $validade, $membro_id]);
        $m['hash_carteirinha'] = $novo_hash;
        $m['carteirinha_valida_ate'] = $validade;
    } catch (Exception $e) {
        error_log("Erro ao inicializar hash/validade do membro: " . $e->getMessage());
    }
}

// Inicializa o Controller de Carteirinhas para carregar o modelo de Canva
use App\Controllers\CarteirinhaController;
$carteirinhaCtrl = new CarteirinhaController($pdo);
$template = $carteirinhaCtrl->getTemplateForMember($membro_id);

// Configuração do QR Code
$validade_br = $m['carteirinha_valida_ate'] ? date('d/m/Y', strtotime($m['carteirinha_valida_ate'])) : date('d/m/Y', strtotime('+1 year'));
$is_valida = true;
if ($m['carteirinha_valida_ate'] && strtotime($m['carteirinha_valida_ate']) < time()) {
    $is_valida = false;
}

// Protocolo de URL da página de validação pública
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$valida_url = $protocol . $domain . dirname($_SERVER['PHP_SELF']) . "/carteirinha_validar.php?hash=" . $m['hash_carteirinha'];
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($valida_url);

// Buscar Comunicados Públicos (Avisos de Painel)
$comunicados = [];
try {
    $stmtComs = $pdo->query("SELECT * FROM comunicacoes WHERE tipo = 'aviso_painel' AND status = 'enviado' ORDER BY data_envio DESC, id DESC LIMIT 3");
    if ($stmtComs) $comunicados = $stmtComs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$page_title = "Minha Carteirinha - " . htmlspecialchars($m['nome']);
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= $page_title ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: '#FFC107',
                        brand_dark: '#D9A406',
                        darkbg: '#050505',
                        darkcard: '#121212',
                        darkborder: '#1E1E1E'
                    },
                    borderRadius: { 'twelve': '12px' }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: #e5e7eb; }
        .carteirinha-container {
            width: 450px;
            height: 280px;
        }
        @media (max-width: 480px) {
            .carteirinha-scale {
                transform: scale(0.75);
                transform-origin: center top;
            }
            .carteirinha-wrapper {
                height: 220px;
            }
        }
        @media (max-width: 380px) {
            .carteirinha-scale {
                transform: scale(0.65);
            }
            .carteirinha-wrapper {
                height: 190px;
            }
        }
    </style>
</head>
<body class="bg-darkbg text-gray-100 min-h-screen pb-12">
    <!-- Header -->
    <header class="h-20 bg-darkcard/80 backdrop-blur-md border-b border-darkborder flex items-center justify-between px-6 md:px-12 sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <div class="size-9 bg-brand rounded-lg flex items-center justify-center text-black font-bold">
                <span class="material-symbols-outlined text-lg">church</span>
            </div>
            <div>
                <h1 class="text-sm font-bold text-white tracking-tight leading-none">SGIM</h1>
                <p class="text-[9px] text-brand uppercase tracking-widest font-bold mt-1">Área do Membro</p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-xs text-gray-400 font-bold hidden md:inline"><?= htmlspecialchars($m['nome']) ?></span>
            <a href="portal.php?logout_membro=1" onclick="<?php unset($_SESSION['membro_id']); ?>" class="flex items-center gap-1 text-[10px] font-bold text-red-500 hover:text-red-400 uppercase tracking-widest transition-all">
                <span class="material-symbols-outlined text-sm">logout</span>
                Sair
            </a>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-4 md:px-8 mt-8 grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Coluna 1: Carteirinha Digital -->
        <div class="lg:col-span-5 flex flex-col items-center">
            <div class="w-full bg-darkcard border border-darkborder rounded-3xl p-6 md:p-8 shadow-2xl flex flex-col items-center">
                <h2 class="text-lg font-black text-white uppercase tracking-tight mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand text-lg">badge</span>
                    Minha Carteirinha Digital
                </h2>

                <!-- Renderização ou Fallback -->
                <div class="carteirinha-wrapper w-full flex justify-center mb-6">
                    <?php if ($template): ?>
                        <?php
                        $elementos = json_decode($template['elementos_json'], true) ?: [];
                        
                        function substituirTagsLocal($val, $m, $validade_br) {
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
                                '{Válida Até}' => $validade_br,
                                '{valida_ate}' => $validade_br
                            ];
                            
                            return str_replace(array_keys($substituicoes), array_values($substituicoes), $val);
                        }
                        ?>
                        <div class="carteirinha-scale">
                            <div class="carteirinha-container relative bg-[#0A0A0A] rounded-2xl border border-brand/20 shadow-2xl overflow-hidden p-0"
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
                                        <?php if ($el['type'] === 'text' || $el['type'] === 'dynamic'): ?>
                                            <span><?= htmlspecialchars(substituirTagsLocal($el['value'], $m, $validade_br)) ?></span>
                                            
                                        <?php elseif ($el['type'] === 'foto_membro'): ?>
                                            <div class="size-20 bg-darkbg border border-brand/40 overflow-hidden flex items-center justify-center rounded-lg shadow-md">
                                                <?php if ($m['foto'] && file_exists('uploads/membros/' . $m['foto'])): ?>
                                                    <img src="uploads/membros/<?= htmlspecialchars($m['foto']) ?>" class="w-full h-full object-cover">
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
                                                    <div class="w-24 border-b border-gray-600 h-6"></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Fallback visual se nenhum template estiver cadastrado -->
                        <div class="carteirinha-scale">
                            <div class="carteirinha-container relative bg-gradient-to-br from-neutral-900 to-black rounded-2xl border border-brand/20 shadow-2xl p-6 flex flex-col justify-between">
                                <div class="flex justify-between items-start">
                                    <div class="flex items-center gap-2">
                                        <div class="size-8 bg-brand rounded-lg flex items-center justify-center text-black font-bold">
                                            <span class="material-symbols-outlined text-sm">church</span>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-black text-white leading-none uppercase">Carteirinha Digital</h4>
                                            <p class="text-[8px] text-gray-500 mt-0.5 uppercase tracking-wider"><?= htmlspecialchars($m['congregacao_nome'] ?? 'Sede Central') ?></p>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 bg-brand/10 border border-brand/20 rounded text-[8px] font-bold text-brand uppercase"><?= htmlspecialchars($m['cargo_nome'] ?? 'Membro') ?></span>
                                </div>
                                
                                <div class="flex gap-4 items-center">
                                    <div class="size-16 bg-white/5 border border-white/10 rounded-lg overflow-hidden flex items-center justify-center text-gray-600">
                                        <?php if ($m['foto'] && file_exists('uploads/membros/' . $m['foto'])): ?>
                                            <img src="uploads/membros/<?= htmlspecialchars($m['foto']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <span class="material-symbols-outlined text-4xl">person</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 space-y-1">
                                        <p class="text-sm font-bold text-white leading-tight"><?= htmlspecialchars($m['nome']) ?></p>
                                        <p class="text-[9px] text-gray-400 font-mono">CPF: <?= htmlspecialchars($m['cpf'] ?? '---') ?></p>
                                        <p class="text-[8px] text-gray-500 uppercase tracking-widest">Validade: <?= $validade_br ?></p>
                                    </div>
                                    <div class="size-12 bg-white p-0.5 rounded shadow">
                                        <img src="<?= $qr_url ?>" class="w-full h-full object-contain">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Crachá de Status de Validade -->
                <div class="w-full p-4 rounded-2xl bg-black border border-darkborder flex items-center justify-between mb-6">
                    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Status da Carteirinha</span>
                    <?php if ($is_valida): ?>
                        <span class="px-3 py-1 bg-green-500/10 border border-green-500/20 rounded-full text-[10px] font-black text-green-400 uppercase tracking-wider flex items-center gap-1">
                            <span class="size-1.5 rounded-full bg-green-400"></span>
                            Ativa • Válida
                        </span>
                    <?php else: ?>
                        <span class="px-3 py-1 bg-red-500/10 border border-red-500/20 rounded-full text-[10px] font-black text-red-500 uppercase tracking-wider flex items-center gap-1">
                            <span class="size-1.5 rounded-full bg-red-500"></span>
                            Expirada / Inativa
                        </span>
                    <?php endif; ?>
                </div>

                <div class="w-full space-y-3">
                    <button onclick="window.print()" class="w-full py-4 rounded-xl bg-brand hover:bg-yellow-500 text-black font-black uppercase text-xs tracking-widest shadow-lg shadow-brand/10 transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-lg">print</span>
                        Imprimir Documento
                    </button>
                    <p class="text-[9px] text-gray-600 text-center uppercase font-bold tracking-widest leading-relaxed">
                        Apresente o QR Code na recepção/secretaria de eventos para validar sua presença e autenticidade.
                    </p>
                </div>
            </div>
        </div>

        <!-- Coluna 2: Ficha Cadastral & Avisos -->
        <div class="lg:col-span-7 space-y-8">
            <!-- Ficha Cadastral -->
            <div class="bg-darkcard border border-darkborder rounded-3xl p-8 shadow-2xl space-y-6">
                <h3 class="text-sm font-black text-brand uppercase tracking-widest border-b border-white/5 pb-4 mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">person_search</span>
                    Ficha Cadastral Ministerial
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Nome Completo</p>
                        <p class="text-sm font-bold text-white mt-1"><?= htmlspecialchars($m['nome']) ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">CPF</p>
                        <p class="text-sm font-mono text-gray-300 mt-1"><?= htmlspecialchars($m['cpf'] ?? '---') ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Telefone / WhatsApp</p>
                        <p class="text-sm font-bold text-white mt-1"><?= htmlspecialchars($m['telefone'] ?? '---') ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">E-mail</p>
                        <p class="text-sm font-medium text-gray-300 mt-1"><?= htmlspecialchars($m['email'] ?? '---') ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Data de Nascimento</p>
                        <p class="text-sm font-bold text-white mt-1"><?= $m['data_nascimento'] ? date('d/m/Y', strtotime($m['data_nascimento'])) : '---' ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Data de Cadastro</p>
                        <p class="text-sm font-bold text-white mt-1"><?= date('d/m/Y', strtotime($m['data_cadastro'])) ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Cargo Ministerial</p>
                        <p class="text-sm font-black text-brand uppercase mt-1"><?= htmlspecialchars($m['cargo_nome'] ?? 'Membro Comum') ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Congregação Vinculada</p>
                        <p class="text-sm font-bold text-white mt-1"><?= htmlspecialchars($m['congregacao_nome'] ?? 'Sede Central') ?></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Endereço Residencial</p>
                        <p class="text-sm text-gray-300 mt-1 leading-relaxed"><?= htmlspecialchars($m['endereco'] ?? 'Não informado') ?></p>
                    </div>
                </div>
            </div>

            <!-- Avisos e Comunicados da Igreja -->
            <div class="bg-darkcard border border-darkborder rounded-3xl p-8 shadow-2xl">
                <h3 class="text-sm font-black text-brand uppercase tracking-widest border-b border-white/5 pb-4 mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">campaign</span>
                    Avisos e Comunicados
                </h3>

                <div class="space-y-6">
                    <?php if (empty($comunicados)): ?>
                        <div class="text-center py-10 opacity-30">
                            <span class="material-symbols-outlined text-4xl mb-2">notifications_off</span>
                            <p class="text-xs uppercase font-bold tracking-widest">Nenhum comunicado cadastrado no momento</p>
                        </div>
                    <?php else: foreach ($comunicados as $c): ?>
                        <div class="p-5 rounded-2xl bg-black border border-darkborder space-y-3">
                            <div class="flex items-center justify-between border-b border-white/5 pb-2">
                                <h4 class="font-bold text-xs uppercase text-brand tracking-wide"><?= htmlspecialchars($c['assunto'] ?? 'Comunicado Oficial') ?></h4>
                                <span class="text-[9px] text-gray-500 font-mono"><?= date('d/m/Y H:i', strtotime($c['data_envio'])) ?></span>
                            </div>
                            <p class="text-xs text-gray-300 leading-relaxed font-medium"><?= nl2br(htmlspecialchars($c['mensagem'])) ?></p>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
