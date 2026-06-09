<?php

namespace App\Services;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    public static function sendMail(
        string $to,
        string $subject,
        string $htmlBody,
        ?string $pdfContent = null,
        ?string $pdfName = 'attachment.pdf'
    ) {
        $mail = new PHPMailer(true);

        try {

            // SMTP
            $mail->isSMTP();
            $mail->Host       = env('MAIL_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('MAIL_USERNAME');
            $mail->Password   = env('MAIL_PASSWORD');
            $mail->SMTPSecure = env('MAIL_ENCRYPTION');
            $mail->Port       = env('MAIL_PORT');
            $mail->Timeout    = (int) env('MAIL_TIMEOUT', 15);

            // Sender
            $mail->setFrom(
                env('MAIL_FROM_ADDRESS'),
                env('MAIL_FROM_NAME')
            );

            // Receiver
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;

            // Optional PDF Attachment
            if ($pdfContent) {
                $mail->addStringAttachment(
                    $pdfContent,
                    $pdfName,
                    'base64',
                    'application/pdf'
                );
            }

            $mail->send();

            return [
                'ok' => true,
                'message' => 'Mail sent successfully'
            ];

        } catch (Exception $e) {
            return [
                'ok' => false,
                'message' => $mail->ErrorInfo
            ];
        }
    }
}
