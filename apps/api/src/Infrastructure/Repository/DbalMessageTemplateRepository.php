<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\MessageTemplateRepositoryInterface;
use Doctrine\DBAL\Connection;

final class DbalMessageTemplateRepository implements MessageTemplateRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function listByUser(int $userId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, user_id, type, subject, body, created_at, updated_at
             FROM message_templates
             WHERE user_id = :user_id
             ORDER BY type ASC',
            ['user_id' => $userId]
        );
    }

    public function findByType(int $userId, string $type): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, type, subject, body, created_at, updated_at
             FROM message_templates
             WHERE user_id = :user_id AND type = :type',
            ['user_id' => $userId, 'type' => $type]
        );

        return $row ?: null;
    }

    public function upsert(int $userId, string $type, ?string $subject, string $body): array
    {
        $row = $this->connection->fetchAssociative(
            'INSERT INTO message_templates (user_id, type, subject, body)
             VALUES (:user_id, :type, :subject, :body)
             ON CONFLICT (user_id, type)
             DO UPDATE SET subject = EXCLUDED.subject, body = EXCLUDED.body, updated_at = NOW()
             RETURNING id, user_id, type, subject, body, created_at, updated_at',
            [
                'user_id' => $userId,
                'type' => $type,
                'subject' => $subject,
                'body' => $body,
            ]
        );

        return $row ?: [];
    }
}
