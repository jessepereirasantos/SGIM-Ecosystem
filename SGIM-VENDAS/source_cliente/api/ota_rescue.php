<?php
/**
 * SGIM FORCE DEPLOYER v1.5.2
 * URL: /SGIM-CLIENTE/api/ota_rescue.php?token=sgim2026
 * Sincroniza fisicamente os arquivos da v1.5.2 do Git para a pasta de produção ativa.
 */
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Validação de Token
$token = $_GET['token'] ?? '';
if ($token !== 'sgim2026') {
    die('<h2 style="color:red;font-family:Arial">Acesso negado. Token inválido.</h2>');
}

$log = [];
$ok = true;

// 2. Mapeamento de Pastas no Servidor HostGator
$srcBase = '/home1/hg9a3205/public_html/SGIM-CLIENTE';
$dstBase = '/home1/hg9a3205/public_html'; // A raiz real do site detectada em produção

$log[] = "Pasta de Origem (Git): $srcBase";
$log[] = "Pasta de Destino (Produção): $dstBase";

if (!is_dir($srcBase)) {
    die("<h2 style='color:red'>Erro: Pasta de origem Git ($srcBase) não existe.</h2>");
}

// Busca dinâmica de redundância caso o destino padrão mude
if (!is_dir($dstBase) || !file_exists($dstBase . '/config/database.php')) {
    $candidatos = [
        '/home1/hg9a3205/public_html',
        '/home1/hg9a3205/public_html/sgim-iade',
        '/home1/hg9a3205/sgim-iade',
        '/home1/hg9a3205/iadeeloha.com.br/sgim-iade',
    ];
    
    foreach ($candidatos as $c) {
        if (is_dir($c) && (file_exists($c . '/config/database.php') || file_exists($c . '/config/db_config.php'))) {
            $dstBase = $c;
            $log[] = "🔍 Pasta de destino encontrada dinamicamente: $dstBase";
            break;
        }
    }
}

if (!is_dir($dstBase)) {
    $diagnostico = "";
    
    $parent1 = '/home1/hg9a3205/public_html';
    if (is_dir($parent1)) {
        $files1 = scandir($parent1);
        $diagnostico .= "<h3>Pastas em $parent1:</h3><ul>";
        foreach ($files1 as $f) {
            if ($f !== '.' && $f !== '..' && is_dir($parent1 . '/' . $f)) {
                $diagnostico .= "<li>$f</li>";
            }
        }
        $diagnostico .= "</ul>";
    }
    
    die("<h2 style='color:red'>Erro: Pasta de destino ($dstBase) não existe.</h2>" . $diagnostico);
}

// 3. Detectar a pasta do Release Current
$dstCurrent = $dstBase . '/releases/current';
$log[] = "Pasta do Release Ativo (Current): $dstCurrent";

// Se o releases/current existir, copiamos para ele também
$alvosDestino = [$dstBase];
if (is_dir($dstCurrent)) {
    $alvosDestino[] = $dstCurrent;
}

// 4. Lista de arquivos a serem copiados
$arquivos = [
    'usuarios.php',
    'usuario_novo.php',
    'usuario_editar.php',
    'includes/header.php',
    'src/Auth/AccessManager.php'
];

// 5. Executar a cópia física dos arquivos
foreach ($arquivos as $relPath) {
    $srcFile = $srcBase . '/' . $relPath;
    if (!file_exists($srcFile)) {
        $log[] = "❌ Arquivo não encontrado na origem: $relPath";
        $ok = false;
        continue;
    }

    foreach ($alvosDestino as $destFolder) {
        $dstFile = $destFolder . '/' . $relPath;
        
        // Garante que o diretório pai do arquivo exista
        $dstDir = dirname($dstFile);
        if (!is_dir($dstDir)) {
            if (!@mkdir($dstDir, 0755, true)) {
                $log[] = "❌ Falha ao criar diretório: $dstDir";
                $ok = false;
                continue;
            }
        }

        // Copia o arquivo
        if (@copy($srcFile, $dstFile)) {
            $log[] = "✅ Copiado: $relPath → $dstFile";
        } else {
            $log[] = "❌ Falha ao copiar: $relPath → $dstFile";
            $ok = false;
        }
    }
}

