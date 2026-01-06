<?php

declare(strict_types=1);

namespace App\Tests\Application\Reports;

use App\Application\Reports\ReportingService;
use App\Domain\Repository\ReportingRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReportingServiceTest extends TestCase
{
    public function testSummaryAggregatesTotals(): void
    {
        $repository = new StubReportingRepository(
            [
                ['status' => 'draft', 'currency' => 'EUR', 'count' => 2, 'amount_cents' => 5000],
                ['status' => 'paid', 'currency' => 'USD', 'count' => 1, 'amount_cents' => 15000],
                ['status' => 'paid', 'currency' => 'EUR', 'count' => 1, 'amount_cents' => 8000],
            ],
            [
                ['billable' => 't', 'count' => 3, 'minutes' => 180],
                ['billable' => 'f', 'count' => 1, 'minutes' => 30],
            ]
        );

        $service = new ReportingService($repository);
        $summary = $service->summary(1, new DateTimeImmutable('-1 day'), new DateTimeImmutable(), null);

        self::assertSame(2, $summary['invoice_totals']['draft']['count']);
        self::assertSame(5000, $summary['invoice_totals']['draft']['amounts']['EUR']);
        self::assertSame(2, $summary['invoice_totals']['paid']['count']);
        self::assertSame(15000, $summary['invoice_totals']['paid']['amounts']['USD']);
        self::assertSame(8000, $summary['invoice_totals']['paid']['amounts']['EUR']);
        self::assertSame(0, $summary['invoice_totals']['sent']['count']);

        self::assertSame(210, $summary['work_events']['total_minutes']);
        self::assertSame(180, $summary['work_events']['billable_minutes']);
        self::assertSame(30, $summary['work_events']['non_billable_minutes']);
        self::assertSame(4, $summary['work_events']['total_sessions']);
    }
}

final class StubReportingRepository implements ReportingRepositoryInterface
{
    public function __construct(private array $invoiceRows, private array $workRows)
    {
    }

    public function invoiceTotals(
        int $userId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?int $clientId
    ): array {
        return $this->invoiceRows;
    }

    public function workEventTotals(
        int $userId,
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?int $clientId
    ): array {
        return $this->workRows;
    }
}
