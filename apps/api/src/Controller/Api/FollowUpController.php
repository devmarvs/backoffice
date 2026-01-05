<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Enum\FollowUpStatus;
use App\Domain\Repository\AuditLogRepositoryInterface;
use App\Domain\Repository\ClientRepositoryInterface;
use App\Domain\Repository\FollowUpRepositoryInterface;
use App\Infrastructure\Mail\SimpleMailer;
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
        AuditLogRepositoryInterface $auditLogs,
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

        $auditLogs->add($userId, 'follow_up.done', 'follow_up', $id, ['status' => FollowUpStatus::Done->value]);

        $followUp = $this->normalizeDates($followUp, ['due_at', 'created_at', 'updated_at']);

        return $this->jsonSuccess($followUp);
    }

    #[Route('/{id}/dismiss', methods: ['POST'])]
    public function dismiss(
        Request $request,
        FollowUpRepositoryInterface $followUps,
        AuditLogRepositoryInterface $auditLogs,
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

        $auditLogs->add($userId, 'follow_up.dismissed', 'follow_up', $id, ['status' => FollowUpStatus::Dismissed->value]);

        $followUp = $this->normalizeDates($followUp, ['due_at', 'created_at', 'updated_at']);

        return $this->jsonSuccess($followUp);
    }

    #[Route('/{id}/reopen', methods: ['POST'])]
    public function reopen(
        Request $request,
        FollowUpRepositoryInterface $followUps,
        AuditLogRepositoryInterface $auditLogs,
        int $id
    ): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $followUp = $followUps->findById($userId, $id);
        if ($followUp === null) {
            return $this->jsonError('not_found', 'Follow-up not found.', 404);
        }

        $auditLogs->add($userId, 'follow_up.reopened', 'follow_up', $id, ['status' => FollowUpStatus::Open->value]);

        $followUp = $this->normalizeDates($followUp, ['due_at', 'created_at', 'updated_at']);

        return $this->jsonSuccess($followUp);
    }

    #[Route('/{id}/email', methods: ['POST'])]
    public function email(
        Request $request,
        FollowUpRepositoryInterface $followUps,
        ClientRepositoryInterface $clients,
        SimpleMailer $mailer,
        AuditLogRepositoryInterface $auditLogs,
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

        $client = $clients->findById($userId, (int) $followUp['client_id']);
        if ($client === null) {
            return $this->jsonError('not_found', 'Client not found.', 404);
        }

        $email = isset($client['email']) ? trim((string) $client['email']) : '';
        if ($email === '') {
            return $this->jsonError('missing_email', 'Client email is required to send.', 409);
        }

        if (!$mailer->isConfigured()) {
            return $this->jsonError('not_configured', 'Mail sender is not configured.', 409);
        }

        $subject = sprintf('Follow-up for %s', $client['name'] ?? 'your session');
        $body = (string) ($followUp['suggested_message'] ?? '');
        if ($body === '') {
            $body = 'Just checking in on our recent session.';
        }

        try {
            $mailer->send($email, $subject, $body);
        } catch (\Throwable $exception) {
            return $this->jsonError('email_failed', $exception->getMessage(), 500);
        }

        $auditLogs->add($userId, 'follow_up.emailed', 'follow_up', $id, ['to' => $email]);

        $followUp = $this->normalizeDates($followUp, ['due_at', 'created_at', 'updated_at']);

        return $this->jsonSuccess(['sent' => true, 'follow_up' => $followUp]);
    }
}
