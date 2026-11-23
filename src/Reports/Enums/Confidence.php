<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports\Enums;

/**
 * The confidence level of an estimate or a verdict.
 */
enum Confidence: string
{
    case High = 'high';

    case Medium = 'medium';

    case Low = 'low';
}
