<?php
/**
 * SGIM MASTER - MOTOR DE DISTRIBUIÇÃO UNIFICADA V5.0
 * Fonte Única: cliente-atualizacao/
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/EmailService.php';

// Sessão desativada temporariamente para evitar bloqueios de redirecionamento no servidor
session_start();

$v = trim($_POST['versao'] ?? '');
$novidades = trim($_POST['novidades'] ?? '');
$enviar_email = ($_POST['notificar_email'] ?? '0') == '1';

if (empty($v)) die(json_encode(['success' => false, 'message' => 'Versão é obrigatória.']));

$changelog = [
    'novidades' => array_filter(explode("\n", str_replace("\r", "", $novidades))),
    'data' => date('Y-m-d')
];

// 1. CONFIGURAÇÃO DE FONTES E DESTINOS
$base = realpath(__DIR__ . '/../');
$fonte = realpath($base . '/cliente-atualizacao'); // FONTE ÚNICA OFICIAL

if (!$fonte || !is_dir($fonte)) {
    die(json_encode(['success' => false, 'message' => 'Erro Crítico: Pasta fonte cliente-atualizacao não encontrada.']));
}

// 2. ATUALIZAR VERSION.JSON NA FONTE (Sincronização Atômica)
$version_file = $fonte . '/version.json';
$v_data = ['version' => $v, 'channel' => 'stable', 'date' => date('Y-m-d H:i:s')];
if (file_exists($version_file)) {
    $existing = json_decode(file_get_contents($version_file), true);
    $v_data = array_merge($existing, $v_data);
}
file_put_contents($version_file, json_encode($v_data, JSON_PRETTY_PRINT));

// 3. GERAÇÃO DOS PACOTES (OTA e INSTALAÇÃO NOVA)
$ota_zip_name = "sgim_ota_v" . str_replace('.', '_', $v) . ".zip";
$ota_zip_path = $base . '/updates/' . $ota_zip_name;
$install_zip_path = $base . '/downloads/sgim_master.zip'; // ZIP DE ATIVAÇÃO

@mkdir($base . '/updates', 0755, true);
@mkdir($base . '/downloads', 0755, true);

function generateZip($source, $destination) {
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) return false;

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($source) + 1);
            // Ignorar arquivos sensíveis
            if (strpos($relativePath, 'config/db.php') === false && strpos($relativePath, 'db_config.php') === false && strpos($relativePath, '.git') === false) {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    return $zip->close();
}

// Gerar OTA
if (!generateZip($fonte, $ota_zip_path)) {
    die(json_encode(['success' => false, 'message' => 'Falha ao gerar pacote OTA.']));
}

// Gerar ZIP de Ativação (IDÊNTICO AO OTA)
if (!copy($ota_zip_path, $install_zip_path)) {
    // Se falhar o copy, tenta gerar do zero para garantir
    generateZip($fonte, $install_zip_path);
}

$checksum_md5 = md5_file($ota_zip_path);

// 4. ATUALIZAR BANCO DE DADOS MASTER
try {
    $pdo->beginTransaction();

    // Registrar Versão para OTA
    $stmt = $pdo->prepare("INSERT INTO sistema_updates (versao, changelog_json, arquivo_zip, checksum_md5, data_publicacao) 
                           VALUES (?, ?, ?, ?, NOW()) 
                           ON DUPLICATE KEY UPDATE changelog_json=VALUES(changelog_json), arquivo_zip=VALUES(arquivo_zip), checksum_md5=VALUES(checksum_md5)");
    $stmt->execute([$v, json_encode($changelog), $ota_zip_name, $checksum_md5]);

    // Registrar Versão no Sistema Versões (v4.0)
    $stmt2 = $pdo->prepare("INSERT INTO sistema_versoes (versao, canal, path_zip, checksum_sha256, changelog, data_lancamento) 
                            VALUES (?, 'stable', ?, ?, ?, NOW()) 
                            ON DUPLICATE KEY UPDATE path_zip=VALUES(path_zip)");
    $stmt2->execute([$v, "updates/$ota_zip_name", hash_file('sha256', $ota_zip_path), json_encode($changelog)]);

    // Atualizar Versão Global
    $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'system_version'")->execute([$v]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die(json_encode(['success' => false, 'message' => 'Erro no banco: ' . $e->getMessage()]));
}

// 5. NOTIFICAÇÃO (Simples)
if ($enviar_email) {
    // Lógica de e-mail aqui (Opcional para o teste)
}

echo json_encode([
    'success' => true, 
    'message' => "SUCESSO: Versão $v publicada. OTA e ZIP de Ativação sincronizados!",
    'ota' => $ota_zip_name,
    'install' => 'sgim_master.zip'
]);
