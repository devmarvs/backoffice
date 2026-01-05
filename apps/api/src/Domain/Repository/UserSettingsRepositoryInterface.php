<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface UserSettingsRepositoryInterface
{
    public function getByUserId(int $userId): ?array;

    public function upsert(int $userId, array $data): array;

    public function updateLastReminderRun(int $userId, \DateTimeImmutable $runAt): void;
}
