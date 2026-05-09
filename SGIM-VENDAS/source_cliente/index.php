<?php
/**
 * Front Controller: SGIM Gateway
 */
require_once 'src/bootstrap.php';

use App\Core\Router;

if (!isset($_SESSION['user_id'])) {
    // Se não estiver logado, permite apenas login ou rotas públicas
    $route = $_GET['route'] ?? '';
    if ($route !== 'login') {
        header('Location: login.php');
        exit;
    }
}

$router = new Router($pdo);
$router->run();
?>
