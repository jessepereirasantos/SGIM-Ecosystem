<?php
/**
 * EmailService - SGIM-VENDAS
 * Serviço para disparo de notificações pós-venda via SMTP Real (Socket).
 */
class EmailService {
    public static function sendOrderApproved($to, $nome, $chave, $pedido_id) {
        $config = require __DIR__ . '/../config/smtp.php';
        
        $site_url = defined('SITE_URL') ? SITE_URL : 'https://' . ($_SERVER['HTTP_HOST'] ?? 'escolateologicaeloha.com.br');
        $subject = "🛡️ Sua Licença SGIM Master foi Liberada! [Pedido #$pedido_id]";
        
        $body = "
        <html>
        <body style='font-family: sans-serif; background-color: #f4f4f4; padding: 20px;'>
            <div style='max-width: 600px; margin: auto; background: #fff; padding: 30px; border-radius: 10px; border: 1px solid #ddd;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #FFC107;'>SGIM MASTER</h2>
                </div>
                <h3>Olá, $nome!</h3>
                <p>Temos o prazer de informar que seu pagamento foi confirmado com sucesso.</p>
                <div style='background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 5px solid #FFC107; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 14px; color: #666;'>Sua Chave de Ativação:</p>
                    <p style='margin: 10px 0 0 0; font-size: 24px; font-weight: bold; color: #333; letter-spacing: 1px;'>$chave</p>
                </div>
                <p>Acesse sua dashboard para baixar o sistema:</p>
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='$site_url/cliente/dashboard.php' style='background-color: #FFC107; color: #000; padding: 15px 25px; text-decoration: none; font-weight: bold; border-radius: 5px; display: inline-block;'>ACESSAR MINHA DASHBOARD</a>
                </div>
                <hr style='border: 0; border-top: 1px solid #eee; margin-top: 30px;'>
                <p style='text-align: center; font-size: 10px; color: #aaa;'>SGIM MASTER - Licença Vitalícia</p>
            </div>
        </body>
        </html>";

        return self::smtp_send($to, $subject, $body, $config);
    }

    public static function sendGeneric($to, $subject, $body_text, $config = null) {
        if (!$config) $config = require __DIR__ . '/../config/smtp.php';
        $body = "<html><body style='font-family:sans-serif;padding:20px;background:#f4f4f4;'>
            <div style='max-width:600px;margin:auto;background:#fff;padding:30px;border-radius:10px;border:1px solid #ddd;'>
                <h2 style='color:#FFC107;'>SGIM MASTER</h2>
                " . nl2br(htmlspecialchars($body_text)) . "
                <hr style='border:0;border-top:1px solid #eee;margin-top:20px;'>
                <p style='font-size:10px;color:#aaa;text-align:center;'>SGIM MASTER - Sistema de Gestão</p>
            </div></body></html>";
        return self::smtp_send($to, $subject, $body, $config);
    }

    public static function sendHtmlGeneric($to, $subject, $html_body, $config = null) {
        if (!$config) $config = require __DIR__ . '/../config/smtp.php';
        $body = "<html><body style='font-family:sans-serif;background:#f4f4f4;padding:20px;'>
            <div style='max-width:600px;margin:auto;background:#fff;padding:30px;border-radius:10px;border:1px solid #ddd;'>
                <h2 style='color:#FFC107;text-align:center;'>SGIM MASTER</h2>
                $html_body
                <hr style='border:0;border-top:1px solid #eee;margin-top:20px;'>
                <p style='font-size:10px;color:#aaa;text-align:center;'>SGIM MASTER - Sistema de Gestão</p>
            </div></body></html>";
        return self::smtp_send($to, $subject, $body, $config);
    }

