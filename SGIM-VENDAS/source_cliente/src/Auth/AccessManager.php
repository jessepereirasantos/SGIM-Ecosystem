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
        $this->loadUserContext();
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

            // Se for Pastor Presidente (cargo_id especial ou escopo global), as permissões são virtuais
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
}
