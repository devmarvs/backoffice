<?php

declare(strict_types=1);

namespace App\Application\Autopilot;

use App\Domain\Enum\WorkEventType;
use App\Domain\Repository\ClientRepositoryInterface;
use App\Domain\Repository\FollowUpRepositoryInterface;
use App\Domain\Repository\InvoiceDraftRepositoryInterface;
use App\Domain\Repository\PackageRepositoryInterface;
use App\Application\Templates\TemplateService;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class WorkEventAutopilot
{
    public function __construct(
        private ClientRepositoryInterface $clients,
        private PackageRepositoryInterface $packages,
        private InvoiceDraftRepositoryInterface $invoiceDrafts,
        private FollowUpRepositoryInterface $followUps,
        private TemplateService $templates,
        #[Autowire('%app.follow_up_days%')] private int $followUpDays,
    ) {
    }

    public function handle(
        array $workEvent,
        ?int $rateCents,
        ?string $currency,
        ?int $followUpDays = null
    ): array
    {
        $result = [
            'invoice_draft' => null,
            'invoice_line' => null,
            'package' => null,
            'follow_up' => null,
        ];

        $type = (string) ($workEvent['type'] ?? '');
        $isSession = $type === WorkEventType::Session->value;
        $billable = (bool) ($workEvent['billable'] ?? false);

        if ($isSession && $billable) {
            $currency = $currency ?: 'EUR';
            $durationMinutes = (int) $workEvent['duration_minutes'];
            $quantityHours = $durationMinutes > 0 ? round($durationMinutes / 60, 2) : 0.0;
            $amountCents = $rateCents !== null ? (int) round($rateCents * $quantityHours) : 0;

            $draft = $this->invoiceDrafts->createDraft(
                (int) $workEvent['user_id'],
                (int) $workEvent['client_id'],
                $amountCents,
                $currency
            );

            $quantity = number_format($quantityHours > 0 ? $quantityHours : 1.0, 2, '.', '');
            $description = sprintf('Session (%s)', $this->formatDuration($durationMinutes));

            $line = $this->invoiceDrafts->addLine(
                (int) $draft['id'],
                (int) $workEvent['id'],
                $description,
                $quantity,
                $rateCents ?? 0
            );

            $this->invoiceDrafts->updateAmount((int) $draft['id']);

            $result['invoice_draft'] = $draft;
            $result['invoice_line'] = $line;
        }

        if ($isSession) {
            $package = $this->packages->findFirstAvailable(
                (int) $workEvent['user_id'],
                (int) $workEvent['client_id']
            );

            if ($package !== null) {
                $updated = $this->packages->incrementUsedSessions((int) $package['id']);
                $remaining = (int) $updated['total_sessions'] - (int) $updated['used_sessions'];
                $result['package'] = [
                    'id' => (int) $updated['id'],
                    'remaining_sessions' => $remaining,
                ];
            }
        }

        $followUpDays = $followUpDays ?? $this->followUpDays;

        if ($isSession && $followUpDays > 0) {
            $dueAt = (new DateTimeImmutable((string) $workEvent['start_at']))
                ->modify(sprintf('+%d days', $followUpDays));

            $client = $this->clients->findById(
                (int) $workEvent['user_id'],
                (int) $workEvent['client_id']
            );
            $clientName = $client['name'] ?? 'your client';
            $sessionDate = $dueAt->modify(sprintf('-%d days', $followUpDays))->format('M j');

            $message = $this->templates->resolve(
                (int) $workEvent['user_id'],
                'follow_up',
                [
                    'client_name' => $clientName,
                    'session_date' => $sessionDate,
                ]
            );

            if ($message === '') {
                $message = sprintf('Follow up with %s about the %s session.', $clientName, $sessionDate);
            }

            $followUp = $this->followUps->create(
                (int) $workEvent['user_id'],
                (int) $workEvent['client_id'],
                $dueAt,
                $message,
                'work_event',
                (int) $workEvent['id']
            );

            $result['follow_up'] = $followUp;
        }

        return $result;
    }

    private function formatDuration(int $durationMinutes): string
    {
        if ($durationMinutes <= 0) {
            return '0m';
        }

        $hours = intdiv($durationMinutes, 60);
        $minutes = $durationMinutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        if ($hours > 0) {
            return sprintf('%dh', $hours);
        }

        return sprintf('%dm', $minutes);
    }
}
