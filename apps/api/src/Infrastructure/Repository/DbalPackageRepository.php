<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\PackageRepositoryInterface;
use Doctrine\DBAL\Connection;

final class DbalPackageRepository implements PackageRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function findFirstAvailable(int $userId, int $clientId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, client_id, title, total_sessions, used_sessions, price_cents, currency, created_at
             FROM packages
             WHERE user_id = :user_id AND client_id = :client_id AND used_sessions < total_sessions
             ORDER BY created_at ASC
             LIMIT 1',
            ['user_id' => $userId, 'client_id' => $clientId]
        );

        return $row ?: null;
    }

    public function incrementUsedSessions(int $packageId): array
    {
        $row = $this->connection->fetchAssociative(
            'UPDATE packages
             SET used_sessions = used_sessions + 1
             WHERE id = :id
             RETURNING id, user_id, client_id, title, total_sessions, used_sessions, price_cents, currency, created_at',
            ['id' => $packageId]
        );

        return $row ?: [];
    }

    public function listByClient(int $userId, int $clientId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, user_id, client_id, title, total_sessions, used_sessions, price_cents, currency, created_at
             FROM packages
             WHERE user_id = :user_id AND client_id = :client_id
             ORDER BY created_at DESC',
            ['user_id' => $userId, 'client_id' => $clientId]
        );
    }

    public function create(array $data): array
    {
        $row = $this->connection->fetchAssociative(
            'INSERT INTO packages (user_id, client_id, title, total_sessions, used_sessions, price_cents, currency)
             VALUES (:user_id, :client_id, :title, :total_sessions, :used_sessions, :price_cents, :currency)
             RETURNING id, user_id, client_id, title, total_sessions, used_sessions, price_cents, currency, created_at',
            [
                'user_id' => $data['user_id'],
                'client_id' => $data['client_id'],
                'title' => $data['title'],
                'total_sessions' => $data['total_sessions'],
                'used_sessions' => $data['used_sessions'] ?? 0,
                'price_cents' => $data['price_cents'],
                'currency' => $data['currency'],
            ]
        );

        return $row ?: [];
    }

    public function update(int $userId, int $packageId, array $data): ?array
    {
        $allowed = ['title', 'total_sessions', 'used_sessions', 'price_cents', 'currency'];
        $updates = [];
        $params = ['id' => $packageId, 'user_id' => $userId];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = sprintf('%s = :%s', $field, $field);
                $params[$field] = $data[$field];
            }
        }

        if ($updates === []) {
            return $this->findById($userId, $packageId);
        }

        $sql = 'UPDATE packages SET ' . implode(', ', $updates) . '
                WHERE id = :id AND user_id = :user_id
                RETURNING id, user_id, client_id, title, total_sessions, used_sessions, price_cents, currency, created_at';

        $row = $this->connection->fetchAssociative($sql, $params);

        return $row ?: null;
    }

    public function findById(int $userId, int $packageId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, client_id, title, total_sessions, used_sessions, price_cents, currency, created_at
             FROM packages
             WHERE id = :id AND user_id = :user_id',
            ['id' => $packageId, 'user_id' => $userId]
        );

        return $row ?: null;
    }
}
