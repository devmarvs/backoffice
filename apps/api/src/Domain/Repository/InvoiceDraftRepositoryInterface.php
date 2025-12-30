<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface InvoiceDraftRepositoryInterface
{
    public function createDraft(int $userId, int $clientId, int $amountCents, string $currency): array;

    public function addLine(
        int $invoiceDraftId,
        ?int $workEventId,
        string $description,
        string $quantity,
        int $unitPriceCents
    ): array;

    public function updateAmount(int $invoiceDraftId): int;

    public function listByStatus(int $userId, string $status): array;

    public function listByDateRange(int $userId, ?string $from, ?string $to): array;

    public function findLines(int $invoiceDraftId): array;

    public function listWithLinesByDateRange(int $userId, ?string $from, ?string $to): array;

    public function bulkUpdateStatus(int $userId, array $ids, string $status): int;

    public function updateStatus(int $userId, int $invoiceDraftId, string $status): ?array;

    public function findById(int $userId, int $invoiceDraftId): ?array;
}
