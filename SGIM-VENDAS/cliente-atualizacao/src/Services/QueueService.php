<?php
namespace App\Services;
use App\Models\JobModel;
use Exception;

class QueueService {
    private $pdo;
    private $jobModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->jobModel = new JobModel($pdo);
    }

    public function process($limit = 5) {
        $jobs = $this->jobModel->getPending($limit);
        $results = [];

        foreach ($jobs as $job) {
            $this->jobModel->updateStatus($job['id'], 'processando');
            try {
                $status = $this->executeJob($job);
                if ($status) {
                    $this->jobModel->updateStatus($job['id'], 'concluido');
                    $results[] = "Job #{$job['id']} ({$job['tipo']}) concluso.";
                } else {
                    $this->markFailure($job);
                    $results[] = "Job #{$job['id']} falhou.";
                }
            } catch (Exception $e) {
                $this->markFailure($job);
                $results[] = "Erro Job #{$job['id']}: " . $e->getMessage();
            }
        }
        return $results;
    }

    private function executeJob($job) {
        $payload = json_decode($job['payload'], true);
        
        switch ($job['tipo']) {
            case 'email_massa':
                return $this->handleEmail($payload);
            case 'whatsapp_massa':
                return $this->handleWhatsApp($payload);
            default:
                return false;
        }
    }

    private function handleEmail($payload) {
        // Aproveita o EmailService legado ou MVC
        require_once __DIR__ . '/../../includes/EmailService.php';
        return \EmailService::sendHtmlGeneric($payload['para'], $payload['assunto'], $payload['mensagem']);
    }

    private function handleWhatsApp($payload) {
        // Futura integração com as APIs de WhatsApp que já temos
        // Por enquanto, apenas loga sucesso para teste de infraestrutura
        return true; 
    }

    private function markFailure($job) {
        $tentativas = $job['tentativas'] + 1;
        $status = ($tentativas >= 3) ? 'falha' : 'pendente'; 
        $this->jobModel->updateStatus($job['id'], $status, $tentativas);
    }
}
