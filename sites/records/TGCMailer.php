<?php
/**
 * TGC 2026 - Serviço de Envio de E-mail
 * Wrapper para PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) throw new RuntimeException('Dependências de e-mail não estão instaladas.');
require_once $autoload;

class TGCMailer {
    private $fromName = 'TGC Admin';

    public function sendRecoveryEmail($toEmail, $pilotName, $newPin) {
        $mail = new PHPMailer(true);

        try {
            $this->configure($mail);
            $mail->addAddress($toEmail, $pilotName);

            $mail->isHTML(true);
            $mail->Subject = 'TGC - Recuperação de PIN';
            $escapedName = htmlspecialchars($pilotName, ENT_QUOTES, 'UTF-8');
            $escapedPin = htmlspecialchars($newPin, ENT_QUOTES, 'UTF-8');
            $body  = "<h1>Olá, {$escapedName}!</h1>";
            $body .= "<p>Você solicitou a recuperação do seu PIN de acesso.</p>";
            $body .= "<div style='background-color: #f3f4f6; padding: 20px; text-align: center; font-size: 24px; font-weight: bold; border: 2px solid #ef4444; color: #1f2937; margin: 20px 0;'>{$escapedPin}</div>";
            $body .= "<p>Utilize este código para registrar seus tempos.</p>";
            $body .= "<p><i>Boa sorte na pista!</i></p>";

            $mail->Body    = $body;
            $mail->AltBody = "Olá {$pilotName}. Seu novo PIN é: {$newPin}";
            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }

    public function sendAdminNotification($subject, $htmlBody, $textBody) {
        $mail = new PHPMailer(true);
        try {
            $this->configure($mail);
            $mail->addAddress('TGC_ADMIN_EMAIL');
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }

    private function configure($mail) {
        $host = projectEnvValue('TGC_SMTP_HOST');
        $username = projectEnvValue('TGC_SMTP_USERNAME');
        $password = projectEnvValue('TGC_SMTP_PASSWORD');
        $port = projectEnvValue('TGC_SMTP_PORT');
        if (!$host || !$username || !$password || !$port || !ctype_digit((string) $port)) {
            throw new RuntimeException('Configuração de e-mail ausente no ambiente.');
        }

        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAutoTLS = true;
        $mail->Port = (int) $port;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($username, $this->fromName);
    }
}
?>