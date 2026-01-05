<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Billing\InvoicePdfRenderer;
use App\Domain\Repository\AuditLogRepositoryInterface;
use App\Infrastructure\Mail\SimpleMailer;
use App\Infrastructure\Billing\StripeBillingService;
use App\Domain\Enum\InvoiceDraftStatus;
use App\Domain\Repository\ClientRepositoryInterface;
use App\Domain\Repository\InvoiceDraftRepositoryInterface;
use App\Domain\Repository\PaymentLinkRepositoryInterface;
use DateTimeImmutable;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/invoice-drafts')]
final class InvoiceDraftController extends BaseApiController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request, InvoiceDraftRepositoryInterface $drafts): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $status = $request->query->get('status', InvoiceDraftStatus::Draft->value);
        $allowed = array_map(fn (InvoiceDraftStatus $item) => $item->value, InvoiceDraftStatus::cases());

        if (!in_array($status, $allowed, true)) {
            return $this->jsonError('invalid_status', 'Status is invalid.', 422);
        }

        $rows = $drafts->listByStatus($userId, (string) $status);
        $rows = array_map(
            fn (array $row) => $this->normalizeDates($row, ['created_at', 'updated_at']),
            $rows
        );

        return $this->jsonSuccess($rows);
    }

    #[Route('/export', methods: ['GET'])]
    public function export(Request $request, InvoiceDraftRepositoryInterface $drafts): Response
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $status = $request->query->get('status');
        $allowed = array_map(fn (InvoiceDraftStatus $item) => $item->value, InvoiceDraftStatus::cases());

        if ($status !== null && $status !== '' && !in_array($status, $allowed, true)) {
            return $this->jsonError('invalid_status', 'Status is invalid.', 422);
        }

        $from = $request->query->get('from');
        $to = $request->query->get('to');

        $fromValue = null;
        $toValue = null;

        try {
            if ($from !== null) {
                $fromValue = (new DateTimeImmutable((string) $from))->format('Y-m-d H:i:sP');
            }
            if ($to !== null) {
                $toValue = (new DateTimeImmutable((string) $to))->format('Y-m-d H:i:sP');
            }
        } catch (\Exception $exception) {
            return $this->jsonError('invalid_range', 'from/to must be valid dates.', 422);
        }

        $rows = $drafts->listForExport(
            $userId,
            $status !== '' ? $status : null,
            $fromValue,
            $toValue
        );
        $rows = array_map(
            fn (array $row) => $this->normalizeDates($row, ['created_at', 'updated_at']),
            $rows
        );

        $csvRows = array_map(
            static fn (array $row) => [
                (string) $row['id'],
                (string) $row['client_id'],
                (string) ($row['client_name'] ?? ''),
                (string) ($row['period_start'] ?? ''),
                (string) ($row['period_end'] ?? ''),
                (string) $row['amount_cents'],
                (string) $row['currency'],
                (string) $row['status'],
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
                'period_start',
                'period_end',
                'amount_cents',
                'currency',
                'status',
                'created_at',
                'updated_at',
            ],
            $csvRows,
            'invoice-drafts.csv'
        );
    }

    #[Route('/{id}/mark-paid', methods: ['POST'])]
    public function markPaid(
        Request $request,
        InvoiceDraftRepositoryInterface $drafts,
        PaymentLinkRepositoryInterface $links,
        AuditLogRepositoryInterface $auditLogs,
        int $id
    ): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $draft = $drafts->updateStatus($userId, $id, InvoiceDraftStatus::Paid->value);
        if ($draft === null) {
            return $this->jsonError('not_found', 'Invoice draft not found.', 404);
        }

        $links->deactivateByInvoiceDraft($id);
        $auditLogs->add($userId, 'invoice.paid', 'invoice_draft', $id, ['status' => InvoiceDraftStatus::Paid->value]);

        $draft = $this->normalizeDates($draft, ['created_at', 'updated_at']);

        return $this->jsonSuccess($draft);
    }

    #[Route('/{id}/send', methods: ['POST'])]
    public function markSent(
        Request $request,
        InvoiceDraftRepositoryInterface $drafts,
        AuditLogRepositoryInterface $auditLogs,
        int $id
    ): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $draft = $drafts->updateStatus($userId, $id, InvoiceDraftStatus::Sent->value);
        if ($draft === null) {
            return $this->jsonError('not_found', 'Invoice draft not found.', 404);
        }

        $auditLogs->add($userId, 'invoice.sent', 'invoice_draft', $id, ['status' => InvoiceDraftStatus::Sent->value]);

        $draft = $this->normalizeDates($draft, ['created_at', 'updated_at']);

        return $this->jsonSuccess($draft);
    }

    #[Route('/{id}/void', methods: ['POST'])]
    public function void(
        Request $request,
        InvoiceDraftRepositoryInterface $drafts,
        PaymentLinkRepositoryInterface $links,
        AuditLogRepositoryInterface $auditLogs,
        int $id
    ): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $draft = $drafts->findById($userId, $id);
        if ($draft === null) {
            return $this->jsonError('not_found', 'Invoice draft not found.', 404);
        }

        if (($draft['status'] ?? null) === InvoiceDraftStatus::Paid->value) {
            return $this->jsonError('invalid_status', 'Paid invoices cannot be voided.', 409);
        }

        if (($draft['status'] ?? null) !== InvoiceDraftStatus::Void->value) {
            $draft = $drafts->updateStatus($userId, $id, InvoiceDraftStatus::Void->value);
        }

        if ($draft === null) {
            return $this->jsonError('not_found', 'Invoice draft not found.', 404);
        }

        $links->deactivateByInvoiceDraft($id);
        $auditLogs->add($userId, 'invoice.voided', 'invoice_draft', $id, ['status' => InvoiceDraftStatus::Void->value]);

        $draft = $this->normalizeDates($draft, ['created_at', 'updated_at']);

        return $this->jsonSuccess($draft);
    }

    #[Route('/{id}/email', methods: ['POST'])]
    public function email(
        Request $request,
        InvoiceDraftRepositoryInterface $drafts,
        ClientRepositoryInterface $clients,
        InvoicePdfRenderer $renderer,
        SimpleMailer $mailer,
        AuditLogRepositoryInterface $auditLogs,
        int $id
    ): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $draft = $drafts->findById($userId, $id);
        if ($draft === null) {
            return $this->jsonError('not_found', 'Invoice draft not found.', 404);
        }

        if (in_array($draft['status'] ?? null, [InvoiceDraftStatus::Paid->value, InvoiceDraftStatus::Void->value], true)) {
            return $this->jsonError('invalid_status', 'Paid or void invoices cannot be emailed.', 409);
        }

        $client = $clients->findById($userId, (int) $draft['client_id']);
        if ($client === null) {
            return $this->jsonError('not_found', 'Client not found.', 404);
        }

        $email = isset($client['email']) ? trim((string) $client['email']) : '';
        if ($email === '') {
            return $this->jsonError('missing_email', 'Client email is required to send.', 409);
        }

        try {
            $payload = $this->parseJson($request);
        } catch (JsonException $exception) {
            return $this->jsonError('invalid_json', $exception->getMessage(), 400);
        }

        $subject = isset($payload['subject']) ? trim((string) $payload['subject']) : '';
        if ($subject === '') {
            $subject = sprintf('Invoice #%d', (int) $draft['id']);
        }

        $message = isset($payload['message']) ? trim((string) $payload['message']) : '';
        if ($message === '') {
            $name = $client['name'] ?? 'there';
            $amount = sprintf('%s %.2f', $draft['currency'], ((int) $draft['amount_cents']) / 100);
            $message = sprintf(
                "Hi %s,\n\nAttached is invoice #%d for %s.\n\nThanks,\nBackOffice Autopilot",
                $name,
                (int) $draft['id'],
                $amount
            );
        }

        if (!$mailer->isConfigured()) {
            return $this->jsonError('not_configured', 'Mail sender is not configured.', 409);
        }

        $lines = $drafts->findLines((int) $draft['id']);
        $pdf = $renderer->render($draft, $client, $lines);
        $filename = sprintf('invoice-%d.pdf', (int) $draft['id']);

        try {
            $mailer->send($email, $subject, $message, $filename, $pdf, 'application/pdf');
        } catch (\Throwable $exception) {
            return $this->jsonError('email_failed', $exception->getMessage(), 500);
        }

        if (($draft['status'] ?? null) === InvoiceDraftStatus::Draft->value) {
            $draft = $drafts->updateStatus($userId, $id, InvoiceDraftStatus::Sent->value) ?? $draft;
        }

        $auditLogs->add($userId, 'invoice.emailed', 'invoice_draft', $id, ['to' => $email]);

        $draft = $this->normalizeDates($draft, ['created_at', 'updated_at']);

        return $this->jsonSuccess(['sent' => true, 'invoice' => $draft]);
    }

    #[Route('/{id}/pdf', methods: ['GET'])]
    public function pdf(
        Request $request,
        InvoiceDraftRepositoryInterface $drafts,
        ClientRepositoryInterface $clients,
        InvoicePdfRenderer $renderer,
        int $id
    ): Response {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $draft = $drafts->findById($userId, $id);
        if ($draft === null) {
            return $this->jsonError('not_found', 'Invoice draft not found.', 404);
        }

        $client = $clients->findById($userId, (int) $draft['client_id']);
        if ($client === null) {
            return $this->jsonError('not_found', 'Client not found.', 404);
        }

        $lines = $drafts->findLines((int) $draft['id']);
        $pdf = $renderer->render($draft, $client, $lines);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename=\"invoice-%s.pdf\"', $draft['id']),
        ]);
    }

    #[Route('/bulk', methods: ['GET'])]
    public function bulk(Request $request, InvoiceDraftRepositoryInterface $drafts): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $from = $request->query->get('from');
        $to = $request->query->get('to');

        $rows = $drafts->listWithLinesByDateRange(
            $userId,
            $from !== null ? (string) $from : null,
            $to !== null ? (string) $to : null
        );

        $rows = array_map(
            fn (array $row) => $this->normalizeDates($row, ['created_at', 'updated_at']),
            $rows
        );

        return $this->jsonSuccess($rows);
    }

    #[Route('/bulk/mark-sent', methods: ['POST'])]
    public function bulkMarkSent(Request $request, InvoiceDraftRepositoryInterface $drafts): JsonResponse
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
        $ids = $payload['ids'] ?? [];

        if (!is_array($ids) || $ids === []) {
            return $this->jsonError('invalid_ids', 'ids must be a non-empty array.', 422);
        }

        $ids = array_values(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0));
        if ($ids === []) {
            return $this->jsonError('invalid_ids', 'ids must contain positive integers.', 422);
        }

        $updated = $drafts->bulkUpdateStatus($userId, $ids, InvoiceDraftStatus::Sent->value);

        return $this->jsonSuccess(['updated' => $updated]);
    }

    #[Route('/{id}/payment-link', methods: ['POST'])]
    public function paymentLink(
        Request $request,
        InvoiceDraftRepositoryInterface $drafts,
        PaymentLinkRepositoryInterface $links,
        StripeBillingService $stripe,
        int $id
    ): JsonResponse {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        if (!$stripe->isConfigured()) {
            return $this->jsonError('not_configured', 'Stripe is not configured.', 409);
        }

        $draft = $drafts->findById($userId, $id);
        if ($draft === null) {
            return $this->jsonError('not_found', 'Invoice draft not found.', 404);
        }

        if (in_array($draft['status'] ?? null, [InvoiceDraftStatus::Paid->value, InvoiceDraftStatus::Void->value], true)) {
            return $this->jsonError('invalid_status', 'Payment links are unavailable for paid or void invoices.', 409);
        }

        $existing = $links->findByInvoiceDraft((int) $draft['id']);
        if ($existing !== null) {
            return $this->jsonSuccess($existing);
        }

        $amountCents = (int) $draft['amount_cents'];
        if ($amountCents <= 0) {
            return $this->jsonError('invalid_amount', 'Invoice amount must be greater than 0.', 422);
        }

        $currency = (string) $draft['currency'];
        $description = sprintf('Invoice draft #%d', (int) $draft['id']);
        $paymentLink = $stripe->createPaymentLink($amountCents, $currency, $description);

        $record = $links->create([
            'invoice_draft_id' => (int) $draft['id'],
            'provider' => 'stripe',
            'provider_id' => $paymentLink['id'],
            'url' => $paymentLink['url'],
            'status' => 'active',
        ]);

        return $this->jsonSuccess($record, 201);
    }

    #[Route('/{id}/payment-link/refresh', methods: ['POST'])]
    public function refreshPaymentLink(
        Request $request,
        InvoiceDraftRepositoryInterface $drafts,
        PaymentLinkRepositoryInterface $links,
        StripeBillingService $stripe,
        int $id
    ): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        if (!$stripe->isConfigured()) {
            return $this->jsonError('not_configured', 'Stripe is not configured.', 409);
        }

        $draft = $drafts->findById($userId, $id);
        if ($draft === null) {
            return $this->jsonError('not_found', 'Invoice draft not found.', 404);
        }

        if (in_array($draft['status'] ?? null, [InvoiceDraftStatus::Paid->value, InvoiceDraftStatus::Void->value], true)) {
            return $this->jsonError('invalid_status', 'Payment links are unavailable for paid or void invoices.', 409);
        }

        $amountCents = (int) $draft['amount_cents'];
        if ($amountCents <= 0) {
            return $this->jsonError('invalid_amount', 'Invoice amount must be greater than 0.', 422);
        }

        $links->deactivateByInvoiceDraft((int) $draft['id']);

        $currency = (string) $draft['currency'];
        $description = sprintf('Invoice draft #%d', (int) $draft['id']);
        $paymentLink = $stripe->createPaymentLink($amountCents, $currency, $description);

        $record = $links->create([
            'invoice_draft_id' => (int) $draft['id'],
            'provider' => 'stripe',
            'provider_id' => $paymentLink['id'],
            'url' => $paymentLink['url'],
            'status' => 'active',
        ]);

        return $this->jsonSuccess($record, 201);
    }
}
