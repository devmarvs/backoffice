<?php

declare(strict_types=1);

namespace App\Application\Reminders;

use App\Application\Templates\TemplateService;
use App\Domain\Repository\FollowUpRepositoryInterface;
use App\Domain\Repository\InvoiceDraftRepositoryInterface;
use App\Domain\Repository\UserSettingsRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ReminderService
{
    public function __construct(
        private InvoiceDraftRepositoryInterface $invoiceDrafts,
        private FollowUpRepositoryInterface $followUps,
        private UserSettingsRepositoryInterface $settings,
        private TemplateService $templates,
        #[Autowire('%app.invoice_reminder_days%')] private int $defaultReminderDays
    ) {
    }

    public function runForUser(int $userId): int
    {
        $settings = $this->settings->getByUserId($userId);
        $reminderDays = $settings['invoice_reminder_days'] ?? $this->defaultReminderDays;

        if ($reminderDays === null || (int) $reminderDays <= 0) {
            $this->settings->updateLastReminderRun($userId, new DateTimeImmutable());
            return 0;
        }

        $cutoff = (new DateTimeImmutable())->modify(sprintf('-%d days', (int) $reminderDays));
        $drafts = $this->invoiceDrafts->listByStatus($userId, 'draft');
        $count = 0;

        foreach ($drafts as $draft) {
            if (!isset($draft['created_at'])) {
                continue;
            }

            $createdAt = new DateTimeImmutable((string) $draft['created_at']);
            if ($createdAt > $cutoff) {
                continue;
            }

            $draftId = (int) $draft['id'];
            if ($this->followUps->findOpenBySource($userId, 'invoice_draft', $draftId) !== null) {
                continue;
            }

            $amount = sprintf('%s %.2f', $draft['currency'], ((int) $draft['amount_cents']) / 100);
            $message = $this->templates->resolve(
                $userId,
                'payment_reminder',
                [
                    'invoice_id' => $draftId,
                    'amount' => $amount,
                ]
            );

            if ($message === '') {
                $message = sprintf('Reminder: invoice #%d for %s is ready when you are.', $draftId, $amount);
            }

            $this->followUps->create(
                $userId,
                (int) $draft['client_id'],
                new DateTimeImmutable(),
                $message,
                'invoice_draft',
                $draftId
            );

            $count++;
        }

        $this->settings->updateLastReminderRun($userId, new DateTimeImmutable());

        return $count;
    }
}
