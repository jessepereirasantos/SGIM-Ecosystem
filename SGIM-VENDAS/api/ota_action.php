<?php
/**
 * SGIM MASTER - API DE AÇÕES OTA (Controller oficial)
 */
use SGIM\OTA\OtaPublisher;

ob_start();

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null) {
        $buffer = ob_get_clean();
        file_put_contents(
            __DIR__.'/fatal.log',
            print_r($error, true) . "\nBUFFER: " . $buffer,
            FILE_APPEND
        );
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'success' => false,
            'fatal'   => 'SHUTDOWN: ' . $error['message'],
            'buffer'  => $buffer,
            'file'    => $error['file'],
            'line'    => $error['line']
        ]);
        exit;
    }
});

session_start();
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
/*
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado: Admin session not found']);
    exit;
}
*/

require_once __DIR__ . '/../includes/system/OtaPublisher.php';

$acao = $_POST['acao'] ?? '';

switch ($acao) {
    case 'publicar_release':
        $logFile = dirname(__DIR__) . '/shared/system/logs/release_publish_log.json';
        $logDir = dirname($logFile);
        if (!is_dir($logDir))
            mkdir($logDir, 0755, true);

        try {
            $manifestPath = dirname(__DIR__) . '/api/update/latest.json';
            $currentManifest = file_exists($manifestPath)
                ? json_decode(file_get_contents($manifestPath), true)
                : [];
            $currentVersion = $currentManifest['version'] ?? '0.0.0';

            $version = trim($_POST['version'] ?? '');
            if (!$version || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                throw new Exception("Versão inválida: '$version'. Use formato 1.2.3");
            }

            $sourceDir = dirname(__DIR__) . '/source_cliente/';
            if (!is_dir($sourceDir)) {
                throw new Exception("Diretório source_cliente não encontrado em: $sourceDir");
            }

            $tmpDir = dirname(__DIR__) . '/shared/system/workspace/';
            if (!is_dir($tmpDir))
                mkdir($tmpDir, 0755, true);

            // ── CAMADA 1: ESTABILIZAÇÃO DO ZIP ──────────────────────────────
            $profilerLog = dirname(__DIR__) . '/shared/system/logs/zip_profiler.log';
            $startTime   = microtime(true);
            $startMem    = memory_get_usage();

            file_put_contents(
                $profilerLog,
                "[" . date('Y-m-d H:i:s') . "] START ZIP | RAM: " . round($startMem / 1024 / 1024, 2) . "MB\n"
            );

            // 1. Build em diretório isolado (evita auto-inclusão)
            $tempZip = sys_get_temp_dir() . '/ota_build_' . uniqid() . '.zip';

            $zip = new ZipArchive();
            if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("Falha ao criar ZIP temporário em: " . $tempZip);
            }

            // 2. Bloqueio na origem (não entra em pastas proibidas)
            $excludeDirs = ['shared', 'releases', 'workspace', 'downloads', 'backups', 'node_modules', 'vendor', '.git'];

            $directory = new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS);

            $filter = new RecursiveCallbackFilterIterator(
                $directory,
                function ($current, $key, $iterator) use ($excludeDirs) {
                    if ($current->isDir()) {
                        return !in_array($current->getFilename(), $excludeDirs);
                    }
                    return true;
                }
            );

            $files = new RecursiveIteratorIterator($filter);
            $files->setMaxDepth(10);

            $filesAdded = 0;
            $memLimit   = (int) ini_get('memory_limit') * 1024 * 1024;
            if ($memLimit <= 0) $memLimit = 256 * 1024 * 1024;

            foreach ($files as $file) {
                if (memory_get_usage() > ($memLimit * 0.8)) {
                    $zip->close();
                    @unlink($tempZip);
                    throw new Exception("FAIL_SAFE: Memória excedeu 80% do limite. Processo abortado.");
                }

                if (!$file->isDir()) {
                    $filePath     = $file->getRealPath();
                    $relativePath = str_replace('\\', '/', substr($filePath, strlen(realpath($sourceDir)) + 1));

                    $zip->addFile($filePath, $relativePath);
                    $filesAdded++;

                    if ($filesAdded % 50 === 0) {
                        $elapsed = microtime(true) - $startTime;
                        file_put_contents(
                            $profilerLog,
                            sprintf("[%d] RAM: %.2fMB | TIME: %.2fs | FILE: %s\n",
                                $filesAdded, memory_get_usage() / 1024 / 1024, $elapsed, $relativePath),
                            FILE_APPEND
                        );
                    }
                }
            }
            $zip->close();

            // 3. Move para workspace oficial
            $zipFile = $tmpDir . 'SGIM-CLIENTE-v' . $version . '.zip';
            if (file_exists($zipFile)) @unlink($zipFile);
            rename($tempZip, $zipFile);

            $totalTime = microtime(true) - $startTime;
            file_put_contents(
                $profilerLog,
                "[SUCCESS] Total: $filesAdded arquivos | Tempo: {$totalTime}s\n",
                FILE_APPEND
            );
            // ── FIM CAMADA 1 ────────────────────────────────────────────────

            if (!file_exists($zipFile) || $filesAdded === 0) {
                throw new Exception("ZIP gerado está vazio ou não foi encontrado. Arquivos adicionados: $filesAdded");
            }

            $sha256 = hash_file('sha256', $zipFile);

            $publisher = new OtaPublisher(dirname(__DIR__) . '/');
            $published  = $publisher->publish($zipFile, $version, "1.0.0", "stable");

            if (!$published) {
                throw new Exception("OtaPublisher retornou falha. Verifique shared/system/logs/publisher.log");
            }

            $manifest          = json_decode(file_get_contents($manifestPath), true);
            $manifest['url']   = 'https://escolateologicaeloha.com.br/api/update/packages/' . ($manifest['package'] ?? '');
            $manifest['sha256']        = $sha256;
            $manifest['notes']         = "v{$version}: Release automática gerada em " . date('d/m/Y H:i');
            $manifest['release_date']  = date('Y-m-d');
            $manifest['files_count']   = $filesAdded;
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

            $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
            if (!is_array($logs)) $logs = [];
            $logs[] = [
                'timestamp'       => date('c'),
                'acao'            => 'publicar_release',
                'version_anterior'=> $currentVersion,
                'version_nova'    => $version,
                'manifest_path'   => $manifestPath,
                'zip_path'        => $zipFile,
                'zip_size_bytes'  => filesize($zipFile),
                'files_no_zip'    => $filesAdded,
                'sha256'          => $sha256,
                'download_url'    => $manifest['url'],
                'package_name'    => ($manifest['package'] ?? 'N/A'),
                'status'          => 'success',
            ];
            file_put_contents($logFile, json_encode(array_slice($logs, -100), JSON_PRETTY_PRINT), LOCK_EX);

            // Camada 1 aplicada também no Instalador Comercial
            $comZip = dirname(__DIR__) . '/downloads/sgim_master.zip';
            if (file_exists($comZip)) @unlink($comZip);
            $tempComZip = sys_get_temp_dir() . '/installer_build_' . uniqid() . '.zip';
            $cZip = new ZipArchive();
            if ($cZip->open($tempComZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $cDirectory = new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS);
                $cFilter = new RecursiveCallbackFilterIterator(
                    $cDirectory,
                    function ($current, $key, $iterator) use ($excludeDirs) {
                        if ($current->isDir()) {
                            return !in_array($current->getFilename(), $excludeDirs);
                        }
                        return true;
                    }
                );
                $cFiles = new RecursiveIteratorIterator($cFilter);
                $cFiles->setMaxDepth(10);
                foreach ($cFiles as $cf) {
                    if (!$cf->isDir()) {
                        $cp = $cf->getRealPath();
                        $cr = str_replace('\\', '/', substr($cp, strlen(realpath($sourceDir)) + 1));
                        if (strpos($cr, 'db_config.php') !== false || strpos($cr, '.installed') !== false) continue;
                        $cZip->addFile($cp, $cr);
                    }
                }
                $cZip->close();
                rename($tempComZip, $comZip);
            }

            echo json_encode([
                'status'       => 'success',
                'message'      => "✅ Release v{$version} publicada e Instalador Comercial atualizado!",
                'version_nova' => $version,
                'sha256'       => $sha256,
                'files_count'  => $filesAdded,
            ]);

        } catch (Exception $e) {
            $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
            if (!is_array($logs)) $logs = [];
            $logs[] = [
                'timestamp' => date('c'),
                'acao'      => 'publicar_release',
                'version'   => ($version ?? 'unknown'),
                'status'    => 'error',
                'error'     => $e->getMessage(),
            ];
            file_put_contents($logFile, json_encode(array_slice($logs, -100), JSON_PRETTY_PRINT), LOCK_EX);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'sincronizar_fonte':
        try {
            $destDir = dirname(__DIR__) . '/source_cliente/';
            if (!is_dir($destDir))
                mkdir($destDir, 0755, true);

            $clienteDir = trim($_POST['cliente_path'] ?? '');

            if (!$clienteDir) {
                require_once __DIR__ . '/../config/database.php';
                if (isset($pdo) && $pdo instanceof PDO) {
                    $s = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'sgim_cliente_path' LIMIT 1");
                    $clienteDir = $s ? trim($s->fetchColumn()) : '';
                }
            }

            if (!$clienteDir || !is_dir($clienteDir)) {
                echo json_encode([
                    'status'  => 'error',
                    'message' => '⚠ Path do SGIM-CLIENTE não configurado. Envie o campo "cliente_path" com o caminho absoluto no servidor.',
                    'destino' => $destDir,
                    'hint'    => 'Use o cPanel File Manager para localizar o caminho absoluto do SGIM-CLIENTE.',
                ]);
                break;
            }
            $clienteDir = rtrim($clienteDir, '/') . '/';

            $count   = 0;
            $excluir = ['.git', '.installed', 'shared', 'releases', 'backups', 'db_config.php'];

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($clienteDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $relativePath = substr($item->getPathname(), strlen(realpath($clienteDir)) + 1);
                $relativePath = str_replace('\\', '/', $relativePath);

                $skip = false;
                foreach ($excluir as $ex) {
                    if (strpos($relativePath, $ex) !== false) { $skip = true; break; }
                }
                if ($skip) continue;

                $destPath = $destDir . $relativePath;
                if ($item->isDir()) {
                    if (!is_dir($destPath)) mkdir($destPath, 0755, true);
                } else {
                    copy($item->getPathname(), $destPath);
                    $count++;
                }
            }

            echo json_encode([
                'status'           => 'success',
                'message'          => "✅ source_cliente/ sincronizado. $count arquivos copiados.",
                'arquivos_copiados'=> $count,
                'origem'           => $clienteDir,
                'destino'          => $destDir,
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'gerar_instalador':
        try {
            $sourceDir = dirname(__DIR__) . '/source_cliente/';
            if (!is_dir($sourceDir)) {
                throw new Exception("Diretório source_cliente não encontrado.");
            }
            $zipFile = dirname(__DIR__) . '/downloads/sgim_master.zip';

            // Camada 1 aplicada
            $excludeDirs = ['shared', 'releases', 'workspace', 'downloads', 'backups', 'node_modules', 'vendor', '.git'];
            $tempZip     = sys_get_temp_dir() . '/installer_build_' . uniqid() . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("Falha ao criar ZIP temporário do instalador.");
            }

            $directory = new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS);
            $filter = new RecursiveCallbackFilterIterator(
                $directory,
                function ($current, $key, $iterator) use ($excludeDirs) {
                    if ($current->isDir()) {
                        return !in_array($current->getFilename(), $excludeDirs);
                    }
                    return true;
                }
            );
            $files = new RecursiveIteratorIterator($filter);
            $files->setMaxDepth(10);

            $count = 0;
            foreach ($files as $f) {
                if (!$f->isDir()) {
                    $p = $f->getRealPath();
                    $r = str_replace('\\', '/', substr($p, strlen(realpath($sourceDir)) + 1));
                    if (strpos($r, 'db_config.php') !== false || strpos($r, '.installed') !== false) continue;
                    $zip->addFile($p, $r);
                    $count++;
                }
            }
            $zip->close();
            if (file_exists($zipFile)) @unlink($zipFile);
            rename($tempZip, $zipFile);

            echo json_encode(['status' => 'success', 'message' => "✅ Instalador Comercial (sgim_master.zip) reconstruído com sucesso! ($count arquivos)"]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Ação inválida']);
} catch (Throwable $e) {
    $buffer = ob_get_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'  => 'error',
        'success' => false,
        'buffer'  => $buffer,
        'fatal'   => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
        'trace'   => $e->getTraceAsString()
    ]);
    exit;
}
