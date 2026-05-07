<?php
namespace App\Models;
use App\Core\Model;
use PDO;

class JobModel extends Model {
    public function add($tipo, $payload) {
        $stmt = $this->db->prepare("INSERT INTO sistema_jobs (tipo, payload) VALUES (?, ?)");
        return $stmt->execute([$tipo, json_encode($payload)]);
    }

    public function getPending($limit = 5) {
        $stmt = $this->db->prepare("SELECT * FROM sistema_jobs WHERE status = 'pendente' ORDER BY criado_em ASC LIMIT ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status, $tentativas = null) {
        $sql = "UPDATE sistema_jobs SET status = ?";
        $params = [$status];
        if ($tentativas !== null) {
            $sql .= ", tentativas = ?";
            $params[] = $tentativas;
        }
        $sql .= " WHERE id = ?";
        $params[] = $id;

        return $this->db->prepare($sql)->execute($params);
    }
}
