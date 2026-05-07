<?php
namespace App\Updater;

/**
 * VersionManager - Gerencia o estado de versão do cliente SGIM
 * Responsável por leitura/escrita do version.json e controle de integridade.
 */
class VersionManager {
    private $filePath;
    private $data;

    public function __construct() {
        $this->filePath = __DIR__ . '/../../version.json';
        $this->load();
    }

    /**
     * Carrega os dados do arquivo version.json
     */
    private function load() {
        if (file_exists($this->filePath)) {
            $content = file_get_contents($this->filePath);
            $this->data = json_decode($content, true);
        }

        // Fallback e Inicialização de novo padrão se necessário
        if (!$this->data || !is_array($this->data)) {
            $this->data = [
                'version' => '1.1.5',
                'channel' => 'stable',
                'checksum_installed' => '',
                'last_backup_at' => null,
                'last_check_at' => null,
                'status' => 'stable'
            ];
            $this->save();
        }
    }

    /**
     * Salva o estado atual no version.json
     */
    public function save() {
        return file_put_contents($this->filePath, json_encode($this->data, JSON_PRETTY_PRINT));
    }

    // Getters
    public function getVersion() { return $this->data['version'] ?? '1.0.0'; }
    public function getChannel() { return $this->data['channel'] ?? 'stable'; }
    public function getChecksum() { return $this->data['checksum_installed'] ?? ''; }
    public function getLastBackup() { return $this->data['last_backup_at']; }
    public function getLastCheck() { return $this->data['last_check_at']; }

    // Setters
    public function setVersion($version) { $this->data['version'] = $version; return $this; }
    public function setChannel($channel) { $this->data['channel'] = $channel; return $this; }
    public function setChecksum($hash) { $this->data['checksum_installed'] = $hash; return $this; }
    public function setLastBackup($timestamp = null) { 
        $this->data['last_backup_at'] = $timestamp ?: date('Y-m-d H:i:s'); 
        return $this; 
    }
    public function setLastCheck($timestamp = null) { 
        $this->data['last_check_at'] = $timestamp ?: date('Y-m-d H:i:s'); 
        return $this; 
    }

    /**
     * Retorna o objeto completo para exportação JSON
     */
    public function getData() {
        return $this->data;
    }
}
?>
