<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Repository\BillingSubscriptionRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Billing\StripeBillingService;
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

    #[Route('/webhook', methods: ['POST'])]
    public function webhook(
        Request $request,
        StripeBillingService $stripe,
        BillingSubscriptionRepositoryInterface $subscriptions
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

        return $this->jsonSuccess(['received' => true]);
    }
}
