<?php
/**
 * PUBLICAÇÃO DA RELEASE REAL v1.1.0
 */
require_once 'includes/system/OtaPublisher.php';

$publisher = new SGIM\OTA\OtaPublisher(__DIR__ . '/');
$zip = 'shared/system/workspace/v1.1.0.zip';

// Publicando versão 1.1.0 oficial
if ($publisher->publish($zip, '1.1.0', '1.0.0', 'stable')) {
    echo "RELEASE 1.1.0 PUBLISHED AND READY FOR CLIENTS";
} else {
    echo "PUBLICATION FAILED";
}
unlink(__FILE__);
