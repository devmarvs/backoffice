<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use DateTimeImmutable;

interface FollowUpRepositoryInterface
{
    public function create(
        int $userId,
        int $clientId,
        DateTimeImmutable $dueAt,
        string $suggestedMessage,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): array;

    public function listByStatus(int $userId, string $status): array;

    public function updateStatus(int $userId, int $followUpId, string $status): ?array;

    public function findOpenBySource(int $userId, string $sourceType, int $sourceId): ?array;

    public function listForExport(
        int $userId,
        ?string $status,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to
    ): array;

    public function findById(int $userId, int $followUpId): ?array;
}
