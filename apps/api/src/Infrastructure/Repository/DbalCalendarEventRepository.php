<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\CalendarEventRepositoryInterface;
use Doctrine\DBAL\Connection;

final class DbalCalendarEventRepository implements CalendarEventRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function upsertEvents(int $userId, string $provider, array $events): int
    {
        $count = 0;

        foreach ($events as $event) {
            $this->connection->executeStatement(
                'INSERT INTO calendar_events (user_id, provider, provider_event_id, summary, start_at, end_at, raw_payload)
                 VALUES (:user_id, :provider, :provider_event_id, :summary, :start_at, :end_at, :raw_payload)
                 ON CONFLICT (user_id, provider, provider_event_id)
                 DO UPDATE SET
                    summary = EXCLUDED.summary,
                    start_at = EXCLUDED.start_at,
                    end_at = EXCLUDED.end_at,
                    raw_payload = EXCLUDED.raw_payload',
                [
                    'user_id' => $userId,
                    'provider' => $provider,
                    'provider_event_id' => $event['provider_event_id'],
                    'summary' => $event['summary'] ?? null,
                    'start_at' => $event['start_at'],
                    'end_at' => $event['end_at'] ?? null,
                    'raw_payload' => isset($event['raw_payload']) ? json_encode($event['raw_payload']) : null,
                ]
            );
            $count++;
        }

        return $count;
    }

    public function listByRange(int $userId, ?string $from, ?string $to): array
    {
        $conditions = ['user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($from !== null) {
            $conditions[] = 'start_at >= :from';
            $params['from'] = $from;
        }

        if ($to !== null) {
            $conditions[] = 'start_at <= :to';
            $params['to'] = $to;
        }

        $sql = 'SELECT id, user_id, provider, provider_event_id, summary, start_at, end_at, raw_payload, created_at
                FROM calendar_events
                WHERE ' . implode(' AND ', $conditions) . '
                ORDER BY start_at ASC';

        return $this->connection->fetchAllAssociative($sql, $params);
    }
}
