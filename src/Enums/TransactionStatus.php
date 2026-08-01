<?php

declare(strict_types=1);

namespace CheckCommerce\Enums;

/**
 * Lifecycle statuses reported for a transaction.
 *
 * Case order matches the API's enum declaration so ordinal values map correctly.
 */
enum TransactionStatus: string
{
    use ApiEnum;

    case Processed = 'Processed';
    case Originated = 'Originated';
    case Funded = 'Funded';
    case Returned = 'Returned';
    case Nsf = 'NSF';
    case ChargeBack = 'ChargeBack';
    case Invalid = 'Invalid';
    case Declined = 'Declined';
    case Refunded = 'Refunded';
    case Credit = 'Credit';
    case Incomplete = 'Incomplete';
    case Cancelled = 'Cancelled';
    case BoException = 'BOException';
    case Downloaded = 'Downloaded';
    case CreditOriginated = 'CreditOriginated';
    case CreditDownloaded = 'CreditDownloaded';
    case CreditReturned = 'CreditReturned';
    case CreditFunded = 'CreditFunded';
    case DeclinedDownloaded = 'DeclinedDownloaded';
    case FirstRecycle = 'FirstRecycle';
    case SecondRecycle = 'SecondRecycle';
    case FailedRecycle = 'FailedRecycle';
    case Authentication = 'Authentication';
    case DownloadedAuthentication = 'DownloadedAuthentication';
    case BatchVerificationPending = 'BatchVerificationPending';
    case BatchVerificationProcessing = 'BatchVerificationProcessing';
    case IncompleteScan = 'IncompleteScan';
    case PendingApproval = 'PendingApproval';
    case PendingHostedPaymentPage = 'PendingHostedPaymentPage';
}
