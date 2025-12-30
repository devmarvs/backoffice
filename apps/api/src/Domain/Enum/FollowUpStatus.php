<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum FollowUpStatus: string
{
    case Open = 'open';
    case Done = 'done';
    case Dismissed = 'dismissed';
}
