<?php

declare(strict_types=1);

namespace CheckCommerce\Enums;

enum SubscriptionStatus: string
{
    use ApiEnum;

    case Active = 'Active';
    case Suspended = 'Suspended';
}
