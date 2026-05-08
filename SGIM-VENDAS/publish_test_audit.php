<?php
/**
 * Script Temporário para Publicar a Release de Teste da Auditoria
 */
require_once 'includes/system/OtaPublisher.php';

$publisher = new SGIM\OTA\OtaPublisher(__DIR__ . '/');
$zip = 'shared/system/workspace/ota_test_v0.zip';

if ($publisher->publish($zip, '0.0.0', '1.0.0', 'test')) {
    echo "TEST RELEASE 0.0.0 PUBLISHED SUCCESSFULLY";
} else {
    echo "FAILED TO PUBLISH TEST RELEASE";
}
unlink(__FILE__); // Auto-destruição por segurança
