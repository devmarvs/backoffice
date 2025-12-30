<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface CalendarEventRepositoryInterface
{
    public function upsertEvents(int $userId, string $provider, array $events): int;

    public function listByRange(int $userId, ?string $from, ?string $to): array;
}
