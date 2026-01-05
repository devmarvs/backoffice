<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\WorkEvent\WorkEventService;
use App\Domain\Enum\WorkEventType;
use App\Application\Voice\VoiceWorkEventParser;
use App\Domain\Repository\ClientRepositoryInterface;
use App\Domain\Repository\WorkEventRepositoryInterface;
use DateTimeImmutable;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/work-events')]
final class WorkEventController extends BaseApiController
{
    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        WorkEventService $service,
        ClientRepositoryInterface $clients
    ): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
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

        $type = isset($payload['type']) ? (string) $payload['type'] : WorkEventType::Session->value;
        $allowedTypes = array_map(fn (WorkEventType $item) => $item->value, WorkEventType::cases());

        if (!in_array($type, $allowedTypes, true)) {
            return $this->jsonError('invalid_type', 'Work event type is invalid.', 422);
        }

        $startAtRaw = isset($payload['start_at']) ? (string) $payload['start_at'] : '';
        if ($startAtRaw === '') {
            return $this->jsonError('invalid_start_at', 'start_at is required.', 422);
        }

        try {
            $startAt = new DateTimeImmutable($startAtRaw);
        } catch (\Exception $exception) {
            return $this->jsonError('invalid_start_at', 'start_at must be a valid datetime.', 422);
        }

        $durationMinutes = isset($payload['duration_minutes']) ? (int) $payload['duration_minutes'] : 0;
        if ($durationMinutes <= 0) {
            return $this->jsonError('invalid_duration', 'duration_minutes must be greater than 0.', 422);
        }

        $billable = true;
        if (array_key_exists('billable', $payload)) {
            $billable = filter_var($payload['billable'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($billable === null) {
                return $this->jsonError('invalid_billable', 'billable must be a boolean.', 422);
            }
        }
        $notes = isset($payload['notes']) ? trim((string) $payload['notes']) : null;

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

        $data = [
            'user_id' => $userId,
            'client_id' => $clientId,
            'type' => $type,
            'start_at' => $startAt->format('Y-m-d H:i:sP'),
            'duration_minutes' => $durationMinutes,
            'billable' => $billable,
            'notes' => $notes,
        ];

        $result = $service->log($data, $rateCents, $currency ?: null);
        $workEvent = $this->normalizeDates($result['work_event'], ['start_at', 'created_at']);

        $autopilot = $result['autopilot'];
        if (isset($autopilot['invoice_draft'])) {
            $autopilot['invoice_draft'] = $this->normalizeDates(
                (array) $autopilot['invoice_draft'],
                ['created_at', 'updated_at']
            );
        }

        if (isset($autopilot['follow_up'])) {
            $autopilot['follow_up'] = $this->normalizeDates(
                (array) $autopilot['follow_up'],
                ['due_at', 'created_at']
            );
        }

        return $this->jsonSuccess([
            'work_event' => $workEvent,
            'autopilot' => $autopilot,
        ], 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request, WorkEventRepositoryInterface $workEvents): JsonResponse
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
            if ($from !== null) {
                $fromDate = new DateTimeImmutable((string) $from);
            }
            if ($to !== null) {
                $toDate = new DateTimeImmutable((string) $to);
            }
        } catch (\Exception $exception) {
            return $this->jsonError('invalid_range', 'from/to must be valid dates.', 422);
        }

        $rows = $workEvents->list(
            $userId,
            $fromDate,
            $toDate,
            $clientId !== null ? (int) $clientId : null
        );

        $rows = array_map(
            fn (array $row) => $this->normalizeDates($row, ['start_at', 'created_at']),
            $rows
        );

        return $this->jsonSuccess($rows);
    }

    #[Route('/export', methods: ['GET'])]
    public function export(Request $request, WorkEventRepositoryInterface $workEvents): Response
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
            if ($from !== null) {
                $fromDate = new DateTimeImmutable((string) $from);
            }
            if ($to !== null) {
                $toDate = new DateTimeImmutable((string) $to);
            }
        } catch (\Exception $exception) {
            return $this->jsonError('invalid_range', 'from/to must be valid dates.', 422);
        }

        $rows = $workEvents->listForExport(
            $userId,
            $fromDate,
            $toDate,
            $clientId !== null ? (int) $clientId : null
        );

        $rows = array_map(
            fn (array $row) => $this->normalizeDates($row, ['start_at', 'created_at']),
            $rows
        );

        $csvRows = array_map(
            static fn (array $row) => [
                (string) $row['id'],
                (string) $row['client_id'],
                (string) ($row['client_name'] ?? ''),
                (string) $row['type'],
                (string) $row['start_at'],
                (string) $row['duration_minutes'],
                isset($row['billable']) && (bool) $row['billable'] ? 'true' : 'false',
                (string) ($row['notes'] ?? ''),
                (string) $row['created_at'],
            ],
            $rows
        );

        return $this->csvResponse(
            ['id', 'client_id', 'client_name', 'type', 'start_at', 'duration_minutes', 'billable', 'notes', 'created_at'],
            $csvRows,
            'work-events.csv'
        );
    }

    #[Route('/voice', methods: ['POST'])]
    public function voiceLog(
        Request $request,
        WorkEventService $service,
        ClientRepositoryInterface $clients,
        VoiceWorkEventParser $parser
    ): JsonResponse {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        try {
            $payload = $this->parseJson($request);
        } catch (JsonException $exception) {
            return $this->jsonError('invalid_json', $exception->getMessage(), 400);
        }

        $transcript = isset($payload['transcript']) ? trim((string) $payload['transcript']) : '';
        if ($transcript === '') {
            return $this->jsonError('invalid_transcript', 'transcript is required.', 422);
        }

        $clientId = isset($payload['client_id']) ? (int) $payload['client_id'] : 0;
        if ($clientId <= 0 && isset($payload['client_name'])) {
            $matches = $clients->search($userId, (string) $payload['client_name']);
            if (count($matches) === 1) {
                $clientId = (int) $matches[0]['id'];
            } else {
                return $this->jsonError('ambiguous_client', 'client_name must match a single client.', 422);
            }
        }

        if ($clientId <= 0 || $clients->findById($userId, $clientId) === null) {
            return $this->jsonError('invalid_client', 'client_id is required.', 422);
        }

        $durationMinutes = isset($payload['duration_minutes']) ? (int) $payload['duration_minutes'] : 0;
        if ($durationMinutes <= 0) {
            $parsed = $parser->extractDurationMinutes($transcript);
            $durationMinutes = $parsed ?? 0;
        }

        if ($durationMinutes <= 0) {
            return $this->jsonError('invalid_duration', 'duration_minutes is required.', 422);
        }

        $startAtRaw = isset($payload['start_at']) ? (string) $payload['start_at'] : null;
        $startAt = new DateTimeImmutable();
        if ($startAtRaw !== null && $startAtRaw !== '') {
            try {
                $startAt = new DateTimeImmutable($startAtRaw);
            } catch (\Exception $exception) {
                return $this->jsonError('invalid_start_at', 'start_at must be a valid datetime.', 422);
            }
        }

        $type = isset($payload['type']) ? (string) $payload['type'] : WorkEventType::Session->value;
        $allowedTypes = array_map(fn (WorkEventType $item) => $item->value, WorkEventType::cases());
        if (!in_array($type, $allowedTypes, true)) {
            return $this->jsonError('invalid_type', 'Work event type is invalid.', 422);
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

        $result = $service->log(
            [
                'user_id' => $userId,
                'client_id' => $clientId,
                'type' => $type,
                'start_at' => $startAt->format('Y-m-d H:i:sP'),
                'duration_minutes' => $durationMinutes,
                'billable' => $billable,
                'notes' => $transcript,
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
