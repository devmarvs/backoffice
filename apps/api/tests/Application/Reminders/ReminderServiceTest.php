<?php

declare(strict_types=1);

namespace App\Tests\Application\Reminders;

use App\Application\Reminders\ReminderService;
use App\Application\Templates\TemplateService;
use App\Domain\Repository\AuditLogRepositoryInterface;
use App\Domain\Repository\FollowUpRepositoryInterface;
use App\Domain\Repository\InvoiceDraftRepositoryInterface;
use App\Domain\Repository\MessageTemplateRepositoryInterface;
use App\Domain\Repository\UserSettingsRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReminderServiceTest extends TestCase
{
    public function testRunRecordsLastRunWhenDisabled(): void
    {
        $settings = new StubSettingsRepository(['invoice_reminder_days' => 0]);
        $auditLogs = new StubAuditLogRepository();
        $service = new ReminderService(
            new StubInvoiceDraftRepository([]),
            new StubFollowUpRepository(),
            $settings,
            new TemplateService(new StubMessageTemplateRepository()),
            $auditLogs,
            7
        );

        $count = $service->runForUser(1);

        self::assertSame(0, $count);
        self::assertNotNull($settings->lastRunAt);
        self::assertCount(1, $auditLogs->entries);
        self::assertSame('reminders.run', $auditLogs->entries[0]['action']);
    }

    public function testRunCreatesReminderAndUpdatesLastRun(): void
    {
        $settings = new StubSettingsRepository(['invoice_reminder_days' => 3]);
        $drafts = new StubInvoiceDraftRepository([
            [
                'id' => 10,
                'client_id' => 5,
                'currency' => 'EUR',
                'amount_cents' => 12000,
                'created_at' => (new DateTimeImmutable('-10 days'))->format(DateTimeImmutable::ATOM),
            ],
        ]);
        $followUps = new StubFollowUpRepository();
        $auditLogs = new StubAuditLogRepository();

        $service = new ReminderService(
            $drafts,
            $followUps,
            $settings,
            new TemplateService(new StubMessageTemplateRepository()),
            $auditLogs,
            7
        );

        $count = $service->runForUser(1);

        self::assertSame(1, $count);
        self::assertSame(1, $followUps->created);
        self::assertNotNull($settings->lastRunAt);
        self::assertCount(1, $auditLogs->entries);
        self::assertSame(1, $auditLogs->entries[0]['metadata']['created']);
    }
}

final class StubInvoiceDraftRepository implements InvoiceDraftRepositoryInterface
{
    public function __construct(private array $drafts)
    {
    }

    public function createDraft(int $userId, int $clientId, int $amountCents, string $currency): array
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function addLine(
        int $invoiceDraftId,
        ?int $workEventId,
        string $description,
        string $quantity,
        int $unitPriceCents
    ): array {
        throw new \BadMethodCallException('Not used.');
    }

    public function updateAmount(int $invoiceDraftId): int
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function listByStatus(int $userId, string $status): array
    {
        return $this->drafts;
    }

    public function listByDateRange(int $userId, ?string $from, ?string $to): array
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function findLines(int $invoiceDraftId): array
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function listWithLinesByDateRange(int $userId, ?string $from, ?string $to): array
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function bulkUpdateStatus(int $userId, array $ids, string $status): int
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function updateStatus(int $userId, int $invoiceDraftId, string $status): ?array
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function findById(int $userId, int $invoiceDraftId): ?array
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function listForExport(int $userId, ?string $status, ?string $from, ?string $to): array
    {
        throw new \BadMethodCallException('Not used.');
    }
}

final class StubFollowUpRepository implements FollowUpRepositoryInterface
{
    public int $created = 0;

    public function create(
        int $userId,
        int $clientId,
        DateTimeImmutable $dueAt,
        string $suggestedMessage,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): array {
        $this->created++;
        return [];
    }

    public function listByStatus(int $userId, string $status): array
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function updateStatus(int $userId, int $followUpId, string $status): ?array
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function findOpenBySource(int $userId, string $sourceType, int $sourceId): ?array
    {
        return null;
    }

    public function listForExport(
        int $userId,
        ?string $status,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to
    ): array
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function findById(int $userId, int $followUpId): ?array
    {
        throw new \BadMethodCallException('Not used.');
    }
}

final class StubSettingsRepository implements UserSettingsRepositoryInterface
{
    public ?DateTimeImmutable $lastRunAt = null;

    public function __construct(private array $settings)
    {
    }

    public function getByUserId(int $userId): ?array
    {
        return $this->settings;
    }

    public function upsert(int $userId, array $data): array
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function updateLastReminderRun(int $userId, DateTimeImmutable $runAt): void
    {
        $this->lastRunAt = $runAt;
    }
}

final class StubAuditLogRepository implements AuditLogRepositoryInterface
{
    public array $entries = [];

    public function add(int $userId, string $action, string $entityType, ?int $entityId, array $metadata = []): void
    {
        $this->entries[] = [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
        ];
    }

    public function listRecent(int $userId, int $limit): array
    {
        throw new \BadMethodCallException('Not used.');
    }
}

final class StubMessageTemplateRepository implements MessageTemplateRepositoryInterface
{
    public function listByUser(int $userId): array
    {
        return [];
    }

    public function findByType(int $userId, string $type): ?array
    {
        return null;
    }

    public function upsert(int $userId, string $type, ?string $subject, string $body): array
    {
        throw new \BadMethodCallException('Not used.');
    }
}
