<?php
/**
 * SGIM EMERGENCY ACTIVATOR
 * Força o symlink releases/current a apontar para v1.4.6
 * URL: /sgim-iade/api/ota_activate_146.php
 * 
 * APAGUE APÓS USO.
 */
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Token mínimo de segurança
$token = $_GET['token'] ?? '';
if ($token !== 'sgim2026') {
    die('<h2 style="color:red;font-family:Arial">Acesso negado. Token inválido.</h2>');
}

$baseDir = realpath(__DIR__ . '/../');
if (!$baseDir) {
    die('<h2 style="color:red;font-family:Arial">Erro: não encontrou o diretório base.</h2>');
}

$releasesDir = $baseDir . '/releases/';
$targetVersion = 'v1.4.6';
$targetPath    = $releasesDir . $targetVersion;
$currentLink   = $releasesDir . 'current';

$log = [];
$ok  = false;

$log[] = "Base detectada: $baseDir";
$log[] = "Releases dir: $releasesDir";
$log[] = "Target: $targetPath";
$log[] = "Current link: $currentLink";
$log[] = "Target existe? " . (is_dir($targetPath) ? '✅ SIM' : '❌ NÃO');

// Verifica se v1.4.6 existe
if (!is_dir($targetPath)) {
    // Tenta outras versões disponíveis em ordem
    $releases = glob($releasesDir . 'v*', GLOB_ONLYDIR);
    if (!empty($releases)) {
        usort($releases, 'version_compare');
        $lastRelease = end($releases);
        $targetPath  = $lastRelease;
        $targetVersion = basename($lastRelease);
        $log[] = "v1.4.6 não encontrada. Usando: $targetVersion";
    } else {
        $log[] = "ERRO: Nenhuma release encontrada.";
        $targetPath = null;
    }
}

if ($targetPath && is_dir($targetPath)) {
    // Lê o que current aponta agora
    if (is_link($currentLink)) {
        $currentTarget = readlink($currentLink);
        $log[] = "Current atual aponta para: $currentTarget";
        // Remove o symlink antigo
        if (unlink($currentLink)) {
            $log[] = "✅ Symlink antigo removido.";
        } else {
            $log[] = "⚠️ Não conseguiu remover o symlink (pode ser diretório físico).";
        }
    } elseif (is_dir($currentLink)) {
        $log[] = "Current é um DIRETÓRIO físico (não symlink). Tentando copiar arquivos diretamente...";
        // Copia os arquivos da nova versão para current/
        $copied = 0;
        $failed = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $relPath = substr($item->getPathname(), strlen($targetPath) + 1);
            $destPath = $currentLink . '/' . $relPath;
            if ($item->isDir()) {
                @mkdir($destPath, 0755, true);
            } else {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
                if (copy($item->getPathname(), $destPath)) {
                    $copied++;
                } else {
                    $failed++;
                }
            }
        }
        $log[] = "Cópia: $copied arquivos copiados, $failed falhas.";
        $ok = ($copied > 0);
        // Agora verifica especificamente usuario_novo.php
        $unTarget = $currentLink . '/usuario_novo.php';
        $unSource = $targetPath . '/usuario_novo.php';
        if (file_exists($unSource) && file_exists($unTarget)) {
            $log[] = "✅ usuario_novo.php copiado com sucesso!";
        } elseif (file_exists($unSource)) {
            if (copy($unSource, $unTarget)) {
                $log[] = "✅ usuario_novo.php copiado manualmente!";
                $ok = true;
            }
        } else {
            $log[] = "⚠️ usuario_novo.php não encontrado em $targetVersion.";
        }
    }

    // Tenta criar symlink se não era diretório físico
    if (!is_dir($currentLink) || is_link($currentLink)) {
        if (symlink($targetPath, $currentLink)) {
            $log[] = "✅ Novo symlink criado: current → $targetVersion";
            $ok = true;
        } else {
            $log[] = "⚠️ Não conseguiu criar symlink (ambiente shared hosting pode não suportar).";
            // Tenta cópia manual como fallback
            $log[] = "Tentando cópia manual dos arquivos essenciais...";
            @mkdir($currentLink, 0755, true);
            $essenciais = ['usuario_novo.php', 'usuarios.php', 'usuario_editar.php'];
            foreach ($essenciais as $f) {
                $src = $targetPath . '/' . $f;
                $dst = $currentLink . '/' . $f;
                if (file_exists($src) && copy($src, $dst)) {
                    $log[] = "✅ $f copiado.";
                    $ok = true;
                } else {
                    $log[] = "❌ $f: falhou ($src → $dst)";
                }
            }
        }
    }

    // Atualiza o banco de dados com a nova versão
    try {
        require_once __DIR__ . '/../config/database.php';
        $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'versao_sistema'");
        $stmt->execute([$targetVersion === 'v1.4.6' ? '1.4.6' : ltrim($targetVersion, 'v')]);
        $log[] = "✅ Banco de dados atualizado para $targetVersion.";
    } catch (Exception $e) {
        $log[] = "⚠️ Banco: " . $e->getMessage();
    }
}

