<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Reports\ReportingService;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reports')]
final class ReportsController extends BaseApiController
{
    #[Route('/summary', methods: ['GET'])]
    public function summary(Request $request, ReportingService $reports): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

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

        $summary = $reports->summary($userId, $fromDate, $toDate, $clientIdValue);

        return $this->jsonSuccess($summary);
    }
}
