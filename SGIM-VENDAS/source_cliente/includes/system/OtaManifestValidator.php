<?php
/**
 * SGIM OTA - MANIFEST VALIDATOR
 * Validador rigoroso de integridade e requisitos de releases.
 */

namespace SGIM\OTA;

use Exception;

class OtaManifestValidator {
    private $requiredFields = [
        'version', 'release_id', 'checksums', 
        'changed_files', 'protected_paths', 
        'rollback_strategy', 'minimum_php'
    ];

    public function validate($manifest) {
        if (!is_array($manifest)) throw new Exception("Manifesto inválido (formato não é array).");

        // 1. Campos Obrigatórios
        foreach ($this->requiredFields as $field) {
            if (!isset($manifest[$field])) {
                throw new Exception("Campo obrigatório ausente no manifesto: $field");
            }
        }

        // 2. Compatibilidade PHP
        if (!version_compare(PHP_VERSION, $manifest['minimum_php'], '>=')) {
            throw new Exception("Incompatibilidade PHP: Versão mínima requerida {$manifest['minimum_php']}. Atual: " . PHP_VERSION);
        }

        // 3. Verificação de Colisões com Paths Protegidos
        foreach ($manifest['changed_files'] as $file) {
            if (ProtectedPathsPolicy::isProtected($file)) {
                throw new Exception("VIOLAÇÃO DE SEGURANÇA: A release tenta alterar um caminho protegido: $file");
            }
            
            // Anti Path Traversal
            if (strpos($file, '..') !== false || strpos($file, '/') === 0) {
                throw new Exception("VIOLAÇÃO DE SEGURANÇA: Caminho inválido detectado: $file");
            }
        }

        return true;
    }
}
