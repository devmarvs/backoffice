<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface MessageTemplateRepositoryInterface
{
    public function listByUser(int $userId): array;

    public function findByType(int $userId, string $type): ?array;

    public function upsert(int $userId, string $type, ?string $subject, string $body): array;
}
