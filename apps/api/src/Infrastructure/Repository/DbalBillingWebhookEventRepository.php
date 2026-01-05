<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\BillingWebhookEventRepositoryInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final class DbalBillingWebhookEventRepository implements BillingWebhookEventRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function createIfNotExists(string $provider, string $eventId, string $eventType, array $payload): bool
    {
        $payloadJson = json_encode($payload);
        if ($payloadJson === false) {
            $payloadJson = json_encode([]);
        }

        $result = $this->connection->executeStatement(
            'INSERT INTO billing_webhook_events (provider, event_id, event_type, payload)
             VALUES (:provider, :event_id, :event_type, :payload)
             ON CONFLICT (provider, event_id) DO NOTHING',
            [
                'provider' => $provider,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'payload' => $payloadJson,
            ]
        );

        return $result > 0;
    }

    public function markProcessing(string $provider, string $eventId): bool
    {
        $result = $this->connection->executeStatement(
            'UPDATE billing_webhook_events
             SET status = :status, processed_at = NULL, error_message = NULL
             WHERE provider = :provider AND event_id = :event_id AND status IN (:statuses)',
            [
                'status' => 'processing',
                'provider' => $provider,
                'event_id' => $eventId,
                'statuses' => ['received', 'failed'],
            ],
            [
                'statuses' => ArrayParameterType::STRING,
            ]
        );

        return $result > 0;
    }

    public function markProcessed(string $provider, string $eventId): void
    {
        $this->connection->executeStatement(
            'UPDATE billing_webhook_events
             SET status = :status, processed_at = NOW(), error_message = NULL
             WHERE provider = :provider AND event_id = :event_id',
            [
                'status' => 'processed',
                'provider' => $provider,
                'event_id' => $eventId,
            ]
        );
    }

    public function markFailed(string $provider, string $eventId, string $errorMessage): void
    {
        $this->connection->executeStatement(
            'UPDATE billing_webhook_events
             SET status = :status, processed_at = NOW(), error_message = :error_message
             WHERE provider = :provider AND event_id = :event_id',
            [
                'status' => 'failed',
                'provider' => $provider,
                'event_id' => $eventId,
                'error_message' => $errorMessage,
            ]
        );
    }
}
