<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Domain\Enum\BillingPlan;
use App\Domain\Repository\BillingSubscriptionRepositoryInterface;

final class BillingAccessService
{
    private const ACTIVE_STATUSES = ['active'];

    public function __construct(
        private BillingSubscriptionRepositoryInterface $subscriptions,
        private BillingPlanResolver $plans
    ) {
    }

    public function resolvePlanForUser(int $userId): ?BillingPlan
    {
        $subscription = $this->subscriptions->findByUser($userId, 'paypal');
        if ($subscription !== null && $this->isActiveStatus($subscription['status'] ?? null)) {
            $plan = $this->plans->resolve($subscription['plan'] ?? null);
            if ($plan !== null) {
                return $plan;
            }
        }

        return $this->plans->resolve(null);
    }

    public function hasProAccess(int $userId): bool
    {
        return $this->resolvePlanForUser($userId) === BillingPlan::Pro;
    }

    private function isActiveStatus(?string $status): bool
    {
        $status = strtolower(trim((string) $status));

        return $status !== '' && in_array($status, self::ACTIVE_STATUSES, true);
    }
}
