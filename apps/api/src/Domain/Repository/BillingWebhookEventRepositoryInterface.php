<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface BillingWebhookEventRepositoryInterface
{
    public function createIfNotExists(string $provider, string $eventId, string $eventType, array $payload): bool;

    public function markProcessing(string $provider, string $eventId): bool;

    public function markProcessed(string $provider, string $eventId): void;

    public function markFailed(string $provider, string $eventId, string $errorMessage): void;
}
