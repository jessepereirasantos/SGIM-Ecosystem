<?php
/**
 * SGIM - SCRIPT DE RESGATE OPERACIONAL v1.1.37
 * Detecta pasta versionada encapsulada e promove arquivos para raiz operacional.
 * 
 * ⚠️  ESTE SCRIPT É DE USO ÚNICO E EMERGENCIAL.
 * ✅  Detecta raiz dinamicamente (sem caminhos fixos).
 * ✅  Valida origem, destino e permissões antes de agir.
 * ✅  Preserva dados críticos (db_config, uploads, storage).
 * ✅  Aplica Retenção Controlada (mantém atual + anterior).
 * ✅  Gera log forense completo.
 */

// Segurança mínima: apenas execução via CLI ou com token
$token_esperado = 'SGIM-RESCUE-2026';
if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== $token_esperado)) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Acesso negado. Informe ?token=SGIM-RESCUE-2026'], JSON_UNESCAPED_UNICODE));
}

header('Content-Type: application/json; charset=utf-8');
set_time_limit(300);

// --- 1. DETECÇÃO DINÂMICA DA RAIZ ---
$raiz = realpath(__DIR__) . DIRECTORY_SEPARATOR;
$logFile = $raiz . 'shared' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'rescue.log';
@mkdir(dirname($logFile), 0755, true);

