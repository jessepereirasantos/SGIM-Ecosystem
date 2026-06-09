<?php
/**
 * SGIM FORCE DEPLOYER v1.5.5 (RELEASE RESCUE EDITION)
 * URL: /SGIM-CLIENTE/api/ota_rescue.php?token=sgim2026
 * Sincroniza dinamicamente os arquivos da release ativa para a raiz de produção (contorna bloqueio de symlinks da HostGator).
 */
header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Validação de Token de Segurança
$token = $_GET['token'] ?? '';
if ($token !== 'sgim2026') {
    die('<h2 style="color:red;font-family:Arial">Acesso negado. Token inválido.</h2>');
}

$log = [];
$ok = true;

// 2. Detecção Dinâmica de Pastas (Origem e Destino)
$dirAtual = str_replace('\\', '/', __DIR__);
$log[] = "Diretório do script ativo: $dirAtual";

if (strpos($dirAtual, '/releases/') !== false) {
    // Modo: Executando de dentro da pasta de release baixada pelo OTA
    $srcBase = dirname(__DIR__); 
    $dstBase = dirname(dirname(dirname(__DIR__))); 
    $log[] = "✅ Modo: Executando de dentro da release ativa.";
} else {
    // Modo: Fallback para desenvolvimento local ou deploy direto via Git na raiz
    $srcBase = str_replace('\\', '/', dirname(__DIR__));
    $dstBase = $srcBase;
    
    // Se estiver em subpasta SGIM-CLIENTE ou SGIM-VENDAS
    $baseName = basename($srcBase);
    if ($baseName === 'SGIM-CLIENTE' || $baseName === 'SGIM-VENDAS') {
        $dstBase = dirname($srcBase);
    }
    $log[] = "⚠️ Modo: Fallback de Git/Desenvolvimento.";
}

// Limpa barras invertidas para consistência
$srcBase = str_replace('\\', '/', $srcBase);
$dstBase = str_replace('\\', '/', $dstBase);

$log[] = "Origem (Arquivos Novos): $srcBase";
$log[] = "Destino (Raiz do Site): $dstBase";

if (!is_dir($srcBase)) {
    die("<h2 style='color:red'>Erro: Pasta de origem ($srcBase) não existe.</h2>");
}

if (!is_dir($dstBase)) {
    $candidatos = [
        '/home1/hg9a3205/public_html',
        '/home1/hg9a3205/public_html/sgim-iade',
    ];
    foreach ($candidatos as $c) {
        if (is_dir($c)) {
            $dstBase = $c;
            break;
        }
    }
}

if (!is_dir($dstBase)) {
    die("<h2 style='color:red'>Erro: Pasta de destino ($dstBase) não existe.</h2>");
}

// 3. Destinos para Cópia (Copia para a Raiz e para releases/current/ por redundância)
$alvosDestino = [$dstBase];
$dstCurrent = $dstBase . '/releases/current';
if (is_dir($dstCurrent) && str_replace('\\', '/', realpath($dstCurrent)) !== $srcBase) {
    $alvosDestino[] = $dstCurrent;
}

// 4. Arquivos Críticos para Sincronização Física na Raiz (Nova v1.5.9)
$arquivos = [
    'usuarios.php',
    'usuario_novo.php',
    'usuario_editar.php',
    'cadastro.php',
    'cadastro_publico.php',
    'includes/header.php',
    'src/Auth/AccessManager.php',
    'eventos.php',
    'evento_novo.php',
    'carteirinha_digital.php',
    'carteirinha_editor.php',
    'cargo_editar.php',
    'cargo_novo.php',
    'transacao_nova.php',
    'membro_novo.php',
    'rescue_rbac.php'
];

// 5. Executar Sincronização de Arquivos
foreach ($arquivos as $relPath) {
    $srcFile = $srcBase . '/' . $relPath;
    if (!file_exists($srcFile)) {
        $log[] = "❌ Arquivo de origem não encontrado: $relPath";
        $ok = false;
        continue;
    }

    foreach ($alvosDestino as $destFolder) {
        $dstFile = $destFolder . '/' . $relPath;
        
        // Garante subdiretórios
        $dstDir = dirname($dstFile);
        if (!is_dir($dstDir)) {
            if (!@mkdir($dstDir, 0755, true)) {
                $log[] = "❌ Falha ao criar pasta: $dstDir";
                $ok = false;
                continue;
            }
        }

        // Copia o arquivo
        if (@copy($srcFile, $dstFile)) {
            $log[] = "✅ Sincronizado: $relPath → $dstFile";
        } else {
            $log[] = "❌ Falha ao copiar: $relPath → $dstFile";
            $ok = false;
        }
    }
}

// 6. Conexão ao Banco de Dados e Forçar Versão v1.5.5
$dbPath = $dstBase . '/config/database.php';
$dbConfigPath = $dstBase . '/config/db_config.php';
$pdo = null;

if (file_exists($dbPath)) {
    try {
        require_once $dbPath;
    } catch (Throwable $e) {
        $log[] = "⚠️ Erro ao carregar database.php: " . $e->getMessage();
    }
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    $possible_db_configs = [
        $dstBase . '/config/db_config.php',
        $dstBase . '/db_config.php',
        $dstBase . '/../db_config.php',
        dirname($dstBase) . '/db_config.php',
    ];
    foreach ($possible_db_configs as $cfg_path) {
        if (file_exists($cfg_path)) {
            @include $cfg_path;
            if (isset($pdo) && $pdo instanceof PDO) {
                $log[] = "✅ Conexão direta estabelecida por inclusão de $cfg_path.";
                break;
            }
        }
    }
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    if (isset($host) && isset($dbname)) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user ?? '', $pass ?? '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $log[] = "✅ Conexão direta criada usando variáveis de banco importadas.";
        } catch (Throwable $e) {
            $log[] = "❌ Falha ao conectar usando variáveis de banco importadas: " . $e->getMessage();
        }
    }
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // A. Forçar versão do sistema
        $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('versao_sistema', '1.5.9') ON DUPLICATE KEY UPDATE valor = '1.5.9'");
        $stmt->execute();
        
        $stmt2 = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('system_version', '1.5.9') ON DUPLICATE KEY UPDATE valor = '1.5.9'");
        $stmt2->execute();
        $log[] = "✅ Banco de dados atualizado para v1.5.9.";

        // B. Garantir todas as permissões críticas no banco do cliente
        $pdo->exec("INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
                   ('usuarios', 'visualizar', 'Visualizar Usuários'),
                   ('usuarios', 'gerenciar', 'Gerenciar Usuários'),
                   ('membros', 'criar', 'Criar Membros'),
                   ('membros', 'cadastrar', 'Cadastrar Membros'),
                   ('financeiro', 'lancar', 'Lançar Transações'),
                   ('financeiro', 'cadastrar', 'Cadastrar Transações'),
                   ('carteirinhas', 'visualizar', 'Visualizar Carteirinhas'),
                   ('carteirinhas', 'gerenciar', 'Gerenciar Carteirinhas')");
        
        $pdo->exec("INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) 
                   SELECT 1, id FROM permissoes 
                   WHERE modulo IN ('usuarios', 'membros', 'financeiro', 'carteirinhas')");
        $log[] = "✅ Matriz de permissões críticas semeadas no banco do cliente.";

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
<title>SGIM Force Deployer - v1.5.9</title>
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
<h1>🔧 SGIM Force Deployer - v1.5.9 (Release Edition)</h1>

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
    <p class="ok">O resgate físico dos arquivos da release v1.5.5 para a raiz de produção foi concluído com sucesso!</p>
</div>
<?php endif; ?>
</body>
</html>
