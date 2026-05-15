/**
 * SGIM GATEWAY - BOOTSTRAP BRIDGE
 */
use App\Core\Router;
$activeRelease = __DIR__ . '/releases/current/index.php';

if (file_exists($activeRelease)) {
    // Carrega a versão atômica (v3.0+)
    require_once $activeRelease;
    exit;
}

// Fallback: Modo Legado (v1.0)
if (file_exists('src/bootstrap.php')) {
    require_once 'src/bootstrap.php';
    
    if (!isset($_SESSION['user_id'])) {
        $route = $_GET['route'] ?? '';
        if ($route !== 'login') {
            header('Location: login.php');
            exit;
        }
    }

    if (isset($pdo) && class_exists('App\Core\Router')) {
        $router = new Router($pdo);
        $router->run();
    } else {
        die("ERRO: O sistema não conseguiu carregar as dependências vitais.");
    }
} else {
    die("SISTEMA OFFLINE: Aguardando sincronização de arquivos.");
}
?>
