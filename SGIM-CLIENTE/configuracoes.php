<?php
ob_start();
session_start();
require_once 'config/database.php';

// Verificação de Autenticação e Conexão de Banco
if (!isset($pdo) || $pdo === null) {
    header('Location: setup.php?db_error=1');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = 'SGIM - Configurações';
$current_page = 'configuracoes';

require_once 'includes/header.php';

$mensagem = '';
$erro = false;

// Buscar configurações atuais
$stmt = $pdo->query("SELECT chave, valor FROM configuracoes");
$configs = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $configs[$row['chave']] = $row['valor'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_igreja') {
        $dados = [
            'nome_igreja' => $_POST['nome_igreja'] ?? '',
            'cnpj'        => $_POST['cnpj'] ?? '',
            'endereco_sede' => $_POST['endereco'] ?? ''
        ];

        try {
            $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor, grupo) VALUES (?, ?, 'igreja') ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            foreach ($dados as $chave => $valor) {
                $stmt->execute([$chave, $valor]);
            }
            $mensagem = "Dados da igreja atualizados com sucesso!";
            
            // Recarregar configurações para refletir no formulário
            $stmt = $pdo->query("SELECT chave, valor FROM configuracoes");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $configs[$row['chave']] = $row['valor'];
            }
        } catch (Exception $e) {
            $erro = true;
            $mensagem = "Erro ao salvar: " . $e->getMessage();
        }
    }

    if (isset($_POST['acao']) && $_POST['acao'] === 'toggle_2fa') {
        require_once 'includes/SGIM_2FA.php';
        $user_id = $_SESSION['user_id'];
        $enabled = isset($_POST['2fa_enabled']) ? 1 : 0;
        
        try {
            if ($enabled) {
                // Se estiver ativando, gera um novo secret temporário
                $_SESSION['temp_2fa_secret'] = SGIM_2FA::createSecret();
                $show_2fa_modal = true;
            } else {
                // Se estiver desativando, limpa no banco
                $stmt = $pdo->prepare("UPDATE usuarios SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
                $stmt->execute([$user_id]);
                $mensagem = "Segurança 2FA desativada com sucesso.";
            }
        } catch (Exception $e) {
            $erro = true;
            $mensagem = "Erro: " . $e->getMessage();
        }
    }

    if (isset($_POST['acao']) && $_POST['acao'] === 'confirm_2fa') {
        require_once 'includes/SGIM_2FA.php';
        $code = $_POST['2fa_code'] ?? '';
        $secret = $_SESSION['temp_2fa_secret'] ?? '';
        
        if (SGIM_2FA::verifyCode($secret, $code)) {
            $stmt = $pdo->prepare("UPDATE usuarios SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?");
            $stmt->execute([$secret, $_SESSION['user_id']]);
            unset($_SESSION['temp_2fa_secret']);
            $mensagem = "Segurança 2FA ativada com sucesso!";
        } else {
            $erro = true;
            $mensagem = "Código 2FA inválido. Tente novamente.";
            $show_2fa_modal = true;
        }
    }
}
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h2 class="text-3xl font-bold text-white tracking-tight">Configurações</h2>
        <p class="text-sm text-gray-500 mt-1">Gerencie os dados da igreja e do sistema.</p>
    </div>
</div>

