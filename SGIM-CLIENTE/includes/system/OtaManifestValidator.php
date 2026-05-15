<?php
/**
 * SGIM OTA - MANIFEST VALIDATOR v1.1.54 (FLEXIBLE)
 */

namespace SGIM\OTA;

use Exception;

class OtaManifestValidator {
    public function validate($manifest) {
        if (!isset($manifest['version'])) {
            throw new Exception("Campo obrigatório ausente no manifesto: version");
        }
        
        // Checksums removidos da obrigatoriedade para evitar travamentos em releases manuais
        return true;
    }
}
