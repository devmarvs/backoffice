<?php

declare(strict_types=1);

namespace App\Application\WorkEvent;

use App\Application\Autopilot\WorkEventAutopilot;
use App\Domain\Repository\UserSettingsRepositoryInterface;
use App\Domain\Repository\WorkEventRepositoryInterface;
use Doctrine\DBAL\Connection;

final class WorkEventService
{
    public function __construct(
        private Connection $connection,
        private WorkEventRepositoryInterface $workEvents,
        private WorkEventAutopilot $autopilot,
        private UserSettingsRepositoryInterface $settings
    ) {
    }

    public function log(array $data, ?int $rateCents, ?string $currency): array
    {
        $userSettings = $this->settings->getByUserId((int) $data['user_id']);
        $effectiveRate = $rateCents ?? ($userSettings['default_rate_cents'] ?? null);
        $effectiveCurrency = $currency ?? ($userSettings['default_currency'] ?? null);
        $followUpDays = $userSettings['follow_up_days'] ?? null;

        $this->connection->beginTransaction();

        try {
            $workEvent = $this->workEvents->create($data);
            $autopilot = $this->autopilot->handle(
                $workEvent,
                $effectiveRate !== null ? (int) $effectiveRate : null,
                $effectiveCurrency !== null ? (string) $effectiveCurrency : null,
                $followUpDays !== null ? (int) $followUpDays : null
            );

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        return [
            'work_event' => $workEvent,
            'autopilot' => $autopilot,
        ];
    }
}
