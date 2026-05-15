<?php
/**
 * SGIM Autoloader (PSR-4 Multi-Namespace)
 * Cobre: App\, SGIM\OTA\Drivers\, SGIM\OTA\
 */
spl_autoload_register(function ($class) {
    // Mapa de namespaces → diretórios (ordem: mais específico primeiro)
    $namespaces = [
        'SGIM\\OTA\\'          => dirname(__DIR__) . '/includes/system/',
        'SGIM\\Auth\\'         => __DIR__ . '/Auth/',
        'App\\'                => __DIR__ . '/',
    ];

    foreach ($namespaces as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

