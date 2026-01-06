<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

final class SmtpClient
{
    private $socket;

    public function __construct(
        private string $host,
        private int $port,
        private string $username,
        private string $password,
        private string $encryption,
        private int $timeout
    ) {
    }

    public function send(string $from, string $to, string $subject, string $headers, string $body): void
    {
        $this->connect();

        $hostname = 'localhost';
        $this->sendCommand('EHLO ' . $hostname, [250]);

        if (strtolower($this->encryption) === 'tls') {
            $this->sendCommand('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('SMTP TLS negotiation failed.');
            }
            $this->sendCommand('EHLO ' . $hostname, [250]);
        }

        if ($this->username !== '') {
            $this->sendCommand('AUTH LOGIN', [334]);
            $this->sendCommand(base64_encode($this->username), [334]);
            $this->sendCommand(base64_encode($this->password), [235]);
        }

        $this->sendCommand(sprintf('MAIL FROM:<%s>', $from), [250]);
        $this->sendCommand(sprintf('RCPT TO:<%s>', $to), [250, 251]);
        $this->sendCommand('DATA', [354]);

        $message = "To: {$to}\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= $headers . "\r\n\r\n";
        $message .= $body;

        $message = $this->escapeMessage($message);
        $this->write($message . "\r\n.\r\n");
        $this->readResponse([250]);

        $this->sendCommand('QUIT', [221]);
        fclose($this->socket);
    }

    private function connect(): void
    {
        $scheme = strtolower($this->encryption) === 'ssl' ? 'ssl://' : '';
        $this->socket = fsockopen(
            $scheme . $this->host,
            $this->port,
            $errorCode,
            $errorMessage,
            $this->timeout > 0 ? $this->timeout : 10
        );

        if (!is_resource($this->socket)) {
            throw new \RuntimeException(sprintf('SMTP connection failed: %s', $errorMessage));
        }

        stream_set_timeout($this->socket, $this->timeout > 0 ? $this->timeout : 10);
        $this->readResponse([220]);
    }

    private function sendCommand(string $command, array $expectedCodes): void
    {
        $this->write($command . "\r\n");
        $this->readResponse($expectedCodes);
    }

    private function readResponse(array $expectedCodes): void
    {
        $response = '';
        while (!feof($this->socket)) {
            $line = fgets($this->socket, 512);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] !== '-') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code === 0 || !in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException(sprintf('SMTP error: %s', trim($response)));
        }
    }

    private function write(string $data): void
    {
        if (fwrite($this->socket, $data) === false) {
            throw new \RuntimeException('Failed to write to SMTP socket.');
        }
    }

    private function escapeMessage(string $message): string
    {
        $lines = preg_split("/\r\n|\r|\n/", $message);
        $escaped = [];
        foreach ($lines as $line) {
            if ($line !== '' && $line[0] === '.') {
                $escaped[] = '.' . $line;
            } else {
                $escaped[] = $line;
            }
        }

        return implode("\r\n", $escaped);
    }
}
