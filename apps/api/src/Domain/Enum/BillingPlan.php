<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum BillingPlan: string
{
    case Starter = 'starter';
    case Pro = 'pro';
}
