<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports\Enums;

/**
 * The architecture layer of a component.
 */
enum Layer: string
{
    case Frontend = 'frontend';

    case Backend = 'backend';

    case Database = 'database';

    case Infra = 'infra';

    case Integration = 'integration';

    case CiCd = 'ci_cd';
}
