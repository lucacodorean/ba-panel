<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports\Enums;

/**
 * The complexity of the request.
 */
enum Complexity: string
{
    case Trivial = 'trivial';

    case Low = 'low';

    case Medium = 'medium';

    case High = 'high';

    case VeryHigh = 'very_high';
}
