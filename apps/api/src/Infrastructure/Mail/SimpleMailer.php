<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SimpleMailer
{
    public function __construct(
        #[Autowire('%app.mail_from%')] private string $from,
        #[Autowire('%app.mail_reply_to%')] private string $replyTo,
        #[Autowire('%app.mail_bcc%')] private string $bcc,
        #[Autowire('%app.mail_transport%')] private string $transport,
        #[Autowire('%app.mail_smtp_host%')] private string $smtpHost,
        #[Autowire('%app.mail_smtp_port%')] private int $smtpPort,
        #[Autowire('%app.mail_smtp_user%')] private string $smtpUser,
        #[Autowire('%app.mail_smtp_password%')] private string $smtpPassword,
        #[Autowire('%app.mail_smtp_encryption%')] private string $smtpEncryption,
        #[Autowire('%app.mail_smtp_timeout%')] private int $smtpTimeout
    ) {
    }

    public function isConfigured(): bool
    {
        if ($this->from === '') {
            return false;
        }

        if (strtolower($this->transport) === 'smtp') {
            return $this->smtpHost !== '';
        }

        return true;
    }

    public function send(
        string $to,
        string $subject,
        string $body,
        ?string $attachmentName = null,
        ?string $attachmentContent = null,
        string $attachmentType = 'application/pdf'
    ): void {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Mail sender is not configured.');
        }

        $headerValues = [$to, $subject, $this->from, $this->replyTo, $this->bcc];
        foreach ($headerValues as $value) {
            if ($value !== '' && preg_match('/\r|\n/', $value) === 1) {
                throw new \RuntimeException('Invalid email header value.');
            }
        }

        $headers = [
            'From: ' . $this->from,
            'MIME-Version: 1.0',
        ];

        if ($this->replyTo !== '') {
            $headers[] = 'Reply-To: ' . $this->replyTo;
        }

        if ($this->bcc !== '') {
            $headers[] = 'Bcc: ' . $this->bcc;
        }

        $message = $body;

        if ($attachmentName !== null && $attachmentContent !== null) {
            $boundary = bin2hex(random_bytes(16));
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

            $message = '--' . $boundary . "\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $body . "\r\n";
            $message .= '--' . $boundary . "\r\n";
            $message .= 'Content-Type: ' . $attachmentType . '; name="' . $attachmentName . "\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= 'Content-Disposition: attachment; filename="' . $attachmentName . "\"\r\n\r\n";
            $message .= chunk_split(base64_encode($attachmentContent)) . "\r\n";
            $message .= '--' . $boundary . '--';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }

        $transport = strtolower($this->transport);
        if ($transport === 'smtp') {
            $smtp = new SmtpClient(
                $this->smtpHost,
                $this->smtpPort,
                $this->smtpUser,
                $this->smtpPassword,
                $this->smtpEncryption,
                $this->smtpTimeout
            );
            $smtp->send(
                $this->from,
                $to,
                $subject,
                implode("\r\n", $headers),
                $message
            );
            return;
        }

        $success = mail($to, $subject, $message, implode("\r\n", $headers));
        if (!$success) {
            throw new \RuntimeException('Failed to send email.');
        }
    }
}
