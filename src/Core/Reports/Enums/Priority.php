<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports\Enums;

/**
 * The priority of the request.
 */
enum Priority: string
{
    case Critical = 'critical';

    case High = 'high';

    case Medium = 'medium';

    case Low = 'low';
}
