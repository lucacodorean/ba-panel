<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports\Enums;

/**
 * The availability status of a dependency.
 */
enum DependencyStatus: string
{
    case Available = 'available';

    case NeedsUpgrade = 'needs_upgrade';

    case NeedsProcurement = 'needs_procurement';

    case Blocked = 'blocked';
}
