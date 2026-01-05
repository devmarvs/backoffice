<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Enum\FollowUpStatus;
use App\Domain\Repository\FollowUpRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/follow-ups')]
final class FollowUpController extends BaseApiController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request, FollowUpRepositoryInterface $followUps): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $status = $request->query->get('status', FollowUpStatus::Open->value);
        $allowed = array_map(fn (FollowUpStatus $item) => $item->value, FollowUpStatus::cases());

        if (!in_array($status, $allowed, true)) {
            return $this->jsonError('invalid_status', 'Status is invalid.', 422);
        }

        $rows = $followUps->listByStatus($userId, (string) $status);
        $rows = array_map(
            fn (array $row) => $this->normalizeDates($row, ['due_at', 'created_at', 'updated_at']),
            $rows
        );

        return $this->jsonSuccess($rows);
    }

    #[Route('/export', methods: ['GET'])]
    public function export(Request $request, FollowUpRepositoryInterface $followUps): Response
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $status = $request->query->get('status');
        $allowed = array_map(fn (FollowUpStatus $item) => $item->value, FollowUpStatus::cases());

        if ($status !== null && $status !== '' && !in_array($status, $allowed, true)) {
            return $this->jsonError('invalid_status', 'Status is invalid.', 422);
        }

        $from = $request->query->get('from');
        $to = $request->query->get('to');
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

        $rows = $followUps->listForExport(
            $userId,
            $status !== '' ? $status : null,
            $fromDate,
            $toDate
        );

        $rows = array_map(
            fn (array $row) => $this->normalizeDates($row, ['due_at', 'created_at', 'updated_at']),
            $rows
        );

        $csvRows = array_map(
            static fn (array $row) => [
                (string) $row['id'],
                (string) $row['client_id'],
                (string) ($row['client_name'] ?? ''),
                (string) $row['due_at'],
                (string) $row['suggested_message'],
                (string) $row['status'],
                (string) ($row['source_type'] ?? ''),
                (string) ($row['source_id'] ?? ''),
                (string) $row['created_at'],
                (string) $row['updated_at'],
            ],
            $rows
        );

        return $this->csvResponse(
            [
                'id',
                'client_id',
                'client_name',
                'due_at',
                'suggested_message',
                'status',
                'source_type',
                'source_id',
                'created_at',
                'updated_at',
            ],
            $csvRows,
            'follow-ups.csv'
        );
    }

    #[Route('/{id}/done', methods: ['POST'])]
    public function markDone(
        Request $request,
        FollowUpRepositoryInterface $followUps,
        int $id
    ): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $followUp = $followUps->updateStatus($userId, $id, FollowUpStatus::Done->value);
        if ($followUp === null) {
            return $this->jsonError('not_found', 'Follow-up not found.', 404);
        }

        $followUp = $this->normalizeDates($followUp, ['due_at', 'created_at', 'updated_at']);

        return $this->jsonSuccess($followUp);
    }

    #[Route('/{id}/dismiss', methods: ['POST'])]
    public function dismiss(
        Request $request,
        FollowUpRepositoryInterface $followUps,
        int $id
    ): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $followUp = $followUps->updateStatus($userId, $id, FollowUpStatus::Dismissed->value);
        if ($followUp === null) {
            return $this->jsonError('not_found', 'Follow-up not found.', 404);
        }

        $followUp = $this->normalizeDates($followUp, ['due_at', 'created_at', 'updated_at']);

        return $this->jsonSuccess($followUp);
    }

    #[Route('/{id}/reopen', methods: ['POST'])]
    public function reopen(
        Request $request,
        FollowUpRepositoryInterface $followUps,
        int $id
    ): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $followUp = $followUps->updateStatus($userId, $id, FollowUpStatus::Open->value);
        if ($followUp === null) {
            return $this->jsonError('not_found', 'Follow-up not found.', 404);
        }

        $followUp = $this->normalizeDates($followUp, ['due_at', 'created_at', 'updated_at']);

        return $this->jsonSuccess($followUp);
    }
}
