<?php
namespace App\Models;
use App\Core\Model;
use PDO;

class NewsModel extends Model {
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM sistema_novidades ORDER BY data_lancamento DESC, id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markAsRead() {
        return $this->db->exec("UPDATE sistema_novidades SET visto = 1");
    }
}
