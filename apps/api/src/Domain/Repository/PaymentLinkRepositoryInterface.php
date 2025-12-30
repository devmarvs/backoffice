<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface PaymentLinkRepositoryInterface
{
    public function create(array $data): array;

    public function findByInvoiceDraft(int $invoiceDraftId): ?array;
}
