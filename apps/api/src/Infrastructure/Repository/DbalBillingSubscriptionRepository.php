<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\BillingSubscriptionRepositoryInterface;
use Doctrine\DBAL\Connection;

final class DbalBillingSubscriptionRepository implements BillingSubscriptionRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function findByUser(int $userId, string $provider): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, provider, customer_id, subscription_id, status, current_period_end, plan,
                    created_at, updated_at
             FROM billing_subscriptions
             WHERE user_id = :user_id AND provider = :provider',
            ['user_id' => $userId, 'provider' => $provider]
        );

        return $row ?: null;
    }

    public function findBySubscriptionId(string $provider, string $subscriptionId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, provider, customer_id, subscription_id, status, current_period_end, plan,
                    created_at, updated_at
             FROM billing_subscriptions
             WHERE provider = :provider AND subscription_id = :subscription_id',
            ['provider' => $provider, 'subscription_id' => $subscriptionId]
        );

        return $row ?: null;
    }

    public function upsert(int $userId, string $provider, array $data): array
    {
        $payload = array_merge(
            [
                'customer_id' => null,
                'subscription_id' => null,
                'status' => 'pending',
                'current_period_end' => null,
                'plan' => null,
            ],
            $data
        );

        $row = $this->connection->fetchAssociative(
            'INSERT INTO billing_subscriptions (
                user_id, provider, customer_id, subscription_id, status, current_period_end, plan
             ) VALUES (
                :user_id, :provider, :customer_id, :subscription_id, :status, :current_period_end, :plan
             )
             ON CONFLICT (user_id, provider)
             DO UPDATE SET
                customer_id = EXCLUDED.customer_id,
                subscription_id = EXCLUDED.subscription_id,
                status = EXCLUDED.status,
                current_period_end = EXCLUDED.current_period_end,
                plan = EXCLUDED.plan,
                updated_at = NOW()
             RETURNING id, user_id, provider, customer_id, subscription_id, status, current_period_end, plan, created_at, updated_at',
            [
                'user_id' => $userId,
                'provider' => $provider,
                'customer_id' => $payload['customer_id'],
                'subscription_id' => $payload['subscription_id'],
                'status' => $payload['status'],
                'current_period_end' => $payload['current_period_end'],
                'plan' => $payload['plan'],
            ]
        );

        return $row ?: [];
    }
}
