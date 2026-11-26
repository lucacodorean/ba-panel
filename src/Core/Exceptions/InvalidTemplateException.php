<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Exceptions;

use InvalidArgumentException;

/**
 * The template name is not valid, or no template directory contains the template.
 */
final class InvalidTemplateException extends InvalidArgumentException
{
}
