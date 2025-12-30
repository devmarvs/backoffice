<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\IntegrationRepositoryInterface;
use Doctrine\DBAL\Connection;

final class DbalIntegrationRepository implements IntegrationRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function findByUserAndProvider(int $userId, string $provider): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, provider, access_token, refresh_token, expires_at, metadata, created_at, updated_at
             FROM integrations
             WHERE user_id = :user_id AND provider = :provider',
            ['user_id' => $userId, 'provider' => $provider]
        );

        return $row ?: null;
    }

    public function upsert(int $userId, string $provider, array $data): array
    {
        $row = $this->connection->fetchAssociative(
            'INSERT INTO integrations (user_id, provider, access_token, refresh_token, expires_at, metadata)
             VALUES (:user_id, :provider, :access_token, :refresh_token, :expires_at, :metadata)
             ON CONFLICT (user_id, provider)
             DO UPDATE SET
                access_token = EXCLUDED.access_token,
                refresh_token = EXCLUDED.refresh_token,
                expires_at = EXCLUDED.expires_at,
                metadata = EXCLUDED.metadata,
                updated_at = NOW()
             RETURNING id, user_id, provider, access_token, refresh_token, expires_at, metadata, created_at, updated_at',
            [
                'user_id' => $userId,
                'provider' => $provider,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            ]
        );

        return $row ?: [];
    }

    public function delete(int $userId, string $provider): void
    {
        $this->connection->executeStatement(
            'DELETE FROM integrations WHERE user_id = :user_id AND provider = :provider',
            ['user_id' => $userId, 'provider' => $provider]
        );
    }
}
