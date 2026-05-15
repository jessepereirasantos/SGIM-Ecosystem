<?php
/**
 * SGIM GATEWAY - BOOTSTRAP BRIDGE
 * Este arquivo atua como o ponto de entrada atômico para o sistema OTA.
 */
$activeRelease = __DIR__ . '/releases/current/index.php';

if (file_exists($activeRelease)) {
    // Carrega a versão atômica (v3.0+)
    require_once $activeRelease;
    exit;
}

// Fallback: Modo Legado (v1.0)
require_once 'src/bootstrap.php';

use App\Core\Router;

if (!isset($_SESSION['user_id'])) {
    $route = $_GET['route'] ?? '';
    if ($route !== 'login') {
        header('Location: login.php');
        exit;
    }
}

$router = new Router($pdo);
$router->run();
?>
