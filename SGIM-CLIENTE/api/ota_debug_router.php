<?php
header('Content-Type: text/plain');
$file = __DIR__ . '/../.htaccess';
if (file_exists($file)) {
    echo "--- .htaccess CONTENT ---\n";
    echo file_get_contents($file);
} else {
    echo ".htaccess not found at: " . realpath($file);
}
