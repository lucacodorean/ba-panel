<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Exceptions;

use RuntimeException;

final class InvalidConfigurationException extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(private readonly array $errors)
    {
        $lines = array_map(static fn (string $error): string => '- '.$error, $errors);

        parent::__construct(implode("\n", ['Invalid ba-panel configuration:', ...$lines]));
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
