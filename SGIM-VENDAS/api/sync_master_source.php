<?php
/**
 * SGIM SYNC BRIDGE - Sincronizador de Origem (Auditoria Forense)
 * Garante que source_cliente/ seja idêntica ao SGIM-CLIENTE/ do Git.
 * Este script é externo ao motor OTA e serve apenas para unificar a fonte de arquivos.
 */

// Caminhos absolutos baseados na estrutura HostGator
$root = dirname(dirname(__DIR__));
$source = $root . '/SGIM-CLIENTE';
$target = $root . '/source_cliente';

// Proteção: Arquivos e pastas que NUNCA devem ser mexidos na sincronização
$ignoreList = [
    'db_config.php', 
    '.installed', 
    'config/db_config.php', 
    'api/update/packages/', 
    '.git',
    'backups'
];

echo "🛡️ SGIM BRIDGE: Iniciando Sincronização de Origem...\n";
echo "Origem (Git): $source\n";
echo "Destino (OTA Source): $target\n\n";

if (!is_dir($source)) {
    die("❌ ERRO CRÍTICO: Pasta de origem do Git não encontrada ($source). Sincronização Abortada.\n");
}

if (!is_dir($target)) {
    mkdir($target, 0755, true);
}

/**
 * Função de Sincronização Recursiva Atômica
 */
function syncDirectories($src, $dst, $ignore) {
    if (!is_dir($src)) return;
    if (!is_dir($dst)) mkdir($dst, 0755, true);

    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            // Filtro de Proteção
            if (in_array($file, $ignore)) {
                continue;
            }

            if (is_dir($srcPath)) {
                syncDirectories($srcPath, $dstPath, $ignore);
            } else {
                // Só copia se o arquivo for diferente ou não existir no destino
                if (!file_exists($dstPath) || md5_file($srcPath) !== md5_file($dstPath)) {
                    copy($srcPath, $dstPath);
                }
            }
        }
    }
    closedir($dir);
}

try {
    syncDirectories($source, $target, $ignoreList);

    // 5. Selo de Auditoria (build_info.json) - Injetado na raiz da distribuição
    $buildInfo = [
        'sync_at' => date('Y-m-d H:i:s'),
        'build_version' => '1.1.21-STABLE',
        'checksum_header' => substr(md5_file($source . '/includes/header.php'), 0, 10),
        'environment' => 'MASTER_PRODUCTION'
    ];
    file_put_contents($target . '/build_info.json', json_encode($buildInfo, JSON_PRETTY_PRINT));

    echo "✅ Sincronização Concluída com Sucesso.\n";
    echo "📊 Build ID: " . $buildInfo['checksum_header'] . "\n";

} catch (Exception $e) {
    echo "❌ ERRO NA SINCRONIZAÇÃO: " . $e->getMessage() . "\n";
}
