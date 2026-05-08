<?php
/**
 * SGIM OTA - PROTECTED PATHS POLICY
 * Define as zonas de exclusão que nunca devem ser alteradas por uma atualização.
 */

namespace SGIM\OTA;

class ProtectedPathsPolicy {
    private static $protectedPaths = [
        'config/db.php',
        '.env',
        'shared/system/state/*',
        'shared/system/logs/*',
        'shared/system/audit/*',
        'shared/system/backups/*',
        'uploads/*',
        'assets/images/user/*',
        'sessions/*',
        'mercadopago/webhooks/*'
    ];

    /**
     * Verifica se um caminho está protegido.
     */
    public static function isProtected($path) {
        $path = ltrim($path, '/');
        foreach (self::$protectedPaths as $protected) {
            $pattern = str_replace(['*', '/'], ['.*', '\/'], $protected);
            if (preg_match('/^' . $pattern . '$/i', $path)) {
                return true;
            }
        }
        return false;
    }

    public static function getProtectedPaths() {
        return self::$protectedPaths;
    }
}
