<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports\Enums;

/**
 * The impact of a change on a component.
 */
enum Impact: string
{
    case High = 'high';

    case Medium = 'medium';

    case Low = 'low';
}
