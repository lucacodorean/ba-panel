<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports\Enums;

/**
 * The impact level on a non-functional area.
 */
enum ImpactLevel: string
{
    case None = 'none';

    case Low = 'low';

    case Medium = 'medium';

    case High = 'high';
}
