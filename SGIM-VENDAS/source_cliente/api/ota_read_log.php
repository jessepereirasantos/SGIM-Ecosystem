<?php
header('Content-Type: text/plain');
$logFile = __DIR__ . '/../shared/system/logs/activation.log';
if (file_exists($logFile)) {
    echo "--- activation.log (LAST 50 LINES) ---\n";
    $lines = file($logFile);
    echo implode("", array_slice($lines, -50));
} else {
    echo "Log not found at: " . $logFile;
}