function rescueLog($msg, $logFile) {
    @file_put_contents($logFile, '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

rescueLog("=== INÍCIO DO RESGATE === Raiz detectada: $raiz", $logFile);

// --- 2. BLINDAGEM DE DADOS (Prefixo Recursivo) ---
$protectedPaths = [
    'config/db_config.php',
    'uploads/',
    'storage/',
    'releases/',
    'backups/',
    '.installed',
    'shared/',
    'FORCAR_PROMOCAO.php',
    '.htaccess',
    '.cpanel.yml',
];

$isDryRun = false;
if (php_sapi_name() === 'cli') {
    foreach ($argv as $arg) {
        if ($arg === '--dry-run' || $arg === '-d') {
            $isDryRun = true;
            break;
        }
    }
} else {
    $isDryRun = (!empty($_GET['dry_run']) && $_GET['dry_run'] == '1');
}

// --- 3. DETECÇÃO DE PASTAS VERSIONADAS NA RAIZ ---
// Padrão: X.Y.Z (ex: 1.1.36, 1.2.0)
$versionPattern = '/^\d+\.\d+\.\d+$/';
$pastasDaRaiz = array_diff(scandir($raiz), ['.', '..']);
$pastasVersionadas = [];

foreach ($pastasDaRaiz as $item) {
    if (is_dir($raiz . $item) && preg_match($versionPattern, $item)) {
        $pastasVersionadas[] = $item;
    }
}

if (empty($pastasVersionadas)) {
    $msg = "Nenhuma pasta versionada detectada na raiz. Sistema já está na raiz operacional.";
    rescueLog($msg, $logFile);
    die(json_encode(['status' => 'noop', 'message' => $msg], JSON_UNESCAPED_UNICODE));
}

// Ordena semanticamente
usort($pastasVersionadas, 'version_compare');
rescueLog("Pastas versionadas detectadas: " . implode(', ', $pastasVersionadas), $logFile);

// --- 4. IDENTIFICAÇÃO DO ALVO DE PROMOÇÃO ---
// Promove sempre a versão mais recente detectada
$versaoAlvo = end($pastasVersionadas);
$origemDir  = $raiz . $versaoAlvo . DIRECTORY_SEPARATOR;

// Valida se a origem parece ser um core SGIM real
$indicesCoreValidos = ['config', 'includes', 'api', 'src'];
$origemValida = false;
foreach ($indicesCoreValidos as $indice) {
    if (is_dir($origemDir . $indice)) {
        $origemValida = true;
        break;
    }
}

if (!$origemValida) {
    $msg = "ABORTADO: A pasta '$versaoAlvo' não contém estrutura SGIM reconhecível.";
    rescueLog($msg, $logFile);
    die(json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE));
}

rescueLog("ALVO DE PROMOÇÃO: $origemDir", $logFile);

// --- 5. PROMOÇÃO DE ARQUIVOS PARA RAIZ OPERACIONAL ---
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($origemDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$copiados = 0;
$falhas   = [];
$pulados  = [];

foreach ($it as $arquivo) {
    $relativePath = substr($arquivo->getPathname(), strlen(realpath($origemDir)) + 1);
    $relativePath = str_replace('\\', '/', $relativePath);

    // Verifica proteção por prefixo
    $isProtected = false;
    foreach ($protectedPaths as $p) {
        if (strpos($relativePath, $p) === 0) {
            $isProtected = true;
            break;
        }
    }

    if ($isProtected) {
        $pulados[] = $relativePath;
        rescueLog("PROTEGIDO (skipped): $relativePath", $logFile);
        continue;
    }

    $destino    = $raiz . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $destinoDir = dirname($destino);

    if (!is_dir($destinoDir)) {
        @mkdir($destinoDir, 0755, true);
    }

    if (!is_writable($destinoDir)) {
        $falhas[] = $relativePath;
        rescueLog("SEM PERMISSÃO DE ESCRITA: $relativePath -> $destino", $logFile);
        continue;
    }

    if (copy($arquivo->getPathname(), $destino)) {
        $copiados++;
        rescueLog("PROMOTING: $relativePath -> $destino", $logFile);
    } else {
        $falhas[] = $relativePath;
        rescueLog("FALHA DE CÓPIA: $relativePath -> $destino", $logFile);
    }
}

rescueLog("=== PROMOÇÃO CONCLUÍDA === Copiados: $copiados | Falhas: " . count($falhas), $logFile);

// --- 6. RETENÇÃO CONTROLADA (Manter: atual + anterior) ---
// Ordena novamente e remove versões além das 2 mais recentes
$retencaoLog = [];

if (count($pastasVersionadas) > 2) {
    $paraRemover = array_slice($pastasVersionadas, 0, count($pastasVersionadas) - 2);

    foreach ($paraRemover as $versaoAntiga) {
        $dirAntigo = $raiz . $versaoAntiga;
        
        // Limpeza profunda da pasta versionada antiga
        $filesOld = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirAntigo, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($filesOld as $fo) {
            $fo->isDir() ? rmdir($fo->getRealPath()) : unlink($fo->getRealPath());
        }
        rmdir($dirAntigo);
        $retencaoLog[] = "REMOVIDA: $versaoAntiga/";
        rescueLog("RETENÇÃO: Pasta antiga removida: $versaoAntiga/", $logFile);
    }
}

// Mantém as 2 mais recentes como rollback
$mantidas = array_slice($pastasVersionadas, -2);
foreach ($mantidas as $m) {
    rescueLog("RETENÇÃO: Pasta mantida (rollback disponível): $m/", $logFile);
}

rescueLog("=== RESGATE FINALIZADO ===", $logFile);

// --- 7. RESPOSTA FORENSE FINAL ---
echo json_encode([
    'status'       => count($falhas) === 0 ? 'success' : 'partial',
    'veredito'     => count($falhas) === 0
        ? "✅ RESGATE CONCLUÍDO: $copiados arquivos promovidos para a raiz operacional."
        : "⚠️ RESGATE PARCIAL: $copiados promovidos, " . count($falhas) . " falhas.",
    'versao_resgatada' => $versaoAlvo,
    'raiz_operacional' => $raiz,
    'arquivos_promovidos' => $copiados,
    'arquivos_protegidos' => count($pulados),
    'falhas'       => $falhas,
    'retencao'     => [
        'removidas' => $retencaoLog,
        'mantidas'  => $mantidas,
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
