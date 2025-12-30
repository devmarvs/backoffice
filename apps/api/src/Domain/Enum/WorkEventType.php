<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum WorkEventType: string
{
    case Session = 'session';
    case NoShow = 'no_show';
    case Admin = 'admin';
}
