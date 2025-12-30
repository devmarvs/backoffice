<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface ClientRepositoryInterface
{
    public function search(int $userId, ?string $query): array;

    public function create(int $userId, string $name, ?string $email, ?string $phone): array;

    public function findById(int $userId, int $clientId): ?array;

    public function update(int $userId, int $clientId, array $fields): ?array;
}
