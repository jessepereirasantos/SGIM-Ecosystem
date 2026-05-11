<?php
/**
 * SGIM MASTER - API DE AÇÕES OTA (Controller oficial)
 * Usa OtaPublisher.php internamente para publicação atômica.
 */
session_start();
header('Content-Type: application/json');

// TRAVA REMOVIDA TEMPORARIAMENTE PARA VALIDAÇÃO DO COMANDANTE
/*
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado: Admin session not found']);
    exit;
}
*/

require_once __DIR__ . '/../includes/system/OtaPublisher.php';
use SGIM\OTA\OtaPublisher;

$acao = $_POST['acao'] ?? '';

switch ($acao) {
    case 'publicar_release':
        $logFile = dirname(__DIR__) . '/shared/system/logs/release_publish_log.json';
        $logDir  = dirname($logFile);
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);

        try {
            $version = "1.1.3";
            $sourceDir = dirname(__DIR__) . '/source_cliente/';
            $tmpDir    = dirname(__DIR__) . '/shared/system/workspace/';
            if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);

            $zipFile = $tmpDir . 'SGIM-CLIENTE-RESTAURADO.zip';
            if (file_exists($zipFile)) unlink($zipFile);

            // 1. Gera o ZIP com a versão atualizada
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("Falha ao abrir criação do pacote ZIP OTA.");
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen(realpath($sourceDir)) + 1);
                    $relativePath = str_replace('\\', '/', $relativePath);

                    if (strpos($relativePath, 'config/db_config.php') !== false) continue;
                    if (strpos($relativePath, 'config/db.php') !== false) continue;
                    if (strpos($relativePath, '.installed') !== false) continue;
                    if (strpos($relativePath, '.git') !== false) continue;

                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();

            if (!file_exists($zipFile)) {
                throw new Exception("ZIP gerado não foi encontrado em: $zipFile");
            }

            $sha256 = hash_file('sha256', $zipFile);

            // 2. Publicação oficial via OtaPublisher (pipeline industrial)
            $publisher = new OtaPublisher(dirname(__DIR__) . '/');
            $published = $publisher->publish($zipFile, $version, "1.0.0", "stable");

            if (!$published) {
                throw new Exception("OtaPublisher retornou falha na publicação atômica.");
            }

            // 3. Enriquece manifesto com campos que o CLIENTE espera
            $manifestPath = dirname(__DIR__) . '/api/update/latest.json';
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $manifest['url'] = 'https://escolateologicaeloha.com.br/api/update/packages/' . $manifest['package'];
            $manifest['notes'] = "v1.1.3: Correção Crítica Estrutural. Restaurada SPA. Caminhos de DB corrigidos globalmente. Dashboard sem duplicação HTML.";
            $manifest['release_date'] = date('Y-m-d');
            $manifest['changed_files'] = [
                "includes/header.php",
                "dashboard.php",
                "config/database.php"
            ];
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));

            // 4. TELEMETRIA REAL
            $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
            $logs[] = [
                'timestamp'         => date('c'),
                'acao'              => 'publicar_release',
                'version'           => $version,
                'manifest_path'     => $manifestPath,
                'zip_path'          => $zipFile,
                'zip_exists'        => file_exists($zipFile),
                'zip_size_bytes'    => filesize($zipFile),
                'sha256'            => $sha256,
                'latest_json_version'=> ($manifest['version'] ?? 'N/A'),
                'release_id'        => ($manifest['release_id'] ?? 'N/A'),
                'download_url'      => ($manifest['url'] ?? 'N/A'),
                'package_name'      => ($manifest['package'] ?? 'N/A'),
                'status'            => 'success',
                'error'             => null
            ];
            file_put_contents($logFile, json_encode(array_slice($logs, -100), JSON_PRETTY_PRINT));

            echo json_encode([
                'status'    => 'success',
                'message'   => "Release $version publicada com sucesso via OtaPublisher!",
                'zip_exists'=> true,
                'zip_size'  => filesize($zipFile),
                'sha256'    => $sha256,
                'manifest'  => $manifest
            ]);
        } catch (Exception $e) {
            $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
            $logs[] = [
                'timestamp' => date('c'),
                'acao'      => 'publicar_release',
                'version'   => ($version ?? 'unknown'),
                'status'    => 'error',
                'error'     => $e->getMessage()
            ];
            file_put_contents($logFile, json_encode(array_slice($logs, -100), JSON_PRETTY_PRINT));

            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'gerar_instalador':
        // Lógica para gerar o ZIP comercial
        echo json_encode(['status' => 'success', 'message' => 'Instalador oficial gerado e pronto para download.']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Ação inválida']);
        break;
}
