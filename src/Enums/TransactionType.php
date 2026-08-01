<?php

declare(strict_types=1);

namespace CheckCommerce\Enums;

/**
 * Operations that can be performed through the transaction endpoint.
 */
enum TransactionType: string
{
    use ApiEnum;

    case Authorize = 'Authorize';
    case Cancel = 'Cancel';
    case Credit = 'Credit';
    case Debit = 'Debit';
    case LookupBank = 'LookupBank';
    case Prenote = 'Prenote';
    case PrenoteDebit = 'PrenoteDebit';
    case PrenoteCredit = 'PrenoteCredit';
    case Refund = 'Refund';
    case Resubmit = 'Resubmit';
    case Settle = 'Settle';
    case Status = 'Status';
    case StatusDetail = 'StatusDetail';
    case Void = 'Void';
    case AuthStatus = 'AuthStatus';
    case Addenda = 'Addenda';
}
