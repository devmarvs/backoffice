<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use DateTimeImmutable;

interface ReportingRepositoryInterface
{
    public function invoiceTotals(
        int $userId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?int $clientId
    ): array;

    public function workEventTotals(
        int $userId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?int $clientId
    ): array;
}
