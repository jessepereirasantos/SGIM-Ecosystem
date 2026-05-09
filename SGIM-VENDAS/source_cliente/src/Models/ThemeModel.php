<?php
namespace App\Models;
use App\Core\Model;
use PDO;
use Throwable;

class ThemeModel extends Model {
    public function getTheme() {
        try {
            $stmt = $this->db->query("SELECT * FROM configuracoes_tema WHERE id = 1");
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // Se a tabela não existe, cria ela agora (Auto-Healing)
            if (strpos($e->getMessage(), "1146") !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
                $this->createThemeTable();
                return $this->getDefaults();
            }
            return null;
        }
    }

    private function createThemeTable() {
        $sql = "CREATE TABLE IF NOT EXISTS configuracoes_tema (
            id INT PRIMARY KEY AUTO_INCREMENT,
            logo_url VARCHAR(255) DEFAULT '',
            cor_brand VARCHAR(20) DEFAULT '#ffc880',
            cor_brand_dark VARCHAR(20) DEFAULT '#d4a35d',
            cor_brand_light VARCHAR(20) DEFAULT '#ffd9a8',
            darkbg VARCHAR(20) DEFAULT '#050505',
            darkcard VARCHAR(20) DEFAULT '#121212',
            darkborder VARCHAR(20) DEFAULT '#1e1e1e',
            lightbg VARCHAR(20) DEFAULT '#F3F4F6',
            lightcard VARCHAR(20) DEFAULT '#FFFFFF',
            lightborder VARCHAR(20) DEFAULT '#E5E7EB',
            modo_padrao VARCHAR(10) DEFAULT 'dark',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $this->db->exec($sql);
        
        // Inserir o primeiro registro
        $defaults = $this->getDefaults();
        $this->db->exec("INSERT INTO configuracoes_tema (id, cor_brand, darkbg, darkcard, darkborder) 
                         VALUES (1, '#ffc880', '#050505', '#121212', '#1e1e1e')");
    }

    private function getDefaults() {
        return [
            'cor_brand' => '#ffc880', 'cor_brand_dark' => '#d4a35d', 'cor_brand_light' => '#ffd9a8',
            'darkbg' => '#050505', 'darkcard' => '#121212', 'darkborder' => '#1e1e1e',
            'lightbg' => '#F3F4F6', 'lightcard' => '#FFFFFF', 'lightborder' => '#E5E7EB',
            'modo_padrao' => 'dark', 'logo_url' => ''
        ];
    }

    public function updateTheme($data, $logo_url = null) {
        $params = array_values($data);
        $logo_sql = "";
        if ($logo_url) {
            $logo_sql = ", logo_url = ?";
            $params[] = $logo_url;
        }

        $sql = "UPDATE configuracoes_tema SET 
                cor_brand=?, cor_brand_dark=?, cor_brand_light=?, 
                darkbg=?, darkcard=?, darkborder=?, 
                lightbg=?, lightcard=?, lightborder=?, modo_padrao=? 
                $logo_sql WHERE id=1";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
