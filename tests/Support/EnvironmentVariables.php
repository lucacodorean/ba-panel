<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Tests\Support;

/**
 * Set environment variables for a test and restore the previous values afterwards.
 */
final class EnvironmentVariables
{
    /**
     * @var array<string, array{putenv: string|false, env: mixed, server: mixed, inEnv: bool, inServer: bool}>
     */
    private array $original = [];

    public function set(string $name, ?string $value): void
    {
        $this->remember($name);

        if ($value === null) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);

            return;
        }

        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    public function restore(): void
    {
        foreach ($this->original as $name => $state) {
            if ($state['putenv'] === false) {
                putenv($name);
            } else {
                putenv($name.'='.$state['putenv']);
            }

            if ($state['inEnv']) {
                $_ENV[$name] = $state['env'];
            } else {
                unset($_ENV[$name]);
            }

            if ($state['inServer']) {
                $_SERVER[$name] = $state['server'];
            } else {
                unset($_SERVER[$name]);
            }
        }

        $this->original = [];
    }

    private function remember(string $name): void
    {
        if (array_key_exists($name, $this->original)) {
            return;
        }

        $this->original[$name] = [
            'putenv' => getenv($name),
            'env' => $_ENV[$name] ?? null,
            'server' => $_SERVER[$name] ?? null,
            'inEnv' => array_key_exists($name, $_ENV),
            'inServer' => array_key_exists($name, $_SERVER),
        ];
    }
}
