<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\ClientRepositoryInterface;
use Doctrine\DBAL\Connection;

final class DbalClientRepository implements ClientRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function search(int $userId, ?string $query): array
    {
        $params = ['user_id' => $userId];
        $conditions = 'user_id = :user_id';

        if ($query !== null && $query !== '') {
            $params['query'] = '%' . $query . '%';
            $conditions .= ' AND (name ILIKE :query OR email ILIKE :query OR phone ILIKE :query)';
        }

        return $this->connection->fetchAllAssociative(
            'SELECT id, name, email, phone, created_at, updated_at
             FROM clients
             WHERE ' . $conditions . '
             ORDER BY name ASC',
            $params
        );
    }

    public function create(int $userId, string $name, ?string $email, ?string $phone): array
    {
        $row = $this->connection->fetchAssociative(
            'INSERT INTO clients (user_id, name, email, phone) VALUES (:user_id, :name, :email, :phone)
             RETURNING id, user_id, name, email, phone, created_at, updated_at',
            [
                'user_id' => $userId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
            ]
        );

        return $row ?: [];
    }

    public function findById(int $userId, int $clientId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, name, email, phone, created_at, updated_at
             FROM clients
             WHERE id = :id AND user_id = :user_id',
            ['id' => $clientId, 'user_id' => $userId]
        );

        return $row ?: null;
    }

    public function update(int $userId, int $clientId, array $fields): ?array
    {
        $allowed = ['name', 'email', 'phone'];
        $updates = [];
        $params = ['id' => $clientId, 'user_id' => $userId];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $updates[] = sprintf('%s = :%s', $key, $key);
                $params[$key] = $fields[$key];
            }
        }

        if ($updates === []) {
            return $this->findById($userId, $clientId);
        }

        $sql = 'UPDATE clients SET ' . implode(', ', $updates) . ', updated_at = NOW()
                WHERE id = :id AND user_id = :user_id
                RETURNING id, user_id, name, email, phone, created_at, updated_at';

        $row = $this->connection->fetchAssociative($sql, $params);

        return $row ?: null;
    }
}
