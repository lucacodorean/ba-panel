<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Tests\Support\EnvironmentVariables;

const BAP_ENV_NAMES = ['BAP_ENABLED', 'BAP_AI_PROVIDER', 'BAP_AI_MODEL', 'BAP_AI_KEY', 'BAP_DOCS_ARRAY'];

/**
 * Load the package config file with the given environment variables.
 *
 * @param  array<string, string|null>  $variables
 * @return array<string, mixed>
 */
function loadBaPanelConfig(array $variables = []): array
{
    $environment = new EnvironmentVariables;

    try {
        foreach (BAP_ENV_NAMES as $name) {
            $environment->set($name, $variables[$name] ?? null);
        }

        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 2).'/config/ba-panel.php';

        return $config;
    } finally {
        $environment->restore();
    }
}

it('exists at the contract path', function (): void {
    expect(dirname(__DIR__, 2).'/config/ba-panel.php')->toBeFile();
});

it('uses defaults when no environment variable is set', function (): void {
    expect(loadBaPanelConfig())->toBe([
        'enabled' => false,
        'ai' => [
            'provider' => null,
            'model' => null,
            'key' => null,
        ],
        'docs' => [],
    ]);
});

it('reads BAP_ENABLED as a boolean', function (string $value, bool $expected): void {
    expect(loadBaPanelConfig(['BAP_ENABLED' => $value])['enabled'])->toBe($expected);
})->with([
    'true' => ['true', true],
    'false' => ['false', false],
]);

it('reads the AI settings from the environment', function (): void {
    $config = loadBaPanelConfig([
        'BAP_AI_PROVIDER' => 'openai',
        'BAP_AI_MODEL' => 'gpt-test',
        'BAP_AI_KEY' => 'secret-key',
    ]);

    expect($config['ai'])->toBe([
        'provider' => 'openai',
        'model' => 'gpt-test',
        'key' => 'secret-key',
    ]);
});

it('parses BAP_DOCS_ARRAY as a comma-separated list', function (string $value, array $expected): void {
    expect(loadBaPanelConfig(['BAP_DOCS_ARRAY' => $value])['docs'])->toBe($expected);
})->with([
    'single path' => ['docs', ['docs']],
    'multiple paths' => ['docs,README.md,/var/docs', ['docs', 'README.md', '/var/docs']],
    'trims items' => ['  docs , README.md  ,  /var/docs ', ['docs', 'README.md', '/var/docs']],
    'drops empty items and re-indexes' => [',docs,, ,README.md,', ['docs', 'README.md']],
    'only separators' => [' , ,, ', []],
    'empty string' => ['', []],
]);

it('does not leak environment variables after loading', function (): void {
    loadBaPanelConfig(['BAP_AI_PROVIDER' => 'openai']);

    expect(getenv('BAP_AI_PROVIDER'))->toBeFalse()
        ->and($_ENV)->not->toHaveKey('BAP_AI_PROVIDER')
        ->and($_SERVER)->not->toHaveKey('BAP_AI_PROVIDER');
});
