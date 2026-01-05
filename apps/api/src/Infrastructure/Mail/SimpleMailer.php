<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SimpleMailer
{
    public function __construct(
        #[Autowire('%app.mail_from%')] private string $from,
        #[Autowire('%app.mail_reply_to%')] private string $replyTo,
        #[Autowire('%app.mail_bcc%')] private string $bcc
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->from !== '';
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

        $success = mail($to, $subject, $message, implode("\r\n", $headers));
        if (!$success) {
            throw new \RuntimeException('Failed to send email.');
        }
    }
}
