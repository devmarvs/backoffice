<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Repository\CalendarEventRepositoryInterface;
use App\Domain\Repository\ClientRepositoryInterface;
use App\Application\WorkEvent\WorkEventService;
use App\Domain\Enum\WorkEventType;
use DateTimeImmutable;
use JsonException;
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
        $from = $from !== null && $from !== '' ? (string) $from : null;
        $to = $to !== null && $to !== '' ? (string) $to : null;

        $rows = $events->listByRange(
            $userId,
            $from,
            $to
        );

        $rows = array_map(
            fn (array $row) => $this->normalizeDates($row, ['start_at', 'end_at', 'created_at']),
            $rows
        );

        return $this->jsonSuccess($rows);
    }

    #[Route('/suggestions', methods: ['GET'])]
    public function suggestions(Request $request, CalendarEventRepositoryInterface $events): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $from = $from !== null && $from !== '' ? (string) $from : null;
        $to = $to !== null && $to !== '' ? (string) $to : null;

        $rows = $events->listSuggestions(
            $userId,
            $from,
            $to
        );

        $rows = array_map(
            fn (array $row) => $this->normalizeDates($row, ['start_at', 'end_at', 'created_at']),
            $rows
        );

        return $this->jsonSuccess($rows);
    }

    #[Route('/events/{id}/log', methods: ['POST'])]
    public function logFromCalendar(
        Request $request,
        CalendarEventRepositoryInterface $events,
        ClientRepositoryInterface $clients,
        WorkEventService $workEventService,
        int $id
    ): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $event = $events->findById($userId, $id);
        if ($event === null) {
            return $this->jsonError('not_found', 'Calendar event not found.', 404);
        }

        try {
            $payload = $this->parseJson($request);
        } catch (JsonException $exception) {
            return $this->jsonError('invalid_json', $exception->getMessage(), 400);
        }

        $clientId = isset($payload['client_id']) ? (int) $payload['client_id'] : 0;
        if ($clientId <= 0) {
            return $this->jsonError('invalid_client', 'client_id is required.', 422);
        }

        if ($clients->findById($userId, $clientId) === null) {
            return $this->jsonError('invalid_client', 'Client not found.', 404);
        }

        $startAt = new DateTimeImmutable((string) $event['start_at']);
        $durationMinutes = 0;
        if (!empty($event['end_at'])) {
            $endAt = new DateTimeImmutable((string) $event['end_at']);
            $durationMinutes = (int) round(max(0, $endAt->getTimestamp() - $startAt->getTimestamp()) / 60);
        }

        $overrideDuration = isset($payload['duration_minutes']) ? (int) $payload['duration_minutes'] : 0;
        if ($overrideDuration > 0) {
            $durationMinutes = $overrideDuration;
        }

        if ($durationMinutes <= 0) {
            $durationMinutes = 60;
        }

        $billable = true;
        if (array_key_exists('billable', $payload)) {
            $billable = filter_var($payload['billable'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($billable === null) {
                return $this->jsonError('invalid_billable', 'billable must be a boolean.', 422);
            }
        }

        $rateCents = null;
        if (array_key_exists('rate_cents', $payload) && $payload['rate_cents'] !== null) {
            $rateCents = (int) $payload['rate_cents'];
            if ($rateCents < 0) {
                return $this->jsonError('invalid_rate', 'rate_cents must be >= 0.', 422);
            }
        }

        $currency = isset($payload['currency']) ? strtoupper(trim((string) $payload['currency'])) : null;
        if ($currency !== null && $currency !== '' && strlen($currency) !== 3) {
            return $this->jsonError('invalid_currency', 'currency must be a 3-letter code.', 422);
        }

        $notes = isset($payload['notes']) ? trim((string) $payload['notes']) : null;
        if ($notes === null || $notes === '') {
            $notes = $event['summary'] ?? null;
        }

        $result = $workEventService->log(
            [
                'user_id' => $userId,
                'client_id' => $clientId,
                'type' => WorkEventType::Session->value,
                'start_at' => $startAt->format('Y-m-d H:i:sP'),
                'duration_minutes' => $durationMinutes,
                'billable' => $billable,
                'notes' => $notes,
                'source_type' => 'calendar_event',
                'source_id' => (int) $event['id'],
            ],
            $rateCents,
            $currency ?: null
        );

        $workEvent = $this->normalizeDates($result['work_event'], ['start_at', 'created_at']);

        return $this->jsonSuccess([
            'work_event' => $workEvent,
            'autopilot' => $result['autopilot'],
        ], 201);
    }
}
