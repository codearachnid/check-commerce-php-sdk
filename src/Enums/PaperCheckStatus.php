<?php

declare(strict_types=1);

namespace CheckCommerce\Enums;

/**
 * Processing statuses for paper check transactions.
 */
enum PaperCheckStatus: string
{
    use ApiEnum;

    case Unknown = 'Unknown';
    case Pending = 'Pending';
    case Accepted = 'Accepted';
    case Processed = 'Processed';
    case Rejected = 'Rejected';
    case Resubmitted = 'Resubmitted';
    case Cancelled = 'Cancelled';
    case Sent = 'Sent';
}
