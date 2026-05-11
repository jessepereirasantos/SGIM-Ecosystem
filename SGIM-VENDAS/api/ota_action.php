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
        $logDir = dirname($logFile);
        if (!is_dir($logDir))
            mkdir($logDir, 0755, true);

        try {
            $manifestPath = dirname(__DIR__) . '/api/update/latest.json';
            $currentManifest = file_exists($manifestPath)
                ? json_decode(file_get_contents($manifestPath), true)
                : [];
            
            // Aceita a versão enviada pelo front-end (prompt do usuário) ou auto-incrementa se não for informada
            if (!empty($_POST['version'])) {
                $version = trim($_POST['version']);
            } else {
                $currentVersion = $currentManifest['version'] ?? '1.0.0';
                $parts = explode('.', $currentVersion);
                $parts[2] = (intval($parts[2] ?? 0)) + 1;   // Incrementa o patch
                $version = implode('.', $parts);
            }

            // Atualiza o manifesto atual imediatamente para garantir persistência inicial do número da versão
            $currentManifest['version'] = $version;
            
            // ✅ FIX: Garante que o diretório de destino dos pacotes exista
            $packagesDir = dirname(__DIR__) . '/api/update/packages/';
            if (!is_dir($packagesDir))
                mkdir($packagesDir, 0755, true);

            // ✅ FIX: Aponta diretamente para o código REAL validado (SGIM-CLIENTE raiz)
            $sourceDir = dirname(dirname(__DIR__)) . '/SGIM-CLIENTE/';
            if (!is_dir($sourceDir)) {
                // Fallback de segurança para source_cliente interno, se a estrutura real não existir
                $sourceDir = dirname(__DIR__) . '/source_cliente/';
            }
            
            if (!is_dir($sourceDir) || count(scandir($sourceDir)) <= 2) {
                throw new Exception("Diretório do cliente ($sourceDir) está vazio ou não existe.");
            }

            $tmpDir = dirname(__DIR__) . '/shared/system/workspace/';
            if (!is_dir($tmpDir))
                mkdir($tmpDir, 0755, true);

            $zipFile = $tmpDir . 'SGIM-CLIENTE-v' . $version . '.zip';
            if (file_exists($zipFile))
                unlink($zipFile);

            // 1. Gera o ZIP com a versão atualizada
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("Falha ao abrir criação do pacote ZIP OTA.");
            }

            $filesAdded = 0;
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen(realpath($sourceDir)) + 1);
                    $relativePath = str_replace('\\', '/', $relativePath);

                    // Exclui arquivos sensíveis e de ambiente
                    if (strpos($relativePath, 'config/db_config.php') !== false)
                        continue;
                    if (strpos($relativePath, 'config/db.php') !== false)
                        continue;
                    if (strpos($relativePath, '.installed') !== false)
                        continue;
                    if (strpos($relativePath, '.git') !== false)
                        continue;
                    if (strpos($relativePath, 'shared/') !== false)
                        continue;
                    if (strpos($relativePath, 'releases/') !== false)
                        continue;

                    $zip->addFile($filePath, $relativePath);
                    $filesAdded++;
                }
            }
            $zip->close();

            if (!file_exists($zipFile) || $filesAdded === 0) {
                throw new Exception("ZIP gerado está vazio ou não foi encontrado. Arquivos adicionados: $filesAdded");
            }

            // ✅ FIX: Gera SHA256 REAL do arquivo ZIP
            $sha256 = hash_file('sha256', $zipFile);

            // 2. Publicação via OtaPublisher (grava manifesto + move pacote)
            $publisher = new OtaPublisher(dirname(__DIR__) . '/');
            $published = $publisher->publish($zipFile, $version, "1.0.0", "stable");

            if (!$published) {
                throw new Exception("OtaPublisher retornou falha. Verifique shared/system/logs/publisher.log");
            }

            // 3. ✅ FIX: Enriquece manifesto com URL CORRETA (packages/) e SHA256 real
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $manifest['url'] = 'https://escolateologicaeloha.com.br/api/update/packages/' . ($manifest['package'] ?? '');
            $manifest['sha256'] = $sha256; // SHA256 real, não placeholder!
            $manifest['notes'] = "v{$version}: Release automática gerada em " . date('d/m/Y H:i');
            $manifest['release_date'] = date('Y-m-d');
            $manifest['files_count'] = $filesAdded;
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

            // 4. Telemetria
            $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
            if (!is_array($logs))
                $logs = [];
            $logs[] = [
                'timestamp' => date('c'),
                'acao' => 'publicar_release',
                'version_anterior' => $currentVersion,
                'version_nova' => $version,
                'manifest_path' => $manifestPath,
                'zip_path' => $zipFile,
                'zip_size_bytes' => filesize($zipFile),
                'files_no_zip' => $filesAdded,
                'sha256' => $sha256,
                'download_url' => $manifest['url'],
                'package_name' => ($manifest['package'] ?? 'N/A'),
                'status' => 'success',
            ];
            file_put_contents($logFile, json_encode(array_slice($logs, -100), JSON_PRETTY_PRINT), LOCK_EX);

            // 5. ✅ ATUALIZAÇÃO DO INSTALADOR COMERCIAL (sgim_master.zip)
            // Garante que novos clientes já nasçam na v1.1.5+
            $comZip = dirname(__DIR__) . '/downloads/sgim_master.zip';

            if (file_exists($comZip)) @unlink($comZip);
            $cZip = new ZipArchive();
            if ($cZip->open($comZip, ZipArchive::CREATE) === TRUE) {
                $cFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
                foreach ($cFiles as $cf) {
                    if (!$cf->isDir()) {
                        $cp = $cf->getRealPath();
                        $cr = substr($cp, strlen(realpath($sourceDir)) + 1);
                        $cr = str_replace('\\', '/', $cr);
                        if (strpos($cr, 'db_config.php') !== false || strpos($cr, '.installed') !== false || strpos($cr, '.git') !== false) continue;
                        $cZip->addFile($cp, $cr);
                    }
                }
                $cZip->close();
            }

            echo json_encode([
                'status'         => 'success',
                'message'        => "✅ Release v{$version} publicada e Instalador Comercial atualizado!",
                'version_nova'   => $version,
                'sha256'         => $sha256
            ]);

        } catch (Exception $e) {
            $logs = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
            if (!is_array($logs))
                $logs = [];
            $logs[] = [
                'timestamp' => date('c'),
                'acao' => 'publicar_release',
                'version' => ($version ?? 'unknown'),
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
            file_put_contents($logFile, json_encode(array_slice($logs, -100), JSON_PRETTY_PRINT), LOCK_EX);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'sincronizar_fonte':
        // ✅ RESOLVE FALHA 5 (versão corrigida)
        // NÃO tenta auto-detectar o path do SGIM-CLIENTE pois dirname() resolve
        // caminhos internos do cPanel incorretos no HostGator.
        try {
            $destDir = dirname(__DIR__) . '/source_cliente/';
            if (!is_dir($destDir))
                mkdir($destDir, 0755, true);

            // O admin informa o path absoluto via form (ou via config no banco)
            $clienteDir = trim($_POST['cliente_path'] ?? '');

            if (!$clienteDir) {
                // Tenta ler de configuração no banco
                require_once __DIR__ . '/../config/database.php';
                if (isset($pdo) && $pdo instanceof PDO) {
                    $s = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'sgim_cliente_path' LIMIT 1");
                    $clienteDir = $s ? trim($s->fetchColumn()) : '';
                }
            }

            if (!$clienteDir || !is_dir($clienteDir)) {
                echo json_encode([
                    'status' => 'error',
                    'message' => '⚠ Path do SGIM-CLIENTE não configurado. ' .
                        'Envie o campo "cliente_path" com o caminho absoluto no servidor, ' .
                        'ou cadastre a chave "sgim_cliente_path" na tabela configuracoes do banco. ' .
                        'Exemplo: /home/seuuser/public_html/sgim-cliente',
                    'destino' => $destDir,
                    'hint' => 'Use o cPanel File Manager para localizar o caminho absoluto do SGIM-CLIENTE.',
                ]);
                break;
            }
            $clienteDir = rtrim($clienteDir, '/') . '/';

            // Copia recursiva
            $count = 0;
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
                    if (strpos($relativePath, $ex) !== false) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip)
                    continue;

                $destPath = $destDir . $relativePath;
                if ($item->isDir()) {
                    if (!is_dir($destPath))
                        mkdir($destPath, 0755, true);
                } else {
                    copy($item->getPathname(), $destPath);
                    $count++;
                }
            }

            echo json_encode([
                'status' => 'success',
                'message' => "✅ source_cliente/ sincronizado. $count arquivos copiados.",
                'arquivos_copiados' => $count,
                'origem' => $clienteDir,
                'destino' => $destDir,
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'gerar_instalador':
        try {
            // ✅ FIX: Aponta diretamente para o código REAL validado (SGIM-CLIENTE raiz)
            $sourceDir = dirname(dirname(__DIR__)) . '/SGIM-CLIENTE/';
            if (!is_dir($sourceDir)) {
                $sourceDir = dirname(__DIR__) . '/source_cliente/'; 
            }
            $zipFile = dirname(__DIR__) . '/downloads/sgim_master.zip';

            if (file_exists($zipFile)) @unlink($zipFile);
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) throw new Exception("Falha ao criar ZIP.");
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($files as $f) {
                if (!$f->isDir()) {
                    $p = $f->getRealPath();
                    $r = str_replace('\\', '/', substr($p, strlen(realpath($sourceDir)) + 1));
                    if (strpos($r, 'db_config.php') !== false || strpos($r, '.installed') !== false) continue;
                    $zip->addFile($p, $r);
                }
            }
            $zip->close();
            echo json_encode(['status' => 'success', 'message' => '✅ Instalador Comercial (sgim_master.zip) reconstruído com sucesso!']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Ação inválida']);
        break;
}


