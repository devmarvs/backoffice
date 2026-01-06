<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Repository\AuditLogRepositoryInterface;
use App\Domain\Repository\BillingSubscriptionRepositoryInterface;
use App\Domain\Repository\BillingWebhookEventRepositoryInterface;
use App\Domain\Repository\InvoiceDraftRepositoryInterface;
use App\Domain\Repository\PaymentLinkRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Billing\PayPalBillingService;
use App\Infrastructure\Billing\StripeBillingService;
use App\Domain\Enum\InvoiceDraftStatus;
use DateTimeImmutable;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/billing')]
final class BillingController extends BaseApiController
{
    #[Route('/checkout', methods: ['POST'])]
    public function checkout(
        Request $request,
        StripeBillingService $stripe,
        UserRepositoryInterface $users,
        BillingSubscriptionRepositoryInterface $subscriptions
    ): JsonResponse {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        if (!$stripe->isConfigured()) {
            return $this->jsonError('not_configured', 'Stripe is not configured.', 409);
        }

        $user = $users->findById($userId);
        if ($user === null) {
            return $this->jsonError('not_found', 'User not found.', 404);
        }

        $session = $stripe->createCheckoutSession($userId, (string) $user['email']);
        $subscriptions->upsert($userId, 'stripe', [
            'status' => 'pending',
        ]);

        return $this->jsonSuccess(['url' => $session['url'], 'session_id' => $session['id']]);
    }

    #[Route('/status', methods: ['GET'])]
    public function status(Request $request, BillingSubscriptionRepositoryInterface $subscriptions): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $subscription = $subscriptions->findByUser($userId, 'stripe');
        if ($subscription !== null) {
            $subscription = $this->normalizeDates($subscription, ['created_at', 'updated_at', 'current_period_end']);
        }

