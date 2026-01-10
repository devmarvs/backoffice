<?php

declare(strict_types=1);

namespace App\Tests\Application\Billing;

use App\Application\Billing\BillingAccessService;
use App\Application\Billing\BillingPlanResolver;
use App\Domain\Enum\BillingPlan;
use App\Domain\Repository\BillingSubscriptionRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class BillingAccessServiceTest extends TestCase
{
    public function testReturnsProForActiveSubscription(): void
    {
        $service = new BillingAccessService(
            new StubBillingSubscriptionRepository([
                'status' => 'active',
                'plan' => 'pro',
            ]),
            new BillingPlanResolver('starter')
        );

        self::assertSame(BillingPlan::Pro, $service->resolvePlanForUser(1));
        self::assertTrue($service->hasProAccess(1));
    }

    public function testFallsBackWhenSubscriptionIsNotActive(): void
    {
        $service = new BillingAccessService(
            new StubBillingSubscriptionRepository([
                'status' => 'pending',
                'plan' => 'pro',
            ]),
            new BillingPlanResolver('starter')
        );

        self::assertSame(BillingPlan::Starter, $service->resolvePlanForUser(1));
        self::assertFalse($service->hasProAccess(1));
    }

    public function testFallsBackWhenSubscriptionPlanIsUnknown(): void
    {
        $service = new BillingAccessService(
            new StubBillingSubscriptionRepository([
                'status' => 'active',
                'plan' => 'enterprise',
            ]),
            new BillingPlanResolver('starter')
        );

        self::assertSame(BillingPlan::Starter, $service->resolvePlanForUser(1));
    }

    public function testUsesDefaultPlanWhenNoSubscription(): void
    {
        $service = new BillingAccessService(
            new StubBillingSubscriptionRepository(null),
            new BillingPlanResolver('starter')
        );

        self::assertSame(BillingPlan::Starter, $service->resolvePlanForUser(1));
    }
}

final class StubBillingSubscriptionRepository implements BillingSubscriptionRepositoryInterface
{
    public function __construct(private ?array $row)
    {
    }

    public function findByUser(int $userId, string $provider): ?array
    {
        return $this->row;
    }

    public function findBySubscriptionId(string $provider, string $subscriptionId): ?array
    {
        return null;
    }

    public function upsert(int $userId, string $provider, array $data): array
    {
        return $data;
    }
}
