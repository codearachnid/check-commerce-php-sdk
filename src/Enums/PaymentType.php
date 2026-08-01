<?php

declare(strict_types=1);

namespace CheckCommerce\Enums;

/**
 * Payment rails supported by the transaction endpoints.
 */
enum PaymentType: string
{
    use ApiEnum;

    case Ach = 'ACH';
    case Rtp = 'RTP';
    case PaperCheck = 'PaperCheck';
    case Iat = 'IAT';
}