<?php if ($mensagem): ?>
    <div class="mb-6 p-4 rounded-twelve <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3">
        <span class="material-symbols-outlined"><?= $erro ? 'error' : 'check_circle' ?></span>
        <p class="text-sm font-semibold"><?= htmlspecialchars($mensagem) ?></p>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Dados da Igreja -->
    <div class="bg-darkcard rounded-twelve border border-darkborder shadow-sm overflow-hidden">
        <div class="p-6 border-b border-darkborder bg-white/[0.02]">
            <h3 class="text-lg font-bold text-white">Dados da Igreja</h3>
            <p class="text-sm text-gray-500">Informações que aparecerão em documentos e relatórios.</p>
        </div>
        <form method="POST" class="p-6 space-y-6">
            <input type="hidden" name="acao" value="salvar_igreja">
            <div class="space-y-2">
                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Nome da Igreja / Ministério</label>
                <input name="nome_igreja" value="<?= htmlspecialchars($configs['nome_igreja'] ?? '') ?>" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand focus:border-brand outline-none transition-all" type="text" placeholder="Ex: Igreja Evangélica Pentecostal"/>
            </div>
            <div class="space-y-2">
                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">CNPJ</label>
                <input name="cnpj" value="<?= htmlspecialchars($configs['cnpj'] ?? '') ?>" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand focus:border-brand outline-none transition-all" type="text" placeholder="00.000.000/0000-00"/>
            </div>
            <div class="space-y-2">
                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Endereço Sede</label>
                <input name="endereco" value="<?= htmlspecialchars($configs['endereco_sede'] ?? '') ?>" class="w-full px-4 py-3 rounded-twelve border border-darkborder bg-darkbg text-gray-300 focus:ring-1 focus:ring-brand focus:border-brand outline-none transition-all" type="text" placeholder="Rua, Número, Bairro, Cidade - UF"/>
            </div>
            <button type="submit" class="w-full py-3 rounded-twelve bg-brand hover:bg-brand-dark text-black font-bold shadow-lg shadow-brand/10 transition-all">
                Salvar Alterações
            </button>
        </form>
    </div>

    <!-- Sistema e Backup -->
    <div class="bg-darkcard rounded-twelve border border-darkborder shadow-sm overflow-hidden">
        <div class="p-6 border-b border-darkborder bg-white/[0.02]">
            <h3 class="text-lg font-bold text-white">Sistema e Segurança</h3>
            <p class="text-sm text-gray-500">Gestão de licença e backups de segurança.</p>
        </div>
        <div class="p-6 space-y-8">
            <div class="p-4 rounded-twelve bg-darkbg border border-darkborder">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">Chave de Licença</p>
                <div class="flex items-center justify-between">
                    <code class="text-brand font-mono text-sm"><?= htmlspecialchars($configs['license_key'] ?? 'NÃO ATIVADO') ?></code>
                    <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-green-500/10 text-green-400 border border-green-500/20">Ativa</span>
                </div>
            </div>

            <div class="space-y-4">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Distribuição e Master Sync</p>
                <a href="publish_master.php" class="flex items-center gap-4 p-4 rounded-twelve bg-purple-500/5 hover:bg-purple-500/10 transition-all border border-purple-500/20 group block">
                    <div class="size-10 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-lg shadow-purple-500/10">
                        <span class="material-symbols-outlined">publish</span>
                    </div>
                    <div>
                        <p class="font-bold text-sm text-white group-hover:text-purple-400 transition-colors">Publicar Versão Master</p>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider">Sincronizar CLIENTE -> VENDAS (ZIP)</p>
                    </div>
                </a>
                <a href="MANUAL_USUARIO.md" target="_blank" class="flex items-center gap-4 p-4 rounded-twelve bg-darkbg hover:bg-white/5 transition-all border border-darkborder group">
                    <div class="size-10 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-500">
                        <span class="material-symbols-outlined">description</span>
                    </div>
                    <div>
                        <p class="font-bold text-sm text-white group-hover:text-brand transition-colors">Manual do Usuário</p>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider">Aprenda a usar o sistema</p>
                    </div>
                </a>
            </div>

            <div class="space-y-4 pt-4 border-t border-darkborder">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Segurança de Dois Fatores (2FA)</p>
                <?php
                try {
                    // Verifica se a coluna two_factor_enabled existe antes de consultar
                    $check2fa = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'two_factor_enabled'");
                    if ($check2fa->rowCount() > 0) {
                        $stmtUser = $pdo->prepare("SELECT two_factor_enabled FROM usuarios WHERE id = ?");
                        $stmtUser->execute([$_SESSION['user_id']]);
                        $user_2fa = $stmtUser->fetch(PDO::FETCH_ASSOC);
                        $is_2fa_enabled = $user_2fa['two_factor_enabled'] ?? false;
                    } else {
                        $is_2fa_enabled = false;
                    }
                } catch (Exception $e) {
                    $is_2fa_enabled = false;
                }
                ?>
                <form method="POST" class="flex items-center justify-between p-4 rounded-twelve bg-darkbg border border-darkborder">
                    <input type="hidden" name="acao" value="toggle_2fa">
                    <div class="flex items-center gap-4">
                        <div class="size-10 rounded-full <?= $is_2fa_enabled ? 'bg-green-500/10 text-green-500' : 'bg-gray-500/10 text-gray-500' ?> flex items-center justify-center">
                            <span class="material-symbols-outlined"><?= $is_2fa_enabled ? 'verified_user' : 'shield_moon' ?></span>
                        </div>
                        <div>
                            <p class="font-bold text-sm text-white">Autenticação 2FA</p>
                            <p class="text-[10px] text-gray-500 uppercase tracking-wider"><?= $is_2fa_enabled ? 'Proteção Ativa' : 'Proteção Desativada' ?></p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="2fa_enabled" <?= $is_2fa_enabled ? 'checked' : '' ?> onchange="this.form.submit()" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand"></div>
                    </label>
                </form>
            </div>

            <div class="space-y-4 pt-4 border-t border-darkborder">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Backup de Segurança</p>
                <a href="backup.php" class="flex items-center gap-4 p-4 rounded-twelve bg-brand/5 hover:bg-brand/10 transition-all border border-brand/20 group">
                    <div class="size-10 rounded-full bg-brand flex items-center justify-center text-black shadow-lg shadow-brand/10">
                        <span class="material-symbols-outlined">backup</span>
                    </div>
                    <div>
                        <p class="font-bold text-sm text-white group-hover:text-brand transition-colors">Gerar Backup Agora</p>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider">Baixar banco de dados e arquivos</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<?php if (isset($show_2fa_modal) && $show_2fa_modal): 
    require_once 'includes/SGIM_2FA.php';
    $secret = $_SESSION['temp_2fa_secret'];
    $qrCodeUrl = SGIM_2FA::getQRCodeUrl($_SESSION['user_email'] ?? 'Usuario', $secret);
    // Usando API moderna para gerar o QR Code visualmente
    $qrChartUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrCodeUrl);
