<?php
namespace SGIM\Auth;

/**
 * AccessManager - O cérebro de permissões do SGIM ERP.
 * Responsável por validar ações de usuários e gerenciar o escopo de visão (Global vs Local).
 */
class AccessManager {
    private $pdo;
    private $userId;
    private $cargoId;
    private $congregacaoId;
    private $escopo; // 'global' ou 'local'
    private $permissoes = [];

    public function __construct($pdo, $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->bootstrapPermissions();
        $this->loadUserContext();
        $this->ensureBridges();
    }

    /**
     * Carrega os dados do usuário, seu cargo e suas permissões.
     */
    private function loadUserContext() {
        if (!$this->userId) return;

        // Busca dados do usuário e seu cargo
        $sql = "SELECT u.congregacao_id, u.cargo_id, c.escopo 
                FROM usuarios u 
                LEFT JOIN cargos c ON u.cargo_id = c.id 
                WHERE u.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->userId]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data) {
            $this->cargoId       = $data['cargo_id'];
            $this->congregacaoId = $data['congregacao_id'];
            $this->escopo        = $data['escopo'] ?? 'local';

            // 🛡️ CHAVE DE FERRO: Se for o admin raiz (ID 1) ou se o cargo for Admin Total (ID 1), forçamos o escopo global
            if ($this->userId == 1 || $this->cargoId == 1) {
                $this->escopo = 'global';
                $this->cargoId = 1;
            }

            $this->loadPermissions();
        }
    }

    private function loadPermissions() {
        if (!$this->cargoId) return;

        $sql = "SELECT p.modulo, p.acao 
                FROM permissoes p
                INNER JOIN cargo_permissoes cp ON p.id = cp.permissao_id
                WHERE cp.cargo_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->cargoId]);
        
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->permissoes[$row['modulo']][] = $row['acao'];
        }
    }

    /**
     * Verifica se o usuário tem permissão para uma ação específica.
     */
    public function forceGlobal() {
        $this->escopo = 'global';
        $this->cargo_id = 1;
        // Preenche com permissões básicas para garantir que o sidebar renderize tudo
        $modules = ['membros', 'financeiro', 'congregacoes', 'usuarios', 'comunicacao', 'relatorios', 'configuracoes', 'departamentos', 'eventos'];
        foreach ($modules as $m) {
            $this->permissoes[$m]['visualizar'] = true;
            $this->permissoes[$m]['gerenciar'] = true;
        }
    }

    public function can($modulo, $acao) {
        // SuperAdmin / Pastor Presidente sempre tem acesso
        if ($this->escopo === 'global') return true;

        if (!isset($this->permissoes[$modulo])) return false;
        return in_array($acao, $this->permissoes[$modulo]);
    }

    /**
     * Retorna o filtro SQL para congregação baseado no escopo do usuário.
     * Uso: $sql .= $access->getScopeFilter();
     */
    public function getScopeFilter($alias = '') {
        if ($this->escopo === 'global') return ""; // Vê tudo

        $prefix = $alias ? "{$alias}." : "";
        return " AND {$prefix}congregacao_id = " . (int)$this->congregacaoId;
    }

    public function getCongregacaoId() {
        return $this->congregacaoId;
    }

    public function isGlobal() {
        return $this->escopo === 'global';
    }

    /**
     * Garante de forma resiliente que todos os arquivos PHP do release atual
     * possuam suas pontes operacionais na raiz, eliminando erros 404 (OTA Safe).
     */
    /**
     * Auto-seeding de permissões críticas de usuários caso estejam ausentes no banco.
     */
    private function bootstrapPermissions() {
        try {
            // Garante que a permissão usuarios -> gerenciar existe na tabela
            $stmt = $this->pdo->query("SELECT id FROM permissoes WHERE modulo = 'usuarios' AND acao = 'gerenciar' LIMIT 1");
            if ($stmt && $stmt->fetch() === false) {
                // Tenta inserir as permissões padrão de usuários
                $sql = "INSERT IGNORE INTO permissoes (modulo, acao, descricao) VALUES 
                        ('usuarios', 'visualizar', 'Visualizar Usuários'),
                        ('usuarios', 'gerenciar', 'Gerenciar Usuários')";
                $this->pdo->exec($sql);
                
                // Também garante que o cargo_id = 1 (Admin Total) receba essa permissão na tabela cargo_permissoes
                $this->pdo->exec("INSERT IGNORE INTO cargo_permissoes (cargo_id, permissao_id) 
                                  SELECT 1, id FROM permissoes 
                                  WHERE modulo = 'usuarios'");
            }
        } catch (\Throwable $e) {
            // Silencioso
        }
    }

    /**
     * Garante de forma resiliente que todos os arquivos PHP do release atual
     * possuam suas pontes operacionais na raiz, eliminando erros 404 (OTA Safe).
     */
    private function ensureBridges() {
        try {
            $basePath = realpath(__DIR__ . '/../../') . '/';
            $releasesPath = $basePath . 'releases/current/';
            
            if (is_dir($releasesPath)) {
                $releaseFiles = glob($releasesPath . '*.php');
                if ($releaseFiles) {
                    foreach ($releaseFiles as $filePath) {
                        $fileName = basename($filePath);
                        
                        // Ignora arquivos que não devem ter ponte na raiz
                        if ($fileName === 'index.php' || $fileName === 'sw.js' || $fileName === 'cron_worker.php') {
                            continue;
                        }
                        
                        // 1. Ponte em Minúsculo
                        $targetBridgeLower = $basePath . strtolower($fileName);
                        $bridgeContentLower = "<?php\n// AUTO-PONTE GERADA PELO ACCESSMANAGER (MINUSCULO)\nrequire_once __DIR__ . '/releases/current/' . basename(__FILE__);\n";
                        if (!file_exists($targetBridgeLower) || strpos(@file_get_contents($targetBridgeLower), 'releases/current') === false) {
                            @file_put_contents($targetBridgeLower, $bridgeContentLower);
                        }
                        
                        // 2. Ponte com Primeira Letra Maiúscula (Compatibilidade Case-Insensitive)
                        $targetBridgeUpper = $basePath . ucfirst(strtolower($fileName));
                        $bridgeContentUpper = "<?php\n// AUTO-PONTE GERADA PELO ACCESSMANAGER (MAIUSCULO)\nrequire_once __DIR__ . '/releases/current/' . basename(__FILE__);\n";
                        if (!file_exists($targetBridgeUpper) || strpos(@file_get_contents($targetBridgeUpper), 'releases/current') === false) {
                            @file_put_contents($targetBridgeUpper, $bridgeContentUpper);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silencioso
        }
    }
}
