<?php

declare(strict_types=1);

namespace CheckCommerce\Enums;

enum BatchStatus: string
{
    use ApiEnum;

    case Pending = 'Pending';
    case Declined = 'Declined';
    case Processing = 'Processing';
    case Processed = 'Processed';
}
