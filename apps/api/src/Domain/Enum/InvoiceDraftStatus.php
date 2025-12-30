<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum InvoiceDraftStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Void = 'void';
}
