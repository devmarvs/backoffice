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

    public function listSuggestions(int $userId, ?string $from, ?string $to): array
    {
        $conditions = ['c.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($from !== null) {
            $conditions[] = 'c.start_at >= :from';
            $params['from'] = $from;
        }

        if ($to !== null) {
            $conditions[] = 'c.start_at <= :to';
            $params['to'] = $to;
        }

        $sql = 'SELECT c.id, c.user_id, c.provider, c.provider_event_id, c.summary, c.start_at, c.end_at, c.raw_payload, c.created_at
                FROM calendar_events c
                LEFT JOIN work_events w
                  ON w.user_id = c.user_id
                 AND w.source_type = :source_type
                 AND w.source_id = c.id
                WHERE ' . implode(' AND ', $conditions) . ' AND w.id IS NULL
                ORDER BY c.start_at ASC';

        $params['source_type'] = 'calendar_event';

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function findById(int $userId, int $eventId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, provider, provider_event_id, summary, start_at, end_at, raw_payload, created_at
             FROM calendar_events
             WHERE id = :id AND user_id = :user_id',
            [
                'id' => $eventId,
                'user_id' => $userId,
            ]
        );

        return $row ?: null;
    }
}
