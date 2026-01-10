<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Domain\Enum\BillingPlan;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class BillingPlanResolver
{
    public function __construct(
        #[Autowire('%app.billing_default_plan%')] private string $defaultPlan
    ) {
    }

    public function resolve(?string $plan): ?BillingPlan
    {
        $candidate = $this->normalize($plan);
        if ($candidate === '') {
            $candidate = $this->normalize($this->defaultPlan);
        }

        if ($candidate === '') {
            return null;
        }

        foreach (BillingPlan::cases() as $case) {
            if ($case->value === $candidate) {
                return $case;
            }
        }

        return null;
    }

    private function normalize(?string $value): string
    {
        return strtolower(trim((string) $value));
    }
}
