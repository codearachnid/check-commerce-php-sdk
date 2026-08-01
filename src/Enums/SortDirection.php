<?php

declare(strict_types=1);

namespace CheckCommerce\Enums;

enum SortDirection: string
{
    use ApiEnum;

    case Asc = 'Asc';
    case Desc = 'Desc';
}
