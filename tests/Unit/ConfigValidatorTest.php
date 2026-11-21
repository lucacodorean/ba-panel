<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Config\ConfigValidator;
use Lucacodorean\BaPanel\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;

/**
 * Build a valid enabled config and apply the overrides.
 *
 * @param  list<string>  $docs
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validBaPanelConfig(array $docs, array $overrides = []): array
{
    return array_replace_recursive([
        'enabled' => true,
        'ai' => [
            'provider' => 'openai',
            'model' => 'gpt-test',
            'key' => 'secret-key',
        ],
        'docs' => $docs,
    ], $overrides);
}

/**
 * Run the validator and return the collected errors. Return null when validation passes.
 *
 * @param  array<string, mixed>  $config
 * @return list<string>|null
 */
function validationErrors(string $basePath, array $config): ?array
{
    try {
        (new ConfigValidator($basePath))->validate($config);
    } catch (InvalidConfigurationException $exception) {
        /** @var list<string> $errors */
        $errors = $exception->errors();

        return $errors;
    }

    return null;
}

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk when $cleanup is true.
 */
function fixtures(bool $cleanup = false): ?TemporaryFilesystem
{
    static $fs = null;

    if ($cleanup) {
        $fs?->cleanup();
        $fs = null;

        return null;
    }

    return $fs ??= new TemporaryFilesystem;
}

afterEach(function (): void {
    fixtures(cleanup: true);
});

it('defines the exception as a RuntimeException', function (): void {
    expect((new ReflectionClass(InvalidConfigurationException::class))->isSubclassOf(RuntimeException::class))
        ->toBeTrue();
});

it('passes a valid config', function (): void {
    $file = fixtures()->file('docs/index.md', '# Docs');

    expect(validationErrors(fixtures()->root(), validBaPanelConfig([$file])))->toBeNull();
});

it('reports each missing AI setting', function (string $key, string $variable, mixed $value): void {
    $directory = fixtures()->directory('docs');
    $config = validBaPanelConfig([$directory]);

    if ($value === 'unset') {
        unset($config['ai'][$key]);
    } else {
        $config['ai'][$key] = $value;
    }

    expect(validationErrors(fixtures()->root(), $config))
        ->toBe([$variable.' is required when BAP_ENABLED is true.']);
})->with([
    'provider missing' => ['provider', 'BAP_AI_PROVIDER', 'unset'],
    'provider null' => ['provider', 'BAP_AI_PROVIDER', null],
    'provider empty' => ['provider', 'BAP_AI_PROVIDER', ''],
    'provider whitespace' => ['provider', 'BAP_AI_PROVIDER', "  \t "],
    'model missing' => ['model', 'BAP_AI_MODEL', 'unset'],
    'model null' => ['model', 'BAP_AI_MODEL', null],
    'model empty' => ['model', 'BAP_AI_MODEL', ''],
    'model whitespace' => ['model', 'BAP_AI_MODEL', '   '],
    'key missing' => ['key', 'BAP_AI_KEY', 'unset'],
    'key null' => ['key', 'BAP_AI_KEY', null],
    'key empty' => ['key', 'BAP_AI_KEY', ''],
    'key whitespace' => ['key', 'BAP_AI_KEY', '   '],
]);

it('reports empty docs', function (): void {
    expect(validationErrors(fixtures()->root(), validBaPanelConfig([])))
        ->toBe(['BAP_DOCS_ARRAY must contain at least one path when BAP_ENABLED is true.']);
});

it('reports a path that does not exist', function (): void {
    $missing = fixtures()->path('missing/readme.md');

    expect(validationErrors(fixtures()->root(), validBaPanelConfig([$missing])))
        ->toBe(['BAP_DOCS_ARRAY path does not exist: '.$missing]);
});

it('reports only the paths that do not exist', function (): void {
    $file = fixtures()->file('README.md');
    $missingA = fixtures()->path('missing-a');
    $missingB = 'missing-b/notes.md';

    expect(validationErrors(fixtures()->root(), validBaPanelConfig([$missingA, $file, $missingB])))
        ->toEqualCanonicalizing([
            'BAP_DOCS_ARRAY path does not exist: '.$missingA,
            'BAP_DOCS_ARRAY path does not exist: '.$missingB,
        ]);
});