// Verifica se usuario_novo.php existe agora em algum lugar acessível
$unCheck = [
    $baseDir . '/usuario_novo.php',
    $releasesDir . 'current/usuario_novo.php',
];
$log[] = "--- Verificação final ---";
foreach ($unCheck as $p) {
    $log[] = ($p . ': ' . (file_exists($p) ? '✅ EXISTE' : '❌ NÃO EXISTE'));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>SGIM Emergency Activator</title>
<style>
body { font-family: Arial, sans-serif; background: #050505; color: #eee; padding: 40px; max-width: 900px; margin: 0 auto; }
h1 { color: #FFC107; margin-bottom: 30px; }
.log { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 8px; padding: 20px; font-family: monospace; font-size: 13px; line-height: 1.8; }
.ok { color: #22c55e; } .fail { color: #ef4444; } .warn { color: #f59e0b; }
.btn { display: inline-block; margin: 10px 5px 0 0; padding: 12px 24px; background: rgba(255,193,7,0.1); border: 1px solid rgba(255,193,7,0.3); color: #FFC107; border-radius: 6px; text-decoration: none; font-weight: bold; }
.btn:hover { background: rgba(255,193,7,0.2); }
.status { padding: 20px; border-radius: 8px; margin: 20px 0; font-size: 18px; font-weight: bold; }
.status.success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; }
.status.failure { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; }
</style>
</head>
<body>
<h1>🔧 SGIM Emergency Activator</h1>

<div class="status <?= $ok ? 'success' : 'failure' ?>">
    <?= $ok ? '✅ OPERAÇÃO CONCLUÍDA COM SUCESSO' : '❌ ALGUMAS OPERAÇÕES FALHARAM' ?>
</div>

<div class="log">
<?php foreach ($log as $line): ?>
<div class="<?= strpos($line, '✅') !== false ? 'ok' : (strpos($line, '❌') !== false ? 'fail' : (strpos($line, '⚠️') !== false ? 'warn' : '')) ?>">
    <?= htmlspecialchars($line) ?>
</div>
<?php endforeach; ?>
</div>

<?php if ($ok): ?>
<div style="margin-top:20px">
    <a href="../usuarios.php" class="btn">→ Ir para Usuários</a>
    <a href="../usuario_novo.php" class="btn">→ Testar Novo Usuário</a>
    <a href="../dashboard.php" class="btn">→ Dashboard</a>
</div>
<?php endif; ?>

<div style="margin-top: 30px; padding: 15px; background: #1a1a1a; border-radius: 8px; font-size: 12px; color: #666;">
⚠️ <strong>APAGUE ESTE ARQUIVO APÓS USO!</strong><br>
Caminho: <?= htmlspecialchars(__FILE__) ?>
</div>
</body>
</html>
