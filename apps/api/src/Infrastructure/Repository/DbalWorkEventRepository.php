<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\WorkEventRepositoryInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final class DbalWorkEventRepository implements WorkEventRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function create(array $data): array
    {
        $row = $this->connection->fetchAssociative(
            'INSERT INTO work_events (user_id, client_id, type, start_at, duration_minutes, billable, notes)
             VALUES (:user_id, :client_id, :type, :start_at, :duration_minutes, :billable, :notes)
             RETURNING id, user_id, client_id, type, start_at, duration_minutes, billable, notes, created_at',
            [
                'user_id' => $data['user_id'],
                'client_id' => $data['client_id'],
                'type' => $data['type'],
                'start_at' => $data['start_at'],
                'duration_minutes' => $data['duration_minutes'],
                'billable' => $data['billable'],
                'notes' => $data['notes'],
            ]
        );

        return $row ?: [];
    }

    public function list(int $userId, ?DateTimeImmutable $from, ?DateTimeImmutable $to, ?int $clientId): array
    {
        $conditions = ['user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($from !== null) {
            $conditions[] = 'start_at >= :from';
            $params['from'] = $from->format('Y-m-d H:i:sP');
        }

        if ($to !== null) {
            $conditions[] = 'start_at <= :to';
            $params['to'] = $to->format('Y-m-d H:i:sP');
        }

        if ($clientId !== null) {
            $conditions[] = 'client_id = :client_id';
            $params['client_id'] = $clientId;
        }

        $sql = 'SELECT id, user_id, client_id, type, start_at, duration_minutes, billable, notes, created_at
                FROM work_events
                WHERE ' . implode(' AND ', $conditions) . '
                ORDER BY start_at DESC';

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function listForExport(
        int $userId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?int $clientId
    ): array
    {
        $conditions = ['we.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($from !== null) {
            $conditions[] = 'we.start_at >= :from';
            $params['from'] = $from->format('Y-m-d H:i:sP');
        }

        if ($to !== null) {
            $conditions[] = 'we.start_at <= :to';
            $params['to'] = $to->format('Y-m-d H:i:sP');
        }

        if ($clientId !== null) {
            $conditions[] = 'we.client_id = :client_id';
            $params['client_id'] = $clientId;
        }

        $sql = 'SELECT we.id, we.client_id, c.name AS client_name, we.type, we.start_at, we.duration_minutes, we.billable, we.notes, we.created_at
                FROM work_events we
                INNER JOIN clients c ON c.id = we.client_id AND c.user_id = we.user_id
                WHERE ' . implode(' AND ', $conditions) . '
                ORDER BY we.start_at DESC';

        return $this->connection->fetchAllAssociative($sql, $params);
    }
}
