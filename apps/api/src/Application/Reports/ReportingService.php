<?php

declare(strict_types=1);

namespace App\Application\Reports;

use App\Domain\Enum\InvoiceDraftStatus;
use App\Domain\Repository\ReportingRepositoryInterface;
use DateTimeImmutable;

final class ReportingService
{
    public function __construct(private ReportingRepositoryInterface $reports)
    {
    }

    public function summary(
        int $userId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?int $clientId
    ): array {
        $invoiceTotals = [];
        foreach (InvoiceDraftStatus::cases() as $status) {
            $invoiceTotals[$status->value] = [
                'count' => 0,
                'amounts' => [],
            ];
        }

        $invoiceRows = $this->reports->invoiceTotals($userId, $from, $to, $clientId);
        foreach ($invoiceRows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (!isset($invoiceTotals[$status])) {
                continue;
            }

            $currency = strtoupper((string) ($row['currency'] ?? ''));
            if ($currency === '') {
                continue;
            }

            $count = (int) ($row['count'] ?? 0);
            $amount = (int) ($row['amount_cents'] ?? 0);

            $invoiceTotals[$status]['count'] += $count;
            if (!isset($invoiceTotals[$status]['amounts'][$currency])) {
                $invoiceTotals[$status]['amounts'][$currency] = 0;
            }
            $invoiceTotals[$status]['amounts'][$currency] += $amount;
        }

        $billableMinutes = 0;
        $nonBillableMinutes = 0;
        $billableSessions = 0;
        $nonBillableSessions = 0;

        $workRows = $this->reports->workEventTotals($userId, $from, $to, $clientId);
        foreach ($workRows as $row) {
            $isBillable = $this->isBillable($row['billable'] ?? null);
            $minutes = (int) ($row['minutes'] ?? 0);
            $count = (int) ($row['count'] ?? 0);

            if ($isBillable) {
                $billableMinutes += $minutes;
                $billableSessions += $count;
            } else {
                $nonBillableMinutes += $minutes;
                $nonBillableSessions += $count;
            }
        }

        return [
            'invoice_totals' => $invoiceTotals,
            'work_events' => [
                'total_minutes' => $billableMinutes + $nonBillableMinutes,
                'billable_minutes' => $billableMinutes,
                'non_billable_minutes' => $nonBillableMinutes,
                'total_sessions' => $billableSessions + $nonBillableSessions,
                'billable_sessions' => $billableSessions,
                'non_billable_sessions' => $nonBillableSessions,
            ],
        ];
    }

    private function isBillable(mixed $value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }

        if ($value === false || $value === 0 || $value === null) {
            return false;
        }

        if (is_string($value)) {
            $value = strtolower($value);
            if (in_array($value, ['t', 'true', '1', 'yes', 'y'], true)) {
                return true;
            }
            if (in_array($value, ['f', 'false', '0', 'no', 'n'], true)) {
                return false;
            }
        }

        return false;
    }
}
