<?php

declare(strict_types=1);

namespace CheckCommerce\Enums;

/**
 * Field delimiter for uploaded batch transaction files (`txt`/`csv`).
 */
enum FileDelimiter: string
{
    use ApiEnum;

    case Comma = 'Comma';
    case SemiColon = 'SemiColon';
    case Pipe = 'Pipe';
}