    public static function sendPasswordReset($to, $nome, $nova_senha) {
        $config = require __DIR__ . '/../config/smtp.php';
        $subject = "🔑 Redefinição de Senha - SGIM Master";
        $body = "<html><body style='font-family:sans-serif;background:#f4f4f4;padding:20px;'>
            <div style='max-width:600px;margin:auto;background:#fff;padding:30px;border-radius:10px;border:1px solid #ddd;'>
                <h2 style='color:#FFC107;'>SGIM MASTER</h2>
                <h3>Olá, $nome!</h3>
                <p>Sua senha foi redefinida com sucesso.</p>
                <div style='background:#f9f9f9;padding:15px;border-radius:8px;border-left:5px solid #FFC107;margin:20px 0;'>
                    <p style='margin:0;font-size:13px;color:#666;'>Nova Senha Temporária:</p>
                    <p style='margin:8px 0 0;font-size:20px;font-weight:bold;color:#333;letter-spacing:2px;'>$nova_senha</p>
                </div>
                <p style='color:#e74c3c;font-size:12px;'><strong>Recomendado:</strong> Altere sua senha após o próximo login.</p>
            </div></body></html>";
        return self::smtp_send($to, $subject, $body, $config);
    }

    public static function smtp_send_public($to, $subject, $body, $config) {
        return self::smtp_send($to, $subject, $body, $config);
    }

    private static function smtp_send($to, $subject, $body, $config) {
        $timeout = 15;
        $newline = "\r\n";
        $http_host = $_SERVER['HTTP_HOST'] ?? 'escolateologicaeloha.com.br';
        
        try {
            $socket = fsockopen($config['host'], $config['port'], $errno, $errstr, $timeout);
            if (!$socket) throw new Exception("Falha na conexão: $errstr");

            $log = [];
            $getResponse = function($s) use (&$log) { 
                $r = ""; while($line = fgets($s, 512)) { $r .= $line; if(substr($line, 3, 1) == " ") break; } 
                $log[] = $r; return $r; 
            };

            $getResponse($socket); // 220
            fwrite($socket, "EHLO " . $http_host . $newline);
            $getResponse($socket);

            if ($config['secure'] === 'tls') {
                fwrite($socket, "STARTTLS" . $newline);
                $getResponse($socket); // 220
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception("Falha ao iniciar TLS");
                }
                fwrite($socket, "EHLO " . $http_host . $newline);
                $getResponse($socket);
            }

            // Auth
            fwrite($socket, "AUTH LOGIN" . $newline);
            $getResponse($socket);
            fwrite($socket, base64_encode($config['user']) . $newline);
            $getResponse($socket);
            fwrite($socket, base64_encode($config['pass']) . $newline);
            $getResponse($socket);

            // Mail
            fwrite($socket, "MAIL FROM: <" . $config['from_email'] . ">" . $newline);
            $getResponse($socket);
            fwrite($socket, "RCPT TO: <$to>" . $newline);
            $getResponse($socket);

            // Data
            fwrite($socket, "DATA" . $newline);
            $getResponse($socket);

            $head = "Date: " . date('r') . $newline;
            $head .= "To: $to" . $newline;
            $head .= "From: " . $config['from_name'] . " <" . $config['from_email'] . ">" . $newline;
            $head .= "Reply-To: " . $config['from_email'] . $newline;
            $head .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=" . $newline;
            $head .= "Message-ID: <" . md5(uniqid(time())) . "@" . $http_host . ">" . $newline;
            $head .= "X-Priority: 3" . $newline;
            $head .= "X-Mailer: SGIM-Mailer/1.0" . $newline;
            $head .= "MIME-Version: 1.0" . $newline;
            $head .= "Content-Type: text/html; charset=UTF-8" . $newline;
            $head .= "Content-Transfer-Encoding: 8bit" . $newline . $newline;

            fwrite($socket, $head . $body . $newline . "." . $newline);
            $getResponse($socket);

            fwrite($socket, "QUIT" . $newline);
            fclose($socket);
            return true;
        } catch (Exception $e) {
            error_log("EmailService Error: " . $e->getMessage());
            return false;
        }
    }
}
