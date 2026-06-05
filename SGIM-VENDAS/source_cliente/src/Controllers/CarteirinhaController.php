<?php
namespace App\Controllers;

use PDO;
use Exception;

class CarteirinhaController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureTables();
    }

    /**
     * Cria as tabelas necessárias caso não existam (Auto-Migração OTA Safe)
     * Executado automaticamente no construtor para garantir compatibilidade em produção.
     */
    private function ensureTables() {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS carteirinha_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                fundo_url VARCHAR(255) DEFAULT NULL,
                logo_url VARCHAR(255) DEFAULT NULL,
                assinatura_url VARCHAR(255) DEFAULT NULL,
                elementos_json LONGTEXT NOT NULL DEFAULT '[]',
                status ENUM('Ativo', 'Inativo') DEFAULT 'Ativo',
                data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS carteirinha_cargos (
                template_id INT NOT NULL,
                cargo_id INT NOT NULL,
                PRIMARY KEY (template_id, cargo_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {
            // Silencioso — evita que a auto-migração quebre a página em caso de banco SQLite ou permissão restrita
            error_log("[SGIM] CarteirinhaController::ensureTables() falhou: " . $e->getMessage());
        }
    }

    /**
     * Retorna todos os templates ativos
     */
    public function getTemplates() {
        $sql = "SELECT t.*, GROUP_CONCAT(c.nome SEPARATOR ', ') as cargos_nomes
                FROM carteirinha_templates t
                LEFT JOIN carteirinha_cargos tc ON t.id = tc.template_id
                LEFT JOIN cargos c ON tc.cargo_id = c.id
                GROUP BY t.id
                ORDER BY t.data_cadastro DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna um template específico
     */
    public function getTemplate($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM carteirinha_templates WHERE id = ?");
        $stmt->execute([$id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($template) {
            // Busca os IDs dos cargos vinculados
            $stmtCargos = $this->pdo->prepare("SELECT cargo_id FROM carteirinha_cargos WHERE template_id = ?");
            $stmtCargos->execute([$id]);
            $template['cargos'] = $stmtCargos->fetchAll(PDO::FETCH_COLUMN);
        }

        return $template;
    }

    /**
     * Salva ou atualiza um template
     */
    public function saveTemplate($nome, $cargos, $fundoFile, $logoFile, $assinaturaFile, $elementosJson, $templateId = null) {
        if (empty($nome)) {
            return ['success' => false, 'message' => 'O nome do modelo é obrigatório.'];
        }

        $upload_dir = 'uploads/carteirinhas/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        try {
            $this->pdo->beginTransaction();

            $fundo_url = null;
            $logo_url = null;
            $assinatura_url = null;

            if ($templateId) {
                // Busca caminhos atuais para preservação/substituição
                $stmtExistente = $this->pdo->prepare("SELECT fundo_url, logo_url, assinatura_url FROM carteirinha_templates WHERE id = ?");
                $stmtExistente->execute([$templateId]);
                $existente = $stmtExistente->fetch(PDO::FETCH_ASSOC);
                if ($existente) {
                    $fundo_url = $existente['fundo_url'];
                    $logo_url = $existente['logo_url'];
                    $assinatura_url = $existente['assinatura_url'];
                }
            }

            // Upload do Fundo
            if ($fundoFile && $fundoFile['error'] === UPLOAD_ERR_OK) {
                $fundo_url = $this->handleUpload($fundoFile, $upload_dir, 'fundo_');
            }

            // Upload do Logo
            if ($logoFile && $logoFile['error'] === UPLOAD_ERR_OK) {
                $logo_url = $this->handleUpload($logoFile, $upload_dir, 'logo_');
            }

            // Upload da Assinatura
            if ($assinaturaFile && $assinaturaFile['error'] === UPLOAD_ERR_OK) {
                $assinatura_url = $this->handleUpload($assinaturaFile, $upload_dir, 'assinatura_');
            }

            if ($templateId) {
                // Atualiza
                $sql = "UPDATE carteirinha_templates 
                        SET nome = ?, fundo_url = ?, logo_url = ?, assinatura_url = ?, elementos_json = ? 
                        WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$nome, $fundo_url, $logo_url, $assinatura_url, $elementosJson, $templateId]);
                $id = $templateId;
            } else {
                // Insere
                $sql = "INSERT INTO carteirinha_templates (nome, fundo_url, logo_url, assinatura_url, elementos_json) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$nome, $fundo_url, $logo_url, $assinatura_url, $elementosJson]);
                $id = $this->pdo->lastInsertId();
            }

            // Atualiza os vínculos de cargos
            $stmtDelCargos = $this->pdo->prepare("DELETE FROM carteirinha_cargos WHERE template_id = ?");
            $stmtDelCargos->execute([$id]);

            if (!empty($cargos)) {
                $stmtInsCargos = $this->pdo->prepare("INSERT INTO carteirinha_cargos (template_id, cargo_id) VALUES (?, ?)");
                foreach ($cargos as $cargo_id) {
                    if (!empty($cargo_id)) {
                        $stmtInsCargos->execute([$id, $cargo_id]);
                    }
                }
            }

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Modelo salvo com sucesso!', 'id' => $id];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Erro ao salvar modelo: ' . $e->getMessage()];
        }
    }

    /**
     * Exclui um template do banco de dados e limpa os arquivos associados
     */
    public function deleteTemplate($id) {
        try {
            $this->pdo->beginTransaction();

            $stmtExistente = $this->pdo->prepare("SELECT fundo_url, logo_url, assinatura_url FROM carteirinha_templates WHERE id = ?");
            $stmtExistente->execute([$id]);
            $existente = $stmtExistente->fetch(PDO::FETCH_ASSOC);

            if ($existente) {
                // Remove arquivos físicos
                foreach (['fundo_url', 'logo_url', 'assinatura_url'] as $key) {
                    if ($existente[$key] && file_exists($existente[$key])) {
                        @unlink($existente[$key]);
                    }
                }
            }

            // Exclui vínculos de cargos
            $stmtDelCargos = $this->pdo->prepare("DELETE FROM carteirinha_cargos WHERE template_id = ?");
            $stmtDelCargos->execute([$id]);

            // Exclui template
            $stmtDelTemplate = $this->pdo->prepare("DELETE FROM carteirinha_templates WHERE id = ?");
            $stmtDelTemplate->execute([$id]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Modelo removido com sucesso.'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Erro ao remover modelo: ' . $e->getMessage()];
        }
    }

    /**
     * Obtém o template ideal para um membro baseado em seu cargo
     */
    public function getTemplateForMember($member_id) {
        // 1. Tenta obter o template vinculado ao cargo do membro
        $sqlCargo = "SELECT t.* FROM carteirinha_templates t
                     INNER JOIN carteirinha_cargos tc ON t.id = tc.template_id
                     INNER JOIN membros m ON m.cargo_id = tc.cargo_id
                     WHERE m.id = ? AND t.status = 'Ativo'
                     LIMIT 1";
        $stmt = $this->pdo->prepare($sqlCargo);
        $stmt->execute([$member_id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($template) {
            return $template;
        }

        // 2. Se não houver específico, busca o primeiro template que não está vinculado a cargos específicos (geral)
        $sqlGeral = "SELECT t.* FROM carteirinha_templates t
                     LEFT JOIN carteirinha_cargos tc ON t.id = tc.template_id
                     WHERE tc.template_id IS NULL AND t.status = 'Ativo'
                     LIMIT 1";
        $stmt = $this->pdo->query($sqlGeral);
        $templateGeral = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($templateGeral) {
            return $templateGeral;
        }

        // 3. Se ainda assim não houver, retorna o primeiro template ativo qualquer
        $sqlQualquer = "SELECT * FROM carteirinha_templates WHERE status = 'Ativo' LIMIT 1";
        $stmt = $this->pdo->query($sqlQualquer);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Trata o upload do arquivo
     */
    private function handleUpload($file, $upload_dir, $prefix) {
        $name = basename($file['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'])) {
            $new_filename = $prefix . uniqid() . '.' . $ext;
            $dest = $upload_dir . $new_filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                return $dest;
            }
        }
        return null;
    }
}
