<?php

declare(strict_types=1);

namespace App\Tests\Application\Autopilot;

use App\Application\Autopilot\WorkEventAutopilot;
use App\Application\Templates\TemplateService;
use App\Domain\Enum\WorkEventType;
use App\Domain\Repository\ClientRepositoryInterface;
use App\Domain\Repository\FollowUpRepositoryInterface;
use App\Domain\Repository\InvoiceDraftRepositoryInterface;
use App\Domain\Repository\MessageTemplateRepositoryInterface;
use App\Domain\Repository\PackageRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WorkEventAutopilotTest extends TestCase
{
    public function testItCreatesInvoiceFollowUpAndPackageUpdates(): void
    {
        $clients = new StubClientRepository();
        $packages = new StubPackageRepository();
        $drafts = new StubInvoiceDraftRepository();
        $followUps = new StubFollowUpRepository();
        $templates = new TemplateService(new StubMessageTemplateRepository());

        $autopilot = new WorkEventAutopilot(
            $clients,
            $packages,
            $drafts,
            $followUps,
            $templates,
            3
        );

        $workEvent = [
            'id' => 15,
            'user_id' => 7,
            'client_id' => 3,
            'type' => WorkEventType::Session->value,
            'start_at' => '2025-01-15T09:00:00+00:00',
            'duration_minutes' => 90,
            'billable' => true,
        ];

        $result = $autopilot->handle($workEvent, 6000, 'EUR');

        self::assertNotNull($result['invoice_draft']);
        self::assertSame(99, $result['invoice_draft']['id']);
        self::assertSame(1, $drafts->updateAmountCalls);
        self::assertSame(6000, $drafts->lines[0]['unit_price_cents']);

        self::assertNotNull($result['package']);
        self::assertSame(2, $result['package']['remaining_sessions']);

        self::assertNotNull($result['follow_up']);
        self::assertStringContainsString('Anna', $result['follow_up']['suggested_message']);
        self::assertSame('2025-01-18T09:00:00+00:00', $result['follow_up']['due_at']);
    }
}

final class StubClientRepository implements ClientRepositoryInterface
{
    public function search(int $userId, ?string $query): array
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function create(int $userId, string $name, ?string $email, ?string $phone): array
    {
        throw new \BadMethodCallException('Not used.');
    }

    public function findById(int $userId, int $clientId): ?array
    {
        return ['id' => $clientId, 'user_id' => $userId, 'name' => 'Anna'];
    }

    public function update(int $userId, int $clientId, array $fields): ?array
    {
        throw new \BadMethodCallException('Not used.');
    }
}

final class StubPackageRepository implements PackageRepositoryInterface
{
    public function findFirstAvailable(int $userId, int $clientId): ?array
    {
        return [
            'id' => 5,
            'user_id' => $userId,
            'client_id' => $clientId,
            'total_sessions' => 4,
            'used_sessions' => 1,
        ];
    }

    public function incrementUsedSessions(int $packageId): array
    {
        return [
            'id' => $packageId,
            'total_sessions' => 4,
            'used_sessions' => 2,
        ];
    }
}

final class StubInvoiceDraftRepository implements InvoiceDraftRepositoryInterface
{
    public array $lines = [];
    public int $updateAmountCalls = 0;

    public function createDraft(int $userId, int $clientId, int $amountCents, string $currency): array
    {
        return [
            'id' => 99,
            'user_id' => $userId,
            'client_id' => $clientId,
            'amount_cents' => $amountCents,
            'currency' => $currency,
        ];
    }

    public function addLine(
        int $invoiceDraftId,
        ?int $workEventId,
        string $description,
        string $quantity,
        int $unitPriceCents
    ): array
    {
        $line = [
            'id' => 77,
            'invoice_draft_id' => $invoiceDraftId,
            'work_event_id' => $workEventId,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPriceCents,
        ];

        $this->lines[] = $line;

        return $line;
    }

    public function updateAmount(int $invoiceDraftId): int
    {
        $this->updateAmountCalls++;
        return 9000;
    }

    public function listByStatus(int $userId, string $status): array
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
}

final class StubFollowUpRepository implements FollowUpRepositoryInterface
{
    public function create(
        int $userId,
        int $clientId,
        DateTimeImmutable $dueAt,
        string $suggestedMessage,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): array
    {
        return [
            'id' => 44,
            'user_id' => $userId,
            'client_id' => $clientId,
            'due_at' => $dueAt->format(DateTimeImmutable::ATOM),
            'suggested_message' => $suggestedMessage,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => 'open',
            'created_at' => $dueAt->format(DateTimeImmutable::ATOM),
        ];
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
