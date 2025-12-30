<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\InvoiceDraftRepositoryInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final class DbalInvoiceDraftRepository implements InvoiceDraftRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function createDraft(int $userId, int $clientId, int $amountCents, string $currency): array
    {
        $row = $this->connection->fetchAssociative(
            'INSERT INTO invoice_drafts (user_id, client_id, amount_cents, currency)
             VALUES (:user_id, :client_id, :amount_cents, :currency)
             RETURNING id, user_id, client_id, period_start, period_end, amount_cents, currency, status, created_at, updated_at',
            [
                'user_id' => $userId,
                'client_id' => $clientId,
                'amount_cents' => $amountCents,
                'currency' => $currency,
            ]
        );

        return $row ?: [];
    }

    public function addLine(
        int $invoiceDraftId,
        ?int $workEventId,
        string $description,
        string $quantity,
        int $unitPriceCents
    ): array
    {
        $row = $this->connection->fetchAssociative(
            'INSERT INTO invoice_lines (invoice_draft_id, work_event_id, description, quantity, unit_price_cents)
             VALUES (:invoice_draft_id, :work_event_id, :description, :quantity, :unit_price_cents)
             RETURNING id, invoice_draft_id, work_event_id, description, quantity, unit_price_cents',
            [
                'invoice_draft_id' => $invoiceDraftId,
                'work_event_id' => $workEventId,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price_cents' => $unitPriceCents,
            ]
        );

        return $row ?: [];
    }

    public function updateAmount(int $invoiceDraftId): int
    {
        $amount = $this->connection->fetchOne(
            'SELECT COALESCE(ROUND(SUM(quantity * unit_price_cents)), 0)
             FROM invoice_lines WHERE invoice_draft_id = :id',
            ['id' => $invoiceDraftId]
        );

        $amountCents = (int) $amount;

        $this->connection->executeStatement(
            'UPDATE invoice_drafts SET amount_cents = :amount_cents, updated_at = NOW() WHERE id = :id',
            ['amount_cents' => $amountCents, 'id' => $invoiceDraftId]
        );

        return $amountCents;
    }

    public function listByStatus(int $userId, string $status): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, user_id, client_id, period_start, period_end, amount_cents, currency, status, created_at, updated_at
             FROM invoice_drafts
             WHERE user_id = :user_id AND status = :status
             ORDER BY created_at DESC',
            ['user_id' => $userId, 'status' => $status]
        );
    }

    public function listByDateRange(int $userId, ?string $from, ?string $to): array
    {
        $conditions = ['user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($from !== null) {
            $conditions[] = 'created_at >= :from';
            $params['from'] = $from;
        }

        if ($to !== null) {
            $conditions[] = 'created_at <= :to';
            $params['to'] = $to;
        }

        $sql = 'SELECT id, user_id, client_id, period_start, period_end, amount_cents, currency, status, created_at, updated_at
                FROM invoice_drafts
                WHERE ' . implode(' AND ', $conditions) . '
                ORDER BY created_at DESC';

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function findLines(int $invoiceDraftId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, invoice_draft_id, work_event_id, description, quantity, unit_price_cents
             FROM invoice_lines
             WHERE invoice_draft_id = :id
             ORDER BY id ASC',
            ['id' => $invoiceDraftId]
        );
    }

    public function listWithLinesByDateRange(int $userId, ?string $from, ?string $to): array
    {
        $drafts = $this->listByDateRange($userId, $from, $to);

        foreach ($drafts as &$draft) {
            $draft['lines'] = $this->findLines((int) $draft['id']);
        }

        return $drafts;
    }

    public function bulkUpdateStatus(int $userId, array $ids, string $status): int
    {
        if ($ids === []) {
            return 0;
        }

        return $this->connection->executeStatement(
            'UPDATE invoice_drafts
             SET status = :status, updated_at = NOW()
             WHERE user_id = :user_id AND id = ANY(:ids)',
            [
                'status' => $status,
                'user_id' => $userId,
                'ids' => $ids,
            ],
            [
                'ids' => ArrayParameterType::INTEGER,
            ]
        );
    }

    public function updateStatus(int $userId, int $invoiceDraftId, string $status): ?array
    {
        $row = $this->connection->fetchAssociative(
            'UPDATE invoice_drafts
             SET status = :status, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id
             RETURNING id, user_id, client_id, period_start, period_end, amount_cents, currency, status, created_at, updated_at',
            ['status' => $status, 'id' => $invoiceDraftId, 'user_id' => $userId]
        );

        return $row ?: null;
    }

    public function findById(int $userId, int $invoiceDraftId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, client_id, period_start, period_end, amount_cents, currency, status, created_at, updated_at
             FROM invoice_drafts
             WHERE id = :id AND user_id = :user_id',
            ['id' => $invoiceDraftId, 'user_id' => $userId]
        );

        return $row ?: null;
    }
}
