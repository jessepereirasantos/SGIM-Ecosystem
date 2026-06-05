<?php
$host_clean = preg_replace('/:.*$/', '', $_SERVER['HTTP_HOST'] ?? '');
$remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
$is_local_env = (in_array($remote_addr, ['127.0.0.1', '::1']) || $host_clean === 'localhost' || $host_clean === '127.0.0.1');

echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NULL') . "\n";
echo "HOST_CLEAN: " . $host_clean . "\n";
echo "REMOTE_ADDR: " . $remote_addr . "\n";
echo "IS_LOCAL_ENV: " . ($is_local_env ? 'TRUE' : 'FALSE') . "\n";