it('accepts an existing file', function (): void {
    $file = fixtures()->file('README.md', 'content');

    expect(validationErrors(fixtures()->root(), validBaPanelConfig([$file])))->toBeNull();
});

it('accepts an existing directory', function (): void {
    $directory = fixtures()->directory('docs/guides');

    expect(validationErrors(fixtures()->root(), validBaPanelConfig([$directory])))->toBeNull();
});

it('resolves relative paths against the base path', function (): void {
    fixtures()->file('docs/index.md');
    fixtures()->directory('guides');

    expect(validationErrors(fixtures()->root(), validBaPanelConfig(['docs/index.md', 'guides', 'docs'])))
        ->toBeNull();
});

it('does not resolve relative paths against the working directory', function (): void {
    $workingDirectoryFile = 'composer.json';

    expect(getcwd().DIRECTORY_SEPARATOR.$workingDirectoryFile)->toBeFile()
        ->and(validationErrors(fixtures()->root(), validBaPanelConfig([$workingDirectoryFile])))
        ->toBe(['BAP_DOCS_ARRAY path does not exist: composer.json']);
});

it('does not resolve absolute paths against the base path', function (): void {
    fixtures()->file('etc/ba-panel-only-in-base');

    expect(validationErrors(fixtures()->root(), validBaPanelConfig(['/etc/ba-panel-only-in-base'])))
        ->toBe(['BAP_DOCS_ARRAY path does not exist: /etc/ba-panel-only-in-base']);
});

it('collects all errors in one exception', function (): void {
    $config = [
        'enabled' => true,
        'ai' => ['provider' => null, 'model' => '', 'key' => '  '],
        'docs' => [],
    ];

    expect(validationErrors(fixtures()->root(), $config))->toEqualCanonicalizing([
        'BAP_AI_PROVIDER is required when BAP_ENABLED is true.',
        'BAP_AI_MODEL is required when BAP_ENABLED is true.',
        'BAP_AI_KEY is required when BAP_ENABLED is true.',
        'BAP_DOCS_ARRAY must contain at least one path when BAP_ENABLED is true.',
    ]);
});

it('collects AI errors and path errors together', function (): void {
    $config = validBaPanelConfig(['missing'], ['ai' => ['key' => null]]);

    expect(validationErrors(fixtures()->root(), $config))->toEqualCanonicalizing([
        'BAP_AI_KEY is required when BAP_ENABLED is true.',
        'BAP_DOCS_ARRAY path does not exist: missing',
    ]);
});

it('reports every error when the ai section is missing', function (): void {
    expect(validationErrors(fixtures()->root(), ['enabled' => true]))->toEqualCanonicalizing([
        'BAP_AI_PROVIDER is required when BAP_ENABLED is true.',
        'BAP_AI_MODEL is required when BAP_ENABLED is true.',
        'BAP_AI_KEY is required when BAP_ENABLED is true.',
        'BAP_DOCS_ARRAY must contain at least one path when BAP_ENABLED is true.',
    ]);
});

it('formats the exception message with one error per line', function (): void {
    $config = validBaPanelConfig(['missing'], ['ai' => ['provider' => null]]);

    try {
        (new ConfigValidator(fixtures()->root()))->validate($config);
        throw new LogicException('Expected InvalidConfigurationException.');
    } catch (InvalidConfigurationException $exception) {
        $expected = "Invalid ba-panel configuration:\n"
            .'- '.implode("\n- ", $exception->errors());

        expect($exception->getMessage())->toBe($expected)
            ->and($exception->errors())->toHaveCount(2);
    }
});

it('formats a single error exactly', function (): void {
    try {
        (new ConfigValidator(fixtures()->root()))->validate(validBaPanelConfig([]));
        throw new LogicException('Expected InvalidConfigurationException.');
    } catch (InvalidConfigurationException $exception) {
        expect($exception->getMessage())->toBe(
            "Invalid ba-panel configuration:\n- BAP_DOCS_ARRAY must contain at least one path when BAP_ENABLED is true."
        );
    }
});
