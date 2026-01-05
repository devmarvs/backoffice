<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface AuditLogRepositoryInterface
{
    public function add(int $userId, string $action, string $entityType, ?int $entityId, array $metadata = []): void;

    public function listRecent(int $userId, int $limit): array;
}
