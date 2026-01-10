<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Billing\BillingAccessService;
use App\Application\Reports\ReportingService;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reports')]
final class ReportsController extends BaseApiController
{
    #[Route('/summary', methods: ['GET'])]
    public function summary(
        Request $request,
        ReportingService $reports,
        BillingAccessService $billingAccess
    ): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $filters = $this->parseFilters($request);
        if ($filters instanceof JsonResponse) {
            return $filters;
        }

        $summary = $reports->summary(
            $userId,
            $filters['from'],
            $filters['to'],
            $filters['clientId']
        );

        if (!$billingAccess->hasProAccess($userId)) {
            return $this->jsonSuccess($this->toBasicSummary($summary));
        }

        $summary['scope'] = 'full';

        return $this->jsonSuccess($summary);
    }

    #[Route('/export', methods: ['GET'])]
    public function export(
        Request $request,
        ReportingService $reports,
        BillingAccessService $billingAccess
    ): Response
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        if (!$billingAccess->hasProAccess($userId)) {
            return $this->jsonError('plan_required', 'Pro plan required for reporting export.', 403);
        }

        $filters = $this->parseFilters($request);
        if ($filters instanceof JsonResponse) {
            return $filters;
        }

        $summary = $reports->summary(
            $userId,
            $filters['from'],
            $filters['to'],
            $filters['clientId']
        );

        $rows = $this->buildExportRows($summary);

        return $this->csvResponse(
            ['group', 'metric', 'value', 'currency'],
            $rows,
            'report-summary.csv'
        );
    }

    private function parseFilters(Request $request): array|JsonResponse
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $clientId = $request->query->get('clientId');

        $fromDate = null;
        $toDate = null;

        try {
            if ($from !== null && $from !== '') {
                $fromDate = new DateTimeImmutable((string) $from);
            }
            if ($to !== null && $to !== '') {
                $toDate = new DateTimeImmutable((string) $to);
            }
        } catch (\Exception $exception) {
            return $this->jsonError('invalid_range', 'from/to must be valid dates.', 422);
        }

        $clientIdValue = null;
        if ($clientId !== null && $clientId !== '') {
            $clientIdValue = (int) $clientId;
            if ($clientIdValue <= 0) {
                return $this->jsonError('invalid_client', 'clientId must be a positive integer.', 422);
            }
        }

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'clientId' => $clientIdValue,
        ];
    }

    private function toBasicSummary(array $summary): array
    {
        $paidTotals = $summary['invoice_totals']['paid'] ?? ['count' => 0, 'amounts' => []];
        $workEvents = $summary['work_events'] ?? [];

        return [
            'scope' => 'basic',
            'invoice_totals' => [
                'paid' => $paidTotals,
            ],
            'work_events' => [
                'total_minutes' => (int) ($workEvents['total_minutes'] ?? 0),
                'total_sessions' => (int) ($workEvents['total_sessions'] ?? 0),
            ],
        ];
    }

    private function buildExportRows(array $summary): array
    {
        $rows = [];
        $workEvents = $summary['work_events'] ?? [];

        $rows[] = ['work_events', 'total_minutes', (string) ($workEvents['total_minutes'] ?? 0), ''];
        $rows[] = ['work_events', 'billable_minutes', (string) ($workEvents['billable_minutes'] ?? 0), ''];
        $rows[] = ['work_events', 'non_billable_minutes', (string) ($workEvents['non_billable_minutes'] ?? 0), ''];
        $rows[] = ['work_events', 'total_sessions', (string) ($workEvents['total_sessions'] ?? 0), ''];
        $rows[] = ['work_events', 'billable_sessions', (string) ($workEvents['billable_sessions'] ?? 0), ''];
        $rows[] = ['work_events', 'non_billable_sessions', (string) ($workEvents['non_billable_sessions'] ?? 0), ''];

        $invoiceTotals = $summary['invoice_totals'] ?? [];
        foreach ($invoiceTotals as $status => $totals) {
            $rows[] = ['invoice_totals', $status . '_count', (string) ($totals['count'] ?? 0), ''];
            $amounts = $totals['amounts'] ?? [];
            foreach ($amounts as $currency => $amount) {
                $rows[] = [
                    'invoice_totals',
                    $status . '_amount_cents',
                    (string) $amount,
                    (string) $currency,
                ];
            }
        }

        return $rows;
    }
}
