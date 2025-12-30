<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\UserRepositoryInterface;
use Doctrine\DBAL\Connection;

final class DbalUserRepository implements UserRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, email, password_hash, created_at FROM users WHERE email = :email',
            ['email' => $email]
        );

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, email, created_at FROM users WHERE id = :id',
            ['id' => $id]
        );

        return $row ?: null;
    }

    public function create(string $email, string $passwordHash): array
    {
        $row = $this->connection->fetchAssociative(
            'INSERT INTO users (email, password_hash) VALUES (:email, :password_hash) RETURNING id, email, created_at',
            ['email' => $email, 'password_hash' => $passwordHash]
        );

        return $row ?: [];
    }

    public function findAll(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, email, created_at FROM users ORDER BY id ASC'
        );
    }
}
