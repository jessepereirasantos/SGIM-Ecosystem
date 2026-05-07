<?php
/**
 * SGIM MASTER - OTA IDENTITY HELPER
 * Este arquivo é a ÚNICA fonte de verdade para descoberta de versão no Master.
 */

if (!function_exists('get_system_version')) {
    function get_system_version($force_refresh = false) {
        static $cached_version = null;
        
        if ($cached_version !== null && !$force_refresh) {
            return $cached_version;
        }

        $latest_file = dirname(__DIR__) . '/api/update/latest.json';
        
        if (file_exists($latest_file)) {
            // Evitar cache de leitura do sistema de arquivos (importante em HostGator)
            clearstatcache(true, $latest_file);
            $data = json_decode(file_get_contents($latest_file), true);
            
            if (isset($data['version'])) {
                $cached_version = $data['version'];
                return $cached_version;
            }
        }

        // Fallback de Segurança: Se o arquivo sumir, busca no banco apenas como última opção
        global $pdo;
        if (isset($pdo)) {
            try {
                return $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'system_version'")->fetchColumn() ?: '1.0.0';
            } catch (Exception $e) {
                return '1.0.0';
            }
        }

        return '1.0.0';
    }
}

/**
 * Retorna os detalhes completos da release ativa
 */
if (!function_exists('get_latest_release_info')) {
    function get_latest_release_info() {
        $latest_file = dirname(__DIR__) . '/api/update/latest.json';
        if (file_exists($latest_file)) {
            clearstatcache(true, $latest_file);
            return json_decode(file_get_contents($latest_file), true);
        }
        return null;
    }
}
