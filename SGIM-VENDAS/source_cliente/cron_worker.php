<?php
/**
 * Cron Worker: SGIM Background Job Processor
 * Configure este arquivo no seu CRON (ex: * * * * * php cron_worker.php)
 */
require_once __DIR__ . '/src/bootstrap.php';
use App\Services\QueueService;

if (php_sapi_name() !== 'cli' && !isset($_GET['run_secret'])) {
    die("Acesso restrito.");
}

$service = new QueueService($pdo);
$logs = $service->process(10); // Processa 10 de cada vez

foreach ($logs as $log) {
    echo $log . "\n";
}
