<?php
/**
 * SGIM OTA - BOOTSTRAP ROUTER (INDUSTRIAL MODEL)
 * O único ponto de entrada para todas as releases.
 */

namespace SGIM\OTA;

class Bootstrap {
    public static function route() {
        $baseDir = __DIR__ . '/';
        $stateFile = $baseDir . 'shared/system/state/current_release.txt';
        
        // 1. Ler Versão Ativa (Fall-through para 'base')
        $version = 'base';
        if (file_exists($stateFile)) {
            $version = trim(file_get_contents($stateFile));
        }

        // 2. Definir Caminho da Release
        $releasePath = $baseDir . 'releases/' . ($version === 'base' ? 'base' : 'v' . $version) . '/';

        // 3. Validar se a release existe
        if (!is_dir($releasePath)) {
            $releasePath = $baseDir . 'releases/base/';
        }

        return $releasePath;
    }
}

// Retorna o caminho da release para quem o incluir
return Bootstrap::route();
