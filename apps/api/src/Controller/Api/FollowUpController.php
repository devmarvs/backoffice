<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Enum\FollowUpStatus;
use App\Domain\Repository\FollowUpRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
}
