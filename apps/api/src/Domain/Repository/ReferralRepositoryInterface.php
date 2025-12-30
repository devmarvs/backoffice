<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface ReferralRepositoryInterface
{
    public function findCodeForUser(int $userId): ?array;

    public function findCode(string $code): ?array;

    public function createCode(int $userId, string $code): array;

    public function createReferral(int $referrerId, ?int $referredUserId, string $code, string $status): array;

    public function listByReferrer(int $referrerId): array;
}
