<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Repository\CalendarEventRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/calendar')]
final class CalendarController extends BaseApiController
{
    #[Route('/events', methods: ['GET'])]
    public function list(Request $request, CalendarEventRepositoryInterface $events): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $from = $request->query->get('from');
        $to = $request->query->get('to');

        $rows = $events->listByRange(
            $userId,
            $from !== null ? (string) $from : null,
            $to !== null ? (string) $to : null
        );

        $rows = array_map(
            fn (array $row) => $this->normalizeDates($row, ['start_at', 'end_at', 'created_at']),
            $rows
        );

        return $this->jsonSuccess($rows);
    }
}
