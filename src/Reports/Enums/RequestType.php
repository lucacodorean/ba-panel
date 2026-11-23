<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports\Enums;

/**
 * The type of the request that the report assesses.
 */
enum RequestType: string
{
    case ChangeRequest = 'change_request';

    case FeatureRequest = 'feature_request';
}
