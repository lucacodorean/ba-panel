<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports\Enums;

/**
 * The type of a dependency.
 */
enum DependencyType: string
{
    case Library = 'library';

    case InternalService = 'internal_service';

    case ExternalApi = 'external_api';

    case Team = 'team';

    case Infrastructure = 'infrastructure';
}
