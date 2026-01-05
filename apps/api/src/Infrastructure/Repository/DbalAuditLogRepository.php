<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\AuditLogRepositoryInterface;
use Doctrine\DBAL\Connection;

final class DbalAuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function add(int $userId, string $action, string $entityType, ?int $entityId, array $metadata = []): void
    {
        $payload = $metadata !== [] ? json_encode($metadata) : null;
        if ($payload === false) {
            $payload = null;
        }

        $this->connection->executeStatement(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, metadata)
             VALUES (:user_id, :action, :entity_type, :entity_id, :metadata)',
            [
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'metadata' => $payload,
            ]
        );
    }

    public function listRecent(int $userId, int $limit): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, user_id, action, entity_type, entity_id, metadata, created_at
             FROM audit_logs
             WHERE user_id = :user_id
             ORDER BY created_at DESC
             LIMIT :limit',
            [
                'user_id' => $userId,
                'limit' => $limit,
            ],
            [
                'limit' => \PDO::PARAM_INT,
            ]
        );
    }
}
