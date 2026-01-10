<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Billing\BillingPlanResolver;
use App\Domain\Repository\BillingSubscriptionRepositoryInterface;
use App\Infrastructure\Billing\PayPalBillingService;
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
        PayPalBillingService $paypal,
        BillingSubscriptionRepositoryInterface $subscriptions,
        BillingPlanResolver $plans
    ): JsonResponse {
        return $this->handlePaypalCheckout($request, $paypal, $subscriptions, $plans);
    }

    #[Route('/status', methods: ['GET'])]
    public function status(Request $request, BillingSubscriptionRepositoryInterface $subscriptions): JsonResponse
    {
        return $this->handleStatus($request, $subscriptions);
    }

    #[Route('/portal', methods: ['POST'])]
    public function portal(Request $request, PayPalBillingService $paypal): JsonResponse
    {
        return $this->handleManage($request, $paypal);
    }

    #[Route('/paypal/checkout', methods: ['POST'])]
    public function paypalCheckout(
        Request $request,
        PayPalBillingService $paypal,
        BillingSubscriptionRepositoryInterface $subscriptions,
        BillingPlanResolver $plans
    ): JsonResponse {
        return $this->handlePaypalCheckout($request, $paypal, $subscriptions, $plans);
    }

    #[Route('/paypal/status', methods: ['GET'])]
    public function paypalStatus(Request $request, BillingSubscriptionRepositoryInterface $subscriptions): JsonResponse
    {
        return $this->handleStatus($request, $subscriptions);
    }

    #[Route('/paypal/confirm', methods: ['POST'])]
    public function paypalConfirm(
        Request $request,
        PayPalBillingService $paypal,
        BillingSubscriptionRepositoryInterface $subscriptions,
        BillingPlanResolver $plans
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

        $existing = $subscriptions->findByUser($userId, 'paypal');
        $planValue = $this->resolvePlanValue(
            $plans,
            isset($payload['plan']) ? (string) $payload['plan'] : null,
            $existing['plan'] ?? null,
            true,
            true
        );
        if ($planValue === null) {
            return $this->jsonError('invalid_plan', 'Plan is invalid.', 422);
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
            'plan' => $planValue,
        ]);

        $row = $this->normalizeDates($row, ['created_at', 'updated_at', 'current_period_end']);

        return $this->jsonSuccess($row);
    }

    #[Route('/paypal/manage', methods: ['POST'])]
    public function paypalManage(Request $request, PayPalBillingService $paypal): JsonResponse
    {
        return $this->handleManage($request, $paypal);
    }

    private function handlePaypalCheckout(
        Request $request,
        PayPalBillingService $paypal,
        BillingSubscriptionRepositoryInterface $subscriptions,
        BillingPlanResolver $plans
    ): JsonResponse {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        if (!$paypal->isConfigured()) {
            return $this->jsonError('not_configured', 'PayPal is not configured.', 409);
        }

        try {
            $payload = $this->parseJson($request);
        } catch (JsonException $exception) {
            return $this->jsonError('invalid_json', $exception->getMessage(), 400);
        }

        $planValue = $this->resolvePlanValue($plans, $payload['plan'] ?? null, null, true, true);
        if ($planValue === null) {
            return $this->jsonError('invalid_plan', 'Plan is invalid.', 422);
        }

        if (!$paypal->isPlanConfigured($planValue)) {
            return $this->jsonError('not_configured', 'PayPal plan is not configured for this plan.', 409);
        }

        $session = $paypal->createSubscription($userId, $planValue);
        $subscriptions->upsert($userId, 'paypal', [
            'subscription_id' => $session['id'],
            'status' => 'pending',
            'plan' => $planValue,
        ]);

        return $this->jsonSuccess(['url' => $session['approve_url'], 'subscription_id' => $session['id']]);
    }

    private function handleStatus(
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

    private function handleManage(Request $request, PayPalBillingService $paypal): JsonResponse
    {
        $userId = $this->requireUserId($request);
        if ($userId === null) {
            return $this->jsonError('unauthorized', 'Authentication required.', 401);
        }

        if (!$paypal->isManageConfigured()) {
            return $this->jsonError('not_configured', 'PayPal manage URL is not configured.', 409);
        }

        return $this->jsonSuccess(['url' => $paypal->getManageUrl()]);
    }

    private function resolvePlanValue(
        BillingPlanResolver $plans,
        ?string $requestedPlan,
        ?string $fallbackPlan,
        bool $useDefault,
        bool $strictRequested
    ): ?string {
        if ($requestedPlan !== null && trim($requestedPlan) !== '') {
            $resolved = $plans->resolve($requestedPlan);
            if ($resolved !== null) {
                return $resolved->value;
            }

            if ($strictRequested) {
                return null;
            }
        }

        if ($fallbackPlan !== null && trim($fallbackPlan) !== '') {
            $fallback = $plans->resolve($fallbackPlan);
            if ($fallback !== null) {
                return $fallback->value;
            }
        }

        return $useDefault ? $plans->resolve(null)?->value : null;
    }
}
