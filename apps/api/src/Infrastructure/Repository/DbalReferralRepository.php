<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\ReferralRepositoryInterface;
use Doctrine\DBAL\Connection;

final class DbalReferralRepository implements ReferralRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function findCodeForUser(int $userId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, code, created_at
             FROM referral_codes
             WHERE user_id = :user_id',
            ['user_id' => $userId]
        );

        return $row ?: null;
    }

    public function findCode(string $code): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, user_id, code, created_at
             FROM referral_codes
             WHERE code = :code',
            ['code' => $code]
        );

        return $row ?: null;
    }

    public function createCode(int $userId, string $code): array
    {
        $row = $this->connection->fetchAssociative(
            'INSERT INTO referral_codes (user_id, code)
             VALUES (:user_id, :code)
             RETURNING id, user_id, code, created_at',
            ['user_id' => $userId, 'code' => $code]
        );

        return $row ?: [];
    }

    public function createReferral(int $referrerId, ?int $referredUserId, string $code, string $status): array
    {
        $row = $this->connection->fetchAssociative(
            'INSERT INTO referrals (referrer_id, referred_user_id, code, status)
             VALUES (:referrer_id, :referred_user_id, :code, :status)
             RETURNING id, referrer_id, referred_user_id, code, status, created_at',
            [
                'referrer_id' => $referrerId,
                'referred_user_id' => $referredUserId,
                'code' => $code,
                'status' => $status,
            ]
        );

        return $row ?: [];
    }

    public function listByReferrer(int $referrerId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, referrer_id, referred_user_id, code, status, created_at
             FROM referrals
             WHERE referrer_id = :referrer_id
             ORDER BY created_at DESC',
            ['referrer_id' => $referrerId]
        );
    }
}
