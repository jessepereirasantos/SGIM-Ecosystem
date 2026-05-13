<?php
/**
 * SGIM OTA - Diagnóstico de Integridade de Arquivos
 * Esta página prova quais arquivos foram instalados pela atualização OTA
 */
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$root = __DIR__ . '/';

// Arquivos-chave para verificar
$files_to_check = [
    'teste.php'                          => 'Aba de Teste OTA (NOVA)',
    'includes/header.php'                => 'Sidebar com link Teste',
    'src/Updater/UpdaterCore.php'        => 'Motor OTA com logs',
    'api/ota_process.php'                => 'Controlador OTA',
    'atualizacoes.php'                   => 'Central de Atualizações',
    'dashboard.php'                      => 'Dashboard com banner',
    'src/bootstrap.php'                  => 'Bootstrap corrigido',
    'backups/temp_update/'               => 'Diretório temp (necessário)',
];

// Versão atual do banco
$v_db = 'N/A';
try {
    $v_db = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'")->fetchColumn();
} catch (Exception $e) {}

// Verificar se backups/ é gravável
$backups_writable = is_writable($root . 'backups') ? '✅ Gravável' : '❌ SEM PERMISSÃO';
if (!is_dir($root . 'backups')) $backups_writable = '❌ Não existe';

$page_title = 'SGIM - Diagnóstico OTA';
$current_page = 'diagnostico_ota';
require_once 'includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-3xl font-black text-white tracking-tight italic uppercase">Diagnóstico <span class="text-brand">OTA</span></h2>
    <p class="text-xs text-gray-500 uppercase tracking-widest font-bold mt-1">Verificação de integridade dos arquivos instalados via atualização automática</p>
</div>

<div class="max-w-4xl space-y-6">
    <!-- Info Geral -->
    <div class="bg-darkcard border border-darkborder rounded-twelve p-6">
        <h3 class="text-white font-bold mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-brand">info</span> Status Geral
        </h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="bg-darkbg rounded-xl p-3">
                <p class="text-gray-500 text-xs uppercase font-bold">Versão no Banco</p>
                <p class="text-brand font-black text-lg"><?= htmlspecialchars($v_db) ?></p>
            </div>
            <div class="bg-darkbg rounded-xl p-3">
                <p class="text-gray-500 text-xs uppercase font-bold">Pasta backups/ (gravação ZIP)</p>
                <p class="text-white font-bold"><?= $backups_writable ?></p>
            </div>
            <div class="bg-darkbg rounded-xl p-3">
                <p class="text-gray-500 text-xs uppercase font-bold">Raiz do Sistema</p>
                <p class="text-gray-400 font-mono text-xs"><?= $root ?></p>
            </div>
            <div class="bg-darkbg rounded-xl p-3">
                <p class="text-gray-500 text-xs uppercase font-bold">PHP Version</p>
                <p class="text-white font-bold"><?= PHP_VERSION ?></p>
            </div>
        </div>
    </div>

    <!-- Verificação de Arquivos -->
    <div class="bg-darkcard border border-darkborder rounded-twelve p-6">
        <h3 class="text-white font-bold mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-brand">folder_check</span> Verificação de Arquivos
        </h3>
        <div class="space-y-2">
            <?php foreach ($files_to_check as $file => $desc): ?>
                <?php
                $full = $root . $file;
                $exists = file_exists($full);
                $mtime = $exists ? date('d/m/Y H:i:s', filemtime($full)) : 'N/A';
                $size  = $exists && is_file($full) ? round(filesize($full) / 1024, 1) . ' KB' : ($exists ? 'DIR' : 'N/A');
                ?>
                <div class="flex items-center justify-between bg-darkbg rounded-xl px-4 py-3 border <?= $exists ? 'border-emerald-500/20' : 'border-red-500/30' ?>">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-sm <?= $exists ? 'text-emerald-400' : 'text-red-400' ?>">
                            <?= $exists ? 'check_circle' : 'cancel' ?>
                        </span>
                        <div>
                            <p class="text-white text-sm font-mono"><?= $file ?></p>
                            <p class="text-gray-500 text-xs"><?= $desc ?></p>
                        </div>
                    </div>
                    <div class="text-right text-xs">
                        <p class="text-gray-400 font-mono"><?= $mtime ?></p>
                        <p class="text-gray-600"><?= $size ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Verificar Permissões de Escrita -->
    <div class="bg-darkcard border border-darkborder rounded-twelve p-6">
        <h3 class="text-white font-bold mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-brand">lock</span> Permissões de Escrita
        </h3>
        <?php
        $dirs_to_test = [__DIR__, __DIR__ . '/includes', __DIR__ . '/src', __DIR__ . '/api', __DIR__ . '/backups'];
        foreach ($dirs_to_test as $dir): 
            $writable = is_writable($dir);
        ?>
        <div class="flex items-center justify-between py-2 border-b border-darkborder last:border-0">
            <span class="text-gray-400 font-mono text-xs"><?= str_replace(__DIR__, '[ROOT]', $dir) ?></span>
            <span class="text-xs font-bold <?= $writable ? 'text-emerald-400' : 'text-red-400' ?>">
                <?= $writable ? '✅ Gravável' : '❌ Bloqueado' ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
