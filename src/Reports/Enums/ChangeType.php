<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports\Enums;

/**
 * The type of change to a component.
 */
enum ChangeType: string
{
    case New = 'new';

    case Modify = 'modify';

    case Deprecate = 'deprecate';

    case Remove = 'remove';
}
