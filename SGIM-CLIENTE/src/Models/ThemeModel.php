<?php
namespace App\Models;
use App\Core\Model;
use PDO;

class ThemeModel extends Model {
    public function getTheme() {
        $stmt = $this->db->query("SELECT * FROM configuracoes_tema WHERE id = 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
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

    public function restoreDefaults() {
        $defaultTheme = [
            'cor_brand' => '#FFC107', 'cor_brand_dark' => '#D4AF37', 'cor_brand_light' => '#FFD54F',
            'darkbg' => '#050505', 'darkcard' => '#121212', 'darkborder' => '#1E1E1E',
            'lightbg' => '#F3F4F6', 'lightcard' => '#FFFFFF', 'lightborder' => '#E5E7EB',
            'modo_padrao' => 'dark'
        ];
        return $this->updateTheme($defaultTheme);
    }
}
