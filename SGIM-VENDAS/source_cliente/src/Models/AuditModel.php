<?php
namespace App\Models;
use App\Core\Model;

class AuditModel extends Model {
    public function log($tabela, $id_referencia, $acao, $dados_antigos = null, $dados_novos = null) {
        $stmt = $this->db->prepare("INSERT INTO sistema_auditoria (usuario_id, tabela, id_referencia, acao, dados_antigos, dados_novos, ip) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $_SESSION['user_id'] ?? 0,
            $tabela,
            $id_referencia,
            $acao,
            $dados_antigos ? json_encode($dados_antigos) : null,
            $dados_novos ? json_encode($dados_novos) : null,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
    }

    public function getLogsByTable($tabela, $limit = 50) {
        $stmt = $this->db->prepare("SELECT * FROM sistema_auditoria WHERE tabela = ? ORDER BY data_hora DESC LIMIT ?");
        $stmt->bindValue(1, $tabela);
        $stmt->bindValue(2, (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
