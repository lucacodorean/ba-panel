<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Exceptions;

use InvalidArgumentException;

/**
 * The feasibility request is not valid. The message is safe to show to the user.
 */
final class InvalidFeasibilityRequestException extends InvalidArgumentException
{
}
