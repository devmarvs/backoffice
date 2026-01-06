<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Mail;

use App\Infrastructure\Mail\SimpleMailer;
use PHPUnit\Framework\TestCase;

final class SimpleMailerTest extends TestCase
{
    public function testSendRejectsHeaderInjection(): void
    {
        $mailer = new SimpleMailer(
            'sender@example.com',
            '',
            '',
            'mail',
            '',
            0,
            '',
            '',
            '',
            10
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid email header value.');

        $mailer->send('user@example.com', "Hello\r\nBcc: bad@example.com", 'Body');
    }
}
