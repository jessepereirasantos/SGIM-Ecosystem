<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Acesso negado.");
}

$file = $_GET['file'] ?? '';
$backupDir = __DIR__ . '/backups/';
$filePath = $backupDir . basename($file);

$is_db_backup = strpos($file, 'backup_db_') === 0;
$is_full_backup = strpos($file, 'backup_FULL_') === 0;

if (file_exists($filePath) && ($is_db_backup || $is_full_backup) && (pathinfo($file, PATHINFO_EXTENSION) === 'sql' || pathinfo($file, PATHINFO_EXTENSION) === 'zip')) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
} else {
    die("Arquivo inválido ou não encontrado.");
}
