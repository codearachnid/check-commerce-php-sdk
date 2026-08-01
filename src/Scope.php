<?php

declare(strict_types=1);

namespace CheckCommerce;

/**
 * OAuth-style scopes that can be requested when authenticating.
 *
 * When no scopes are requested, the issued token carries every scope
 * configured for the API key.
 */
enum Scope: string
{
    /** Access to transaction endpoints. */
    case Transactions = 'obp.tran';

    /** Access to merchant boarding endpoints. */
    case Boarding = 'obp.board';

    /** Access to hosted payment page endpoints. */
    case HostedPages = 'obp.hp';
}
