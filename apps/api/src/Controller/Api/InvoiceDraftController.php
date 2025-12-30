<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Billing\InvoicePdfRenderer;
use App\Infrastructure\Billing\StripeBillingService;
use App\Domain\Enum\InvoiceDraftStatus;
use App\Domain\Repository\ClientRepositoryInterface;
use App\Domain\Repository\InvoiceDraftRepositoryInterface;
use App\Domain\Repository\PaymentLinkRepositoryInterface;
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

    #[Route('/{id}/mark-paid', methods: ['POST'])]
    public function markPaid(
        Request $request,
        InvoiceDraftRepositoryInterface $drafts,
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

        $draft = $this->normalizeDates($draft, ['created_at', 'updated_at']);

        return $this->jsonSuccess($draft);
    }

    #[Route('/{id}/send', methods: ['POST'])]
    public function markSent(
        Request $request,
        InvoiceDraftRepositoryInterface $drafts,
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

        $draft = $this->normalizeDates($draft, ['created_at', 'updated_at']);

        return $this->jsonSuccess($draft);
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
}
