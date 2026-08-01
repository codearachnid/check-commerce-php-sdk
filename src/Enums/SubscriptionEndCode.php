<?php

declare(strict_types=1);

namespace CheckCommerce\Enums;

/**
 * Defines how a recurring subscription ends.
 */
enum SubscriptionEndCode: string
{
    use ApiEnum;

    /** The subscription runs until cancelled. */
    case Indefinite = 'Indefinite';

    /** The subscription ends on a given date (`endTime`). */
    case EndDate = 'EndDate';

    /** The subscription ends after a total amount is reached (`amountTotal`). */
    case AmountTotal = 'AmountTotal';
}
