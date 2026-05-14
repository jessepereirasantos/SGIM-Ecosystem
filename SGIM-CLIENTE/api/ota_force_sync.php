<?php
/**
 * SGIM OTA - FORCE DB SYNC v1.1.41
 * Sincroniza a versão do sistema no banco de dados.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $versao = '1.1.45'; // A versão que você quer que apareça
    
    // Tenta inserir ou atualizar a versão
    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('versao_sistema', ?) ON DUPLICATE KEY UPDATE valor = ?");
    $stmt->execute([$versao, $versao]);

    // Tenta também a chave 'system_version' por segurança
    $stmt2 = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('system_version', ?) ON DUPLICATE KEY UPDATE valor = ?");
    $stmt2->execute([$versao, $versao]);

    echo json_encode([
        "status" => "success",
        "message" => "Banco de dados sincronizado para v$versao. O painel deve atualizar agora.",
        "opcache_reset" => function_exists('opcache_reset') ? opcache_reset() : "N/A"
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
