<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface BillingSubscriptionRepositoryInterface
{
    public function findByUser(int $userId, string $provider): ?array;

    public function findBySubscriptionId(string $provider, string $subscriptionId): ?array;

    public function upsert(int $userId, string $provider, array $data): array;
}
