<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface PackageRepositoryInterface
{
    public function findFirstAvailable(int $userId, int $clientId): ?array;

    public function incrementUsedSessions(int $packageId): array;

    public function listByClient(int $userId, int $clientId): array;

    public function create(array $data): array;

    public function update(int $userId, int $packageId, array $data): ?array;

    public function findById(int $userId, int $packageId): ?array;
}
