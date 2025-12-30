<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\FollowUpRepositoryInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final class DbalFollowUpRepository implements FollowUpRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function create(
        int $userId,
        int $clientId,
        DateTimeImmutable $dueAt,
        string $suggestedMessage,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): array
    {
        $row = $this->connection->fetchAssociative(
            'INSERT INTO follow_ups (user_id, client_id, due_at, suggested_message, source_type, source_id)
             VALUES (:user_id, :client_id, :due_at, :suggested_message, :source_type, :source_id)
             RETURNING id, user_id, client_id, due_at, suggested_message, status, source_type, source_id, created_at, updated_at',
            [
                'user_id' => $userId,
                'client_id' => $clientId,
                'due_at' => $dueAt->format('Y-m-d H:i:sP'),
                'suggested_message' => $suggestedMessage,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]
        );

        return $row ?: [];
    }

    public function listByStatus(int $userId, string $status): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, user_id, client_id, due_at, suggested_message, status, source_type, source_id, created_at, updated_at
             FROM follow_ups
             WHERE user_id = :user_id AND status = :status
             ORDER BY due_at ASC',
            ['user_id' => $userId, 'status' => $status]
        );
    }

    public function updateStatus(int $userId, int $followUpId, string $status): ?array
    {
        $row = $this->connection->fetchAssociative(
            'UPDATE follow_ups
             SET status = :status, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id
             RETURNING id, user_id, client_id, due_at, suggested_message, status, source_type, source_id, created_at, updated_at',
            ['status' => $status, 'id' => $followUpId, 'user_id' => $userId]
        );

        return $row ?: null;
    }

    public function findOpenBySource(int $userId, string $sourceType, int $sourceId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, client_id, due_at, suggested_message, status, source_type, source_id, created_at, updated_at
             FROM follow_ups
             WHERE user_id = :user_id AND status = :status AND source_type = :source_type AND source_id = :source_id',
            [
                'user_id' => $userId,
                'status' => 'open',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]
        );

        return $row ?: null;
    }
}
