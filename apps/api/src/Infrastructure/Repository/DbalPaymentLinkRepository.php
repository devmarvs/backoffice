<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\PaymentLinkRepositoryInterface;
use Doctrine\DBAL\Connection;

final class DbalPaymentLinkRepository implements PaymentLinkRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function create(array $data): array
    {
        $row = $this->connection->fetchAssociative(
            'INSERT INTO payment_links (invoice_draft_id, provider, provider_id, url, status)
             VALUES (:invoice_draft_id, :provider, :provider_id, :url, :status)
             RETURNING id, invoice_draft_id, provider, provider_id, url, status, created_at, updated_at',
            [
                'invoice_draft_id' => $data['invoice_draft_id'],
                'provider' => $data['provider'],
                'provider_id' => $data['provider_id'],
                'url' => $data['url'],
                'status' => $data['status'] ?? 'active',
            ]
        );

        return $row ?: [];
    }

    public function findByInvoiceDraft(int $invoiceDraftId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, invoice_draft_id, provider, provider_id, url, status, created_at, updated_at
             FROM payment_links
             WHERE invoice_draft_id = :invoice_draft_id
             ORDER BY created_at DESC
             LIMIT 1',
            ['invoice_draft_id' => $invoiceDraftId]
        );

        return $row ?: null;
    }

    public function deactivateByInvoiceDraft(int $invoiceDraftId): int
    {
        return $this->connection->executeStatement(
            'UPDATE payment_links
             SET status = :status, updated_at = NOW()
             WHERE invoice_draft_id = :invoice_draft_id AND status != :status',
            [
                'status' => 'inactive',
                'invoice_draft_id' => $invoiceDraftId,
            ]
        );
    }

    public function findByProviderId(string $provider, string $providerId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, invoice_draft_id, provider, provider_id, url, status, created_at, updated_at
             FROM payment_links
             WHERE provider = :provider AND provider_id = :provider_id
             ORDER BY created_at DESC
             LIMIT 1',
            [
                'provider' => $provider,
                'provider_id' => $providerId,
            ]
        );

        return $row ?: null;
    }

    public function findWithInvoiceByProviderId(string $provider, string $providerId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT pl.id, pl.invoice_draft_id, pl.provider, pl.provider_id, pl.url, pl.status,
                    pl.created_at, pl.updated_at, d.user_id, d.status AS invoice_status
             FROM payment_links pl
             INNER JOIN invoice_drafts d ON d.id = pl.invoice_draft_id
             WHERE pl.provider = :provider AND pl.provider_id = :provider_id
             ORDER BY pl.created_at DESC
             LIMIT 1',
            [
                'provider' => $provider,
                'provider_id' => $providerId,
            ]
        );

        return $row ?: null;
    }

    public function updateStatus(int $paymentLinkId, string $status): ?array
    {
        $row = $this->connection->fetchAssociative(
            'UPDATE payment_links
             SET status = :status, updated_at = NOW()
             WHERE id = :id
             RETURNING id, invoice_draft_id, provider, provider_id, url, status, created_at, updated_at',
            [
                'status' => $status,
                'id' => $paymentLinkId,
            ]
        );

        return $row ?: null;
    }
}
