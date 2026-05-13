<?php
/**
 * SGIM OTA - ACTIVATION DRIVER INTERFACE
 * Define o contrato obrigatório para qualquer estratégia de ativação.
 */

namespace SGIM\OTA;

interface ActivationDriverInterface {
    /**
     * Valida se o ambiente suporta este driver.
     */
    public function validateEnvironment(): bool;

    /**
     * Prepara a ativação (backups, verificação de caminhos).
     */
    public function prepareActivation($versionPath, $manifest): bool;

    /**
     * Executa a troca de versão (Swap ou Overwrite).
     */
    public function activate($versionPath, $manifest): bool;

    /**
     * Reverte para o estado anterior em caso de falha.
     */
    public function rollback($previousVersionPath): bool;

    /**
     * Executa healthcheck específico da estratégia.
     */
    public function getHealthcheck(): array;
}
