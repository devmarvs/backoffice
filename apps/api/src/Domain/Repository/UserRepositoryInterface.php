<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?array;

    public function findById(int $id): ?array;

    public function create(string $email, string $passwordHash): array;

    public function findAll(): array;
}
