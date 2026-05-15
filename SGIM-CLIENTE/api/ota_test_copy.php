<?php
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$source = __DIR__ . '/../releases/v1.1.61/router_check.php';
$dest = __DIR__ . '/../router_check_root.php';

echo "Source: $source (" . (file_exists($source) ? 'Exists' : 'Missing') . ")\n";
echo "Dest: $dest\n";

if (copy($source, $dest)) {
    echo "SUCCESS: File copied to root.\n";
    echo "Root Check URL: " . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . str_replace('api/ota_test_copy.php', 'router_check_root.php', $_SERVER['REQUEST_URI']);
} else {
    $err = error_get_last();
    echo "FAILURE: " . $err['message'] . "\n";
}
