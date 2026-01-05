<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface PaymentLinkRepositoryInterface
{
    public function create(array $data): array;

    public function findByInvoiceDraft(int $invoiceDraftId): ?array;

    public function deactivateByInvoiceDraft(int $invoiceDraftId): int;

    public function findByProviderId(string $provider, string $providerId): ?array;

    public function findWithInvoiceByProviderId(string $provider, string $providerId): ?array;

    public function updateStatus(int $paymentLinkId, string $status): ?array;
}
