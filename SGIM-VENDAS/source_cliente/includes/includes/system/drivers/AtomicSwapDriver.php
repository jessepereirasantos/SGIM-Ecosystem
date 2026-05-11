<?php
/**
 * SGIM OTA - ATOMIC SWAP DRIVER (EXPERIMENTAL)
 * Estratégia de ativação via Roteamento de Runtime (Bootstrap).
 * STATUS: CONGELADO / NÃO USAR EM PRODUÇÃO.
 */

namespace SGIM\OTA\Drivers;

use SGIM\OTA\Drivers\ActivationDriverInterface;

class AtomicSwapDriver implements ActivationDriverInterface {
    public function validateEnvironment(): bool {
        return false; // Desabilitado por segurança na Fase 10C
    }

    public function prepareActivation($versionPath, $manifest): bool {
        return true;
    }

    public function activate($versionPath, $manifest): bool {
        return false; // Bloqueado
    }

    public function rollback($previousVersionPath): bool {
        return true;
    }

    public function getHealthcheck(): array {
        return ["status" => "EXPERIMENTAL", "driver" => "AtomicSwapDriver"];
    }
}