        return $this->jsonSuccess($subscription ?? ['status' => 'inactive']);
    }

    #[Route('/portal', methods: ['POST'])]
    public function portal(
        Request $request,
        StripeBillingService $stripe,
        BillingSubscriptionRepositoryInterface $subscriptions
    ): JsonResponse {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        if (!$stripe->isPortalConfigured()) {
            return $this->jsonError('not_configured', 'Stripe billing portal is not configured.', 409);
        }

        $subscription = $subscriptions->findByUser($userId, 'stripe');
        $customerId = $subscription['customer_id'] ?? null;
        if ($customerId === null || $customerId === '') {
            return $this->jsonError('no_customer', 'No Stripe customer is linked yet.', 409);
        }

        $session = $stripe->createPortalSession((string) $customerId);

        return $this->jsonSuccess(['url' => $session['url']]);
    }

    #[Route('/paypal/checkout', methods: ['POST'])]
    public function paypalCheckout(
        Request $request,
        PayPalBillingService $paypal,
        BillingSubscriptionRepositoryInterface $subscriptions
    ): JsonResponse {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        if (!$paypal->isConfigured()) {
            return $this->jsonError('not_configured', 'PayPal is not configured.', 409);
        }

        $session = $paypal->createSubscription($userId);
        $subscriptions->upsert($userId, 'paypal', [
            'subscription_id' => $session['id'],
            'status' => 'pending',
        ]);

        return $this->jsonSuccess(['url' => $session['approve_url'], 'subscription_id' => $session['id']]);
    }

    #[Route('/paypal/status', methods: ['GET'])]
    public function paypalStatus(
        Request $request,
        BillingSubscriptionRepositoryInterface $subscriptions
    ): JsonResponse {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        $subscription = $subscriptions->findByUser($userId, 'paypal');
        if ($subscription !== null) {
            $subscription = $this->normalizeDates($subscription, ['created_at', 'updated_at', 'current_period_end']);
        }

        return $this->jsonSuccess($subscription ?? ['status' => 'inactive']);
    }

    #[Route('/paypal/confirm', methods: ['POST'])]
    public function paypalConfirm(
        Request $request,
        PayPalBillingService $paypal,
        BillingSubscriptionRepositoryInterface $subscriptions
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

        $subscriptionId = isset($payload['subscription_id']) ? trim((string) $payload['subscription_id']) : '';
        if ($subscriptionId === '') {
            return $this->jsonError('invalid_subscription', 'subscription_id is required.', 422);
        }

        if (!$paypal->isConfigured()) {
            return $this->jsonError('not_configured', 'PayPal is not configured.', 409);
        }

        $data = $paypal->getSubscription($subscriptionId);
        $status = strtolower((string) ($data['status'] ?? 'pending'));
        $payerId = $data['subscriber']['payer_id'] ?? null;
        $nextBilling = $data['billing_info']['next_billing_time'] ?? null;

        $row = $subscriptions->upsert($userId, 'paypal', [
            'customer_id' => $payerId,
            'subscription_id' => $subscriptionId,
            'status' => $status,
            'current_period_end' => $nextBilling,
        ]);

        $row = $this->normalizeDates($row, ['created_at', 'updated_at', 'current_period_end']);

        return $this->jsonSuccess($row);
    }

    #[Route('/paypal/manage', methods: ['POST'])]
    public function paypalManage(
        Request $request,
        PayPalBillingService $paypal
    ): JsonResponse {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        if (!$paypal->isManageConfigured()) {
            return $this->jsonError('not_configured', 'PayPal manage URL is not configured.', 409);
        }

        return $this->jsonSuccess(['url' => $paypal->getManageUrl()]);
    }

    #[Route('/webhook', methods: ['POST'])]
    public function webhook(
        Request $request,
        StripeBillingService $stripe,
        BillingSubscriptionRepositoryInterface $subscriptions,
        BillingWebhookEventRepositoryInterface $webhookEvents,
        PaymentLinkRepositoryInterface $paymentLinks,
        InvoiceDraftRepositoryInterface $drafts,
        AuditLogRepositoryInterface $auditLogs
    ): JsonResponse {
        $payload = $request->getContent();
        $signature = $request->headers->get('stripe-signature');

        if ($signature === null) {
            return $this->jsonError('missing_signature', 'Stripe signature header missing.', 400);
        }

        try {
            $event = $stripe->constructEvent($payload, $signature);
        } catch (\Throwable $exception) {
            return $this->jsonError('invalid_signature', 'Stripe signature validation failed.', 400);
        }

        $type = $event->type ?? '';
        $data = $event->data->object ?? null;

        $eventId = $event->id ?? null;
        if ($eventId === null || $eventId === '') {
            return $this->jsonError('invalid_event', 'Stripe event id is missing.', 400);
        }

        $payloadData = json_decode($payload, true);
        if (!is_array($payloadData)) {
            $payloadData = [];
        }

        $webhookEvents->createIfNotExists('stripe', (string) $eventId, (string) $type, $payloadData);
        if (!$webhookEvents->markProcessing('stripe', (string) $eventId)) {
            return $this->jsonSuccess(['received' => true, 'duplicate' => true]);
        }

        try {
            if ($type === 'checkout.session.completed' && $data !== null) {
                $userId = isset($data->client_reference_id) ? (int) $data->client_reference_id : null;
                if ($userId !== null) {
                    $subscriptions->upsert($userId, 'stripe', [
                        'customer_id' => $data->customer ?? null,
                        'subscription_id' => $data->subscription ?? null,
                        'status' => 'active',
                        'current_period_end' => null,
                    ]);
                }

                if (isset($data->payment_link) && $data->payment_link !== null) {
                    $link = $paymentLinks->findWithInvoiceByProviderId('stripe', (string) $data->payment_link);
                    if ($link !== null) {
                        if (($link['status'] ?? '') !== 'paid') {
                            $paymentLinks->updateStatus((int) $link['id'], 'paid');
                        }

                        $invoiceStatus = $link['invoice_status'] ?? null;
                        if (!in_array($invoiceStatus, [InvoiceDraftStatus::Paid->value, InvoiceDraftStatus::Void->value], true)) {
                            $updated = $drafts->updateStatus((int) $link['user_id'], (int) $link['invoice_draft_id'], InvoiceDraftStatus::Paid->value);
                            if ($updated !== null) {
                                $auditLogs->add(
                                    (int) $link['user_id'],
                                    'invoice.paid',
                                    'invoice_draft',
                                    (int) $link['invoice_draft_id'],
                                    ['source' => 'stripe_payment_link']
                                );
                            }
                        }
                    }
                }
            }

            if (in_array($type, ['checkout.session.async_payment_failed', 'payment_intent.payment_failed', 'payment_intent.canceled'], true)) {
                $paymentLinkId = $data->payment_link ?? null;
                if ($paymentLinkId) {
                    $this->updatePaymentLinkStatus((string) $paymentLinkId, 'failed', $paymentLinks, $auditLogs);
                }
            }

            if (in_array($type, ['charge.refunded', 'charge.refund.updated'], true)) {
                $paymentLinkId = $data->payment_link ?? null;
                if ($paymentLinkId) {
                    $link = $this->updatePaymentLinkStatus((string) $paymentLinkId, 'refunded', $paymentLinks, $auditLogs);
                    if ($link !== null) {
                        $auditLogs->add(
                            (int) $link['user_id'],
                            'invoice.refunded',
                            'invoice_draft',
                            (int) $link['invoice_draft_id'],
                            ['source' => 'stripe']
                        );
                    }
                }
            }

            if ($type === 'charge.dispute.created') {
                $paymentLinkId = $data->payment_link ?? null;
                if ($paymentLinkId) {
                    $link = $this->updatePaymentLinkStatus((string) $paymentLinkId, 'disputed', $paymentLinks, $auditLogs);
                    if ($link !== null) {
                        $auditLogs->add(
                            (int) $link['user_id'],
                            'invoice.disputed',
                            'invoice_draft',
                            (int) $link['invoice_draft_id'],
                            ['source' => 'stripe']
                        );
                    }
                }
            }

            if ($type === 'customer.subscription.updated' || $type === 'customer.subscription.deleted') {
                if ($data !== null && isset($data->id)) {
                    $record = $subscriptions->findBySubscriptionId('stripe', (string) $data->id);
                    if ($record !== null) {
                        $currentPeriodEnd = null;
                        if (isset($data->current_period_end)) {
                            $currentPeriodEnd = (new DateTimeImmutable())->setTimestamp((int) $data->current_period_end)->format('Y-m-d H:i:sP');
                        }

                        $subscriptions->upsert((int) $record['user_id'], 'stripe', [
                            'customer_id' => $data->customer ?? null,
                            'subscription_id' => (string) $data->id,
                            'status' => $type === 'customer.subscription.deleted' ? 'canceled' : (string) $data->status,
                            'current_period_end' => $currentPeriodEnd,
                        ]);
                    }
                }
            }

            $webhookEvents->markProcessed('stripe', (string) $eventId);
        } catch (\Throwable $exception) {
            $webhookEvents->markFailed('stripe', (string) $eventId, $exception->getMessage());

            return $this->jsonError('processing_failed', 'Stripe webhook processing failed.', 500);
        }

        return $this->jsonSuccess(['received' => true]);
    }

    private function updatePaymentLinkStatus(
        string $providerId,
        string $status,
        PaymentLinkRepositoryInterface $paymentLinks,
        AuditLogRepositoryInterface $auditLogs
    ): ?array {
        $link = $paymentLinks->findWithInvoiceByProviderId('stripe', $providerId);
        if ($link === null) {
            return null;
        }

        $paymentLinks->updateStatus((int) $link['id'], $status);
        $auditLogs->add(
            (int) $link['user_id'],
            'payment_link.' . $status,
            'payment_link',
            (int) $link['id'],
            ['invoice_draft_id' => (int) $link['invoice_draft_id']]
        );

        return $link;
    }
}
