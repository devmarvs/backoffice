<?php

declare(strict_types=1);

namespace App\Application\Templates;

use App\Domain\Repository\MessageTemplateRepositoryInterface;

final class TemplateService
{
    private const DEFAULTS = [
        'follow_up' => [
            'subject' => null,
            'body' => 'Follow up with {{client_name}} about your {{session_date}} session.',
        ],
        'payment_reminder' => [
            'subject' => null,
            'body' => 'Reminder: invoice #{{invoice_id}} for {{amount}} is ready when you are.',
        ],
        'no_show' => [
            'subject' => null,
            'body' => 'Sorry we missed each other today. Let me know if you want to reschedule.',
        ],
    ];

    public function __construct(private MessageTemplateRepositoryInterface $templates)
    {
    }

    public function listForUser(int $userId): array
    {
        $rows = $this->templates->listByUser($userId);
        $byType = [];

        foreach ($rows as $row) {
            $byType[$row['type']] = $row;
        }

        foreach (self::DEFAULTS as $type => $default) {
            if (!isset($byType[$type])) {
                $byType[$type] = [
                    'type' => $type,
                    'subject' => $default['subject'],
                    'body' => $default['body'],
                ];
            }
        }

        return array_values($byType);
    }

    public function resolve(int $userId, string $type, array $context = []): string
    {
        $template = $this->templates->findByType($userId, $type);
        $body = $template['body'] ?? (self::DEFAULTS[$type]['body'] ?? '');

        if ($body === '') {
            return '';
        }

        foreach ($context as $key => $value) {
            $body = str_replace(sprintf('{{%s}}', $key), (string) $value, $body);
        }

        return $body;
    }

    public function upsert(int $userId, string $type, ?string $subject, string $body): array
    {
        return $this->templates->upsert($userId, $type, $subject, $body);
    }
}
