<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports\Enums;

/**
 * The feasibility rating of one dimension.
 */
enum FeasibilityRating: string
{
    case Feasible = 'feasible';

    case FeasibleWithConditions = 'feasible_with_conditions';

    case NotFeasible = 'not_feasible';
}
