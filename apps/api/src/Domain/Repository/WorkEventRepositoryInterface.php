<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use DateTimeImmutable;

interface WorkEventRepositoryInterface
{
    public function create(array $data): array;

    public function list(int $userId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, ?int $clientId): array;

    public function listForExport(
        int $userId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?int $clientId
    ): array;
}
