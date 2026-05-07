<?php
/**
 * SGIM CLIENT - OTA IDENTITY HELPER
 * Fonte de verdade para a versão instalada no Cliente.
 */

if (!function_exists('get_local_version')) {
    function get_local_version($force_refresh = false) {
        static $cached_v = null;
        if ($cached_v !== null && !$force_refresh) return $cached_v;

        // Prioridade 1: version.json (atualizado pelo UpdaterCore)
        $v_file = dirname(__DIR__) . '/version.json';
        if (file_exists($v_file)) {
            clearstatcache(true, $v_file);
            $data = json_decode(file_get_contents($v_file), true);
            if (isset($data['version'])) {
                $cached_v = $data['version'];
                return $cached_v;
            }
        }

        // Prioridade 2: Banco de Dados (Legado)
        global $pdo;
        if (isset($pdo)) {
            try {
                $cached_v = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'versao_sistema'")->fetchColumn() ?: '1.1.0';
                return $cached_v;
            } catch (Exception $e) {
                return '1.1.0';
            }
        }

        return '1.1.0';
    }
}
