<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface IntegrationRepositoryInterface
{
    public function findByUserAndProvider(int $userId, string $provider): ?array;

    public function upsert(int $userId, string $provider, array $data): array;

    public function delete(int $userId, string $provider): void;
}
