<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/db.php';
require_once 'includes/BackupService.php';

$page_title = 'SGIM - Backup de Segurança';
$current_page = 'configuracoes';

require_once 'includes/header.php';

$backupDir = __DIR__ . '/backups';
$backupService = new BackupService($pdo, $backupDir);

$mensagem = '';
$erro = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao']) && $_POST['acao'] === 'gerar_backup') {
        $tipo = $_POST['tipo'] ?? 'db';
        if ($tipo === 'full') {
            $filename = $backupService->createFullBackup();
        } else {
            $filename = $backupService->createDatabaseBackup();
        }
        
        if ($filename) {
            $mensagem = "Backup gerado com sucesso: $filename. <a href='download_backup.php?file=$filename' class='underline font-bold' download>Clique aqui para baixar</a>";
        } else {
            $erro = true;
            $mensagem = "Falha ao gerar backup. Verifique as permissões da pasta /backups.";
        }
    }
    
    if (isset($_POST['acao']) && $_POST['acao'] === 'excluir_backup') {
        $file = $_POST['file'] ?? '';
        $path = $backupDir . '/' . basename($file);
        if (file_exists($path)) {
            unlink($path);
            $mensagem = "Backup excluído com sucesso.";
        }
    }

    if (isset($_POST['acao']) && $_POST['acao'] === 'restaurar_backup') {
        $file = $_POST['file'] ?? '';
        $res = $backupService->restoreFullBackup($file);
        if ($res['success']) {
            $mensagem = $res['message'];
        } else {
            $erro = true;
            $mensagem = $res['message'];
        }
    }
}

if (isset($_GET['acao']) && $_GET['acao'] === 'gerar') {
    $filename = $backupService->createDatabaseBackup();
    if ($filename) {
        $mensagem = "Backup gerado com sucesso: $filename. <a href='download_backup.php?file=$filename' class='underline font-bold' download>Clique aqui para baixar</a>";
    } else {
        $erro = true;
        $mensagem = "Falha ao gerar backup. Verifique as permissões da pasta /backups.";
    }
}

$backups = $backupService->listBackups();
?>

<div class="flex items-center justify-between mb-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
    <div>
        <h2 class="text-3xl font-bold text-white tracking-tight">Central de Backups</h2>
        <p class="text-sm text-gray-500 mt-1">Proteja seus dados gerando cópias de segurança do banco de dados ou sistema completo.</p>
    </div>
    <div class="flex gap-3">
        <form method="POST">
            <input type="hidden" name="acao" value="gerar_backup">
            <input type="hidden" name="tipo" value="db">
            <button type="submit" class="bg-darkcard border border-darkborder text-white font-bold py-3 px-6 rounded-xl uppercase tracking-widest hover:border-brand transition-all flex items-center gap-2 text-xs">
                <span class="material-symbols-outlined text-brand">database</span>
                Apenas DB
            </button>
        </form>
        <form method="POST">
            <input type="hidden" name="acao" value="gerar_backup">
            <input type="hidden" name="tipo" value="full">
            <button type="submit" class="bg-brand text-black font-black py-3 px-6 rounded-xl uppercase tracking-widest hover:scale-[1.02] active:scale-95 transition-all shadow-lg shadow-brand/20 flex items-center gap-2 text-xs">
                <span class="material-symbols-outlined">auto_fix_high</span>
                Backup Completo
            </button>
        </form>
    </div>
</div>

