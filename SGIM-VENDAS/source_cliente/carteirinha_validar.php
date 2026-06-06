<?php
// AUTO-PONTE: Se existir uma versão mais nova ativa pelo OTA, desvia para ela
$bridge = __DIR__ . '/releases/current/' . basename(__FILE__);
if (file_exists($bridge) && strpos(__DIR__, 'releases') === false) {
    require_once $bridge;
    exit;
}

require_once __DIR__ . '/config/database.php';

// Proteção contra conexão nula
if (!isset($pdo) || $pdo === null) {
    die("Erro de conexão com o banco de dados.");
}

$hash = trim($_GET['hash'] ?? '');
$membro = null;
$valido = false;

if (!empty($hash)) {
    // Busca o membro pelo hash único
    $sql = "SELECT m.*, c.nome as cargo_nome, con.nome as congregacao_nome 
            FROM membros m 
            LEFT JOIN cargos c ON m.cargo_id = c.id 
            LEFT JOIN congregacoes con ON m.congregacao_id = con.id 
            WHERE m.hash_carteirinha = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hash]);
    $membro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($membro) {
        // Verifica a validade
        $data_validade = $membro['carteirinha_valida_ate'];
        if ($data_validade && strtotime($data_validade) >= time()) {
            $valido = true;
        }
    }
}

// Mascara o CPF do membro para proteger a privacidade
function mascararCPF($cpf) {
    $cpf_limpo = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf_limpo) === 11) {
        return "###." . substr($cpf_limpo, 3, 3) . "." . substr($cpf_limpo, 6, 3) . "-##";
    }
    return "###.###.###-##";
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Validador de Carteirinha - SGIM</title>
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
                        darkbg: '#050505',
                        darkcard: '#121212',
                        darkborder: '#1E1E1E'
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-darkbg text-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-darkcard rounded-3xl border border-darkborder p-8 md:p-10 shadow-2xl relative overflow-hidden text-center">
        
        <?php if ($membro): ?>
            <!-- SE ENCONTROU O MEMBRO -->
            
            <?php if ($valido): ?>
                <!-- STATUS VÁLIDO -->
                <div class="absolute top-0 inset-x-0 h-2.5 bg-green-500"></div>
                <div class="inline-flex p-4 bg-green-500/10 border border-green-500/20 rounded-full text-green-400 mb-6 mt-4">
                    <span class="material-symbols-outlined text-4xl">verified</span>
                </div>
                <h1 class="text-xl font-black text-green-400 uppercase tracking-wide">Documento Autêntico</h1>
                <p class="text-xs text-gray-400 uppercase tracking-widest mt-1">Validação Concluída com Sucesso</p>
            <?php else: ?>
                <!-- STATUS EXPIRADO -->
                <div class="absolute top-0 inset-x-0 h-2.5 bg-red-500"></div>
                <div class="inline-flex p-4 bg-red-500/10 border border-red-500/20 rounded-full text-red-500 mb-6 mt-4">
                    <span class="material-symbols-outlined text-4xl">gpp_maybe</span>
                </div>
                <h1 class="text-xl font-black text-red-500 uppercase tracking-wide">Documento Expirado</h1>
                <p class="text-xs text-gray-400 uppercase tracking-widest mt-1">A validade deste documento expirou</p>
            <?php endif; ?>

            <!-- Dados do Membro -->
            <div class="mt-8 flex flex-col items-center">
                <div class="size-28 rounded-full border-4 <?= $valido ? 'border-green-500/20' : 'border-red-500/20' ?> p-1 mb-4">
                    <div class="w-full h-full rounded-full bg-black overflow-hidden flex items-center justify-center">
                        <?php if ($membro['foto'] && file_exists('uploads/membros/' . $membro['foto'])): ?>
                            <img src="uploads/membros/<?= htmlspecialchars($membro['foto']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="material-symbols-outlined text-gray-700 text-5xl">person</span>
                        <?php endif; ?>
                    </div>
                </div>

                <h2 class="text-lg font-bold text-white"><?= htmlspecialchars($membro['nome']) ?></h2>
                <p class="text-xs font-black text-brand uppercase mt-1 leading-none"><?= htmlspecialchars($membro['cargo_nome'] ?? 'Membro') ?></p>
                <p class="text-[10px] text-gray-500 uppercase tracking-wider mt-2"><?= htmlspecialchars($membro['congregacao_nome'] ?? 'Sede Central') ?></p>
            </div>

            <div class="mt-8 space-y-4 border-t border-b border-darkborder py-6 text-left text-xs">
                <div class="flex justify-between">
                    <span class="text-gray-500 uppercase font-bold tracking-wider text-[10px]">Identificação (CPF)</span>
                    <span class="font-mono text-gray-300"><?= htmlspecialchars(mascararCPF($membro['cpf'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 uppercase font-bold tracking-wider text-[10px]">Data de Emissão</span>
                    <span class="font-medium text-gray-300"><?= date('d/m/Y', strtotime($membro['data_cadastro'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 uppercase font-bold tracking-wider text-[10px]">Validade do Documento</span>
                    <span class="font-bold <?= $valido ? 'text-green-400' : 'text-red-500' ?>"><?= $membro['carteirinha_valida_ate'] ? date('d/m/Y', strtotime($membro['carteirinha_valida_ate'])) : '---' ?></span>
                </div>
            </div>

            <div class="mt-8 flex justify-center text-[10px] font-bold text-gray-600 uppercase tracking-widest leading-relaxed">
                SGIM Sistema de Autenticidade Digital
            </div>

        <?php else: ?>
            <!-- SE HASH INVÁLIDO -->
            <div class="absolute top-0 inset-x-0 h-2.5 bg-red-600"></div>
            <div class="inline-flex p-4 bg-red-600/10 border border-red-600/20 rounded-full text-red-500 mb-6 mt-4">
                <span class="material-symbols-outlined text-4xl">block</span>
            </div>
            <h1 class="text-xl font-black text-red-500 uppercase tracking-wide">Documento Inválido</h1>
            <p class="text-xs text-gray-400 uppercase tracking-widest mt-1">Este QR Code não foi reconhecido</p>

            <div class="bg-black/50 border border-darkborder rounded-2xl p-6 mt-8 text-xs text-left text-gray-400 leading-relaxed">
                <span class="font-bold text-white block mb-2 uppercase text-[10px] tracking-wider">Atenção</span>
                O código escaneado não pertence a nenhuma carteirinha digital registrada ou ativa no SGIM. Certifique-se de escanear o documento oficial emitido pela secretaria da congregação.
            </div>

            <div class="mt-8 pt-6 border-t border-darkborder">
                <a href="portal.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-white uppercase tracking-widest transition-colors">
                    <span class="material-symbols-outlined text-sm">home</span>
                    Ir para o Portal
                </a>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>
