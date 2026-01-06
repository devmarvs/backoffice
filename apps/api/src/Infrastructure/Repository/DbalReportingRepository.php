<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\ReportingRepositoryInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final class DbalReportingRepository implements ReportingRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function invoiceTotals(
        int $userId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?int $clientId
    ): array {
        $conditions = ['user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($from !== null) {
            $conditions[] = 'created_at >= :from';
            $params['from'] = $from->format('Y-m-d H:i:sP');
        }

        if ($to !== null) {
            $conditions[] = 'created_at <= :to';
            $params['to'] = $to->format('Y-m-d H:i:sP');
        }

        if ($clientId !== null) {
            $conditions[] = 'client_id = :client_id';
            $params['client_id'] = $clientId;
        }

        $sql = 'SELECT status, currency, COUNT(*) AS count, COALESCE(SUM(amount_cents), 0) AS amount_cents
                FROM invoice_drafts
                WHERE ' . implode(' AND ', $conditions) . '
                GROUP BY status, currency';

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function workEventTotals(
        int $userId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?int $clientId
    ): array {
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

        $sql = 'SELECT billable, COUNT(*) AS count, COALESCE(SUM(duration_minutes), 0) AS minutes
                FROM work_events
                WHERE ' . implode(' AND ', $conditions) . '
                GROUP BY billable';

        return $this->connection->fetchAllAssociative($sql, $params);
    }
}
