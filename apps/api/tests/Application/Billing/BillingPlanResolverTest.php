<?php

declare(strict_types=1);

namespace App\Tests\Application\Billing;

use App\Application\Billing\BillingPlanResolver;
use App\Domain\Enum\BillingPlan;
use PHPUnit\Framework\TestCase;

final class BillingPlanResolverTest extends TestCase
{
    public function testUsesDefaultPlanWhenMissing(): void
    {
        $resolver = new BillingPlanResolver('starter');

        self::assertSame(BillingPlan::Starter, $resolver->resolve(null));
    }

    public function testNormalizesPlanInput(): void
    {
        $resolver = new BillingPlanResolver('starter');

        self::assertSame(BillingPlan::Pro, $resolver->resolve('  Pro '));
    }

    public function testRejectsUnknownPlans(): void
    {
        $resolver = new BillingPlanResolver('starter');

        self::assertNull($resolver->resolve('enterprise'));
    }
}
