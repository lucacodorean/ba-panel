<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports\Enums;

/**
 * The overall feasibility verdict of the request.
 */
enum OverallVerdict: string
{
    case Feasible = 'feasible';

    case FeasibleWithConditions = 'feasible_with_conditions';

    case NotFeasible = 'not_feasible';

    case NeedsMoreInfo = 'needs_more_info';
}