<?php if ($mensagem): ?>
    <div class="mb-6 p-4 rounded-twelve <?= $erro ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-green-500/10 border-green-500/20 text-green-400' ?> border flex items-center gap-3 animate-in zoom-in duration-300">
        <span class="material-symbols-outlined"><?= $erro ? 'error' : 'check_circle' ?></span>
        <p class="text-sm font-semibold"><?= $mensagem ?></p>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    <div class="lg:col-span-2">
        <div class="bg-darkcard border border-darkborder rounded-2xl overflow-hidden shadow-xl animate-in fade-in slide-in-from-bottom-6 duration-700">
            <div class="p-6 border-b border-darkborder bg-white/[0.02] flex items-center justify-between">
                <h3 class="text-lg font-bold text-white">Backups Disponíveis</h3>
                <span class="text-[10px] text-gray-500 uppercase font-black tracking-widest">Local: /backups/</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-black/20">
                            <th class="px-6 py-4 text-[10px] font-black text-gray-500 uppercase tracking-widest">Arquivo</th>
                            <th class="px-6 py-4 text-[10px] font-black text-gray-500 uppercase tracking-widest">Data</th>
                            <th class="px-6 py-4 text-[10px] font-black text-gray-500 uppercase tracking-widest">Tamanho</th>
                            <th class="px-6 py-4 text-[10px] font-black text-gray-500 uppercase tracking-widest text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-darkborder">
                        <?php if (empty($backups)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500 italic text-sm">
                                    Nenhum backup encontrado. Gere um agora mesmo!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($backups as $b): ?>
                                <tr class="hover:bg-white/[0.02] transition-all">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="material-symbols-outlined <?= $b['type'] === 'zip' ? 'text-amber-500' : 'text-brand' ?> text-lg">
                                                <?= $b['type'] === 'zip' ? 'package_2' : 'database' ?>
                                            </span>
                                            <div class="flex flex-col">
                                                <span class="text-xs font-medium text-gray-300"><?= $b['name'] ?></span>
                                                <span class="text-[9px] text-gray-600 uppercase font-black uppercase tracking-tighter"><?= $b['type'] === 'zip' ? 'Sistema Completo' : 'Apenas Banco de Dados' ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-[10px] text-gray-500 font-mono">
                                        <?= date('d/m/Y H:i:s', strtotime($b['date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 text-[10px] text-gray-500">
                                        <?= round($b['size'] / (1024 * 1024), 2) ?> MB
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <?php if ($b['type'] === 'zip'): ?>
                                                <form method="POST" onsubmit="return confirm('ATENÇÃO: A restauração irá substituir os arquivos e o banco de dados atual. Deseja continuar?')">
                                                    <input type="hidden" name="acao" value="restaurar_backup">
                                                    <input type="hidden" name="file" value="<?= $b['name'] ?>">
                                                    <button type="submit" class="p-2 bg-amber-500/10 text-amber-500 rounded-lg hover:bg-amber-500/20 transition-all" title="Restaurar este Backup">
                                                        <span class="material-symbols-outlined text-lg">settings_backup_restore</span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="download_backup.php?file=<?= $b['name'] ?>" download class="p-2 bg-blue-500/10 text-blue-500 rounded-lg hover:bg-blue-500/20 transition-all" title="Download">
                                                <span class="material-symbols-outlined text-lg">download</span>
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este backup?')">
                                                <input type="hidden" name="acao" value="excluir_backup">
                                                <input type="hidden" name="file" value="<?= $b['name'] ?>">
                                                <button type="submit" class="p-2 bg-red-500/10 text-red-500 rounded-lg hover:bg-red-500/20 transition-all" title="Excluir">
                                                    <span class="material-symbols-outlined text-lg">delete</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="lg:col-span-1 space-y-6">
        <div class="bg-darkcard border border-darkborder rounded-2xl p-6 shadow-xl">
            <div class="size-12 bg-brand/10 rounded-xl flex items-center justify-center text-brand mb-4">
                <span class="material-symbols-outlined text-2xl">security</span>
            </div>
            <h4 class="text-white font-bold text-sm mb-2">Segurança em Primeiro Lugar</h4>
            <p class="text-xs text-gray-500 leading-relaxed mb-4">
                Sempre realize um backup antes de aplicar atualizações ou fazer mudanças estruturais no seu servidor.
            </p>
            <div class="p-4 bg-blue-500/5 border border-blue-500/10 rounded-xl">
                <p class="text-[10px] text-blue-400 italic">
                    <i class="fas fa-info-circle mr-1"></i> Recomendamos baixar o arquivo de backup para o seu computador.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
