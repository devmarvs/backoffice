<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\UserSettingsRepositoryInterface;
use Doctrine\DBAL\Connection;

final class DbalUserSettingsRepository implements UserSettingsRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function getByUserId(int $userId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT user_id, business_type, charge_model, default_rate_cents, default_currency,
                    follow_up_days, invoice_reminder_days, onboarding_note, created_at, updated_at
             FROM user_settings
             WHERE user_id = :user_id',
            ['user_id' => $userId]
        );

        return $row ?: null;
    }

    public function upsert(int $userId, array $data): array
    {
        $payload = array_merge(
            [
                'business_type' => null,
                'charge_model' => null,
                'default_rate_cents' => null,
                'default_currency' => 'EUR',
                'follow_up_days' => null,
                'invoice_reminder_days' => null,
                'onboarding_note' => null,
            ],
            $data
        );

        $row = $this->connection->fetchAssociative(
            'INSERT INTO user_settings (
                user_id,
                business_type,
                charge_model,
                default_rate_cents,
                default_currency,
                follow_up_days,
                invoice_reminder_days,
                onboarding_note
             ) VALUES (
                :user_id,
                :business_type,
                :charge_model,
                :default_rate_cents,
                :default_currency,
                :follow_up_days,
                :invoice_reminder_days,
                :onboarding_note
             )
             ON CONFLICT (user_id)
             DO UPDATE SET
                business_type = EXCLUDED.business_type,
                charge_model = EXCLUDED.charge_model,
                default_rate_cents = EXCLUDED.default_rate_cents,
                default_currency = EXCLUDED.default_currency,
                follow_up_days = EXCLUDED.follow_up_days,
                invoice_reminder_days = EXCLUDED.invoice_reminder_days,
                onboarding_note = EXCLUDED.onboarding_note,
                updated_at = NOW()
             RETURNING user_id, business_type, charge_model, default_rate_cents, default_currency,
                       follow_up_days, invoice_reminder_days, onboarding_note, created_at, updated_at',
            [
                'user_id' => $userId,
                'business_type' => $payload['business_type'],
                'charge_model' => $payload['charge_model'],
                'default_rate_cents' => $payload['default_rate_cents'],
                'default_currency' => $payload['default_currency'],
                'follow_up_days' => $payload['follow_up_days'],
                'invoice_reminder_days' => $payload['invoice_reminder_days'],
                'onboarding_note' => $payload['onboarding_note'],
            ]
        );

        return $row ?: [];
    }
}