?>
<div class="fixed inset-0 z-[100] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-darkcard border border-darkborder max-w-md w-full p-8 rounded-2xl shadow-2xl animate-in zoom-in duration-300">
        <div class="text-center mb-6">
            <div class="size-16 bg-brand/10 rounded-2xl flex items-center justify-center text-brand mx-auto mb-4">
                <span class="material-symbols-outlined text-4xl">qr_code_2</span>
            </div>
            <h3 class="text-xl font-bold text-white">Configurar 2FA</h3>
            <p class="text-sm text-gray-500 mt-2">Escaneie o código abaixo com seu app de autenticação (Google Authenticator, Authy, etc).</p>
        </div>

        <div class="bg-white p-4 rounded-xl mb-6 flex justify-center">
            <img src="<?= $qrChartUrl ?>" alt="QR Code 2FA" class="size-48">
        </div>

        <div class="bg-darkbg border border-darkborder p-4 rounded-xl mb-6 text-center">
            <p class="text-[10px] text-gray-500 uppercase font-bold mb-1">Código Manual</p>
            <code class="text-brand font-mono text-lg tracking-widest"><?= $secret ?></code>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="acao" value="confirm_2fa">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Digite o código de 6 dígitos</label>
                <input type="text" name="2fa_code" maxlength="6" required placeholder="000000" class="w-full bg-darkbg border border-darkborder rounded-xl px-4 py-3 text-center text-2xl font-mono text-white tracking-[1em] focus:border-brand outline-none transition-all">
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="location.href='configuracoes.php'" class="flex-1 py-3 text-gray-400 font-bold text-sm hover:text-white transition-all">Cancelar</button>
                <button type="submit" class="flex-1 bg-brand text-black font-bold py-3 rounded-xl hover:bg-brand-dark transition-all">Ativar Agora</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