// 6. Conexão ao Banco de Dados do Cliente e Migração
$dbPath = $dstBase . '/config/database.php';
$dbConfigPath = $dstBase . '/config/db_config.php';
$pdo = null;

if (file_exists($dbPath)) {
    try {
        require_once $dbPath;
    } catch (Throwable $e) {
        $log[] = "⚠️ Erro ao incluir database.php: " . $e->getMessage();
    }
}

if ((!isset($pdo) || !$pdo instanceof PDO) && file_exists($dbConfigPath)) {
    $cfg = file_get_contents($dbConfigPath);
    preg_match("/DB_HOST.*?['\"]([^'\"]+)['\"]/", $cfg, $mh);
    preg_match("/DB_NAME.*?['\"]([^'\"]+)['\"]/", $cfg, $mn);
    preg_match("/DB_USER.*?['\"]([^'\"]+)['\"]/", $cfg, $mu);
    preg_match("/DB_PASS.*?['\"]([^'\"]+)['\"]/", $cfg, $mp);
    if (!empty($mh[1]) && !empty($mn[1])) {
        try {
            $pdo = new PDO("mysql:host={$mh[1]};dbname={$mn[1]};charset=utf8mb4", $mu[1] ?? '', $mp[1] ?? '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $log[] = "✅ Conexão direta estabelecida com o banco de dados.";
        } catch (Throwable $e) {
            $log[] = "❌ Falha na conexão direta com o banco: " . $e->getMessage();
        }
    }
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // A. Forçar versão do sistema
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('versao_sistema', '1.5.2') ON DUPLICATE KEY UPDATE valor = '1.5.2'");
        $stmt->execute();
        
        $stmt2 = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('system_version', '1.5.2') ON DUPLICATE KEY UPDATE valor = '1.5.2'");
        $stmt2->execute();
        $log[] = "✅ Banco de dados atualizado para v1.5.2.";

        // B. Garantir permissões de gestão de usuários no banco do cliente
        $pdo->exec("INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
                   ('usuarios', 'visualizar', 'Visualizar Usuários'),
                   ('usuarios', 'gerenciar', 'Gerenciar Usuários')");
        
        // Dá permissão para o cargo 1
        $pdo->exec("INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) 
                   SELECT 1, id FROM permissoes WHERE modulo = 'usuarios'");
        $log[] = "✅ Permissões de usuários semeadas no banco do cliente.";

    } catch (Throwable $e) {
        $log[] = "❌ Erro nas operações do banco de dados: " . $e->getMessage();
        $ok = false;
    }
} else {
    $log[] = "❌ Não foi possível obter conexão PDO com o banco de dados do cliente.";
    $ok = false;
}

// 7. Limpeza de Opcache
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        $log[] = "✅ Opcache resetado com sucesso.";
    } else {
        $log[] = "⚠️ Falha ao resetar opcache.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>SGIM Force Deployer</title>
<style>
body { font-family: Arial, sans-serif; background: #050505; color: #eee; padding: 40px; max-width: 900px; margin: 0 auto; }
h1 { color: #FFC107; margin-bottom: 30px; }
.log { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 8px; padding: 20px; font-family: monospace; font-size: 13px; line-height: 1.8; }
.ok { color: #22c55e; } .fail { color: #ef4444; } .warn { color: #f59e0b; }
.status { padding: 20px; border-radius: 8px; margin: 20px 0; font-size: 18px; font-weight: bold; }
.status.success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; }
.status.failure { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; }
</style>
</head>
<body>
<h1>🔧 SGIM Force Deployer - v1.5.2</h1>

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
    <p class="ok">O deploy físico dos arquivos da v1.5.2 foi concluído com sucesso!</p>
</div>
<?php endif; ?>
</body>
</html>
