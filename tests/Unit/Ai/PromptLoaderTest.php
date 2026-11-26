<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Ai\PromptLoader;
use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use Lucacodorean\BaPanel\Tests\TestCase;

/*
| These tests write prompt files in a temporary directory. Each test removes the directory afterwards.
| The selection order is: the configured path, then the published path, then the default path.
*/

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk and forget it when $cleanup is true.
 */
function promptLoaderFixtures(bool $cleanup = false): ?TemporaryFilesystem
{
    static $fs = null;

    if ($cleanup) {
        $fs?->cleanup();
        $fs = null;

        return null;
    }

    return $fs ??= new TemporaryFilesystem;
}

/**
 * Return the temporary filesystem of the current test.
 */
function promptLoaderFs(): TemporaryFilesystem
{
    return promptLoaderFixtures() ?? throw new RuntimeException('No temporary filesystem.');
}

/**
 * Build a loader with the temporary root as base path. The default file is in the temporary root.
 */
function promptLoader(?string $publishedPath = null, ?string $defaultPath = null): PromptLoader
{
    $fs = promptLoaderFs();

    return new PromptLoader($fs->root(), $publishedPath, $defaultPath ?? $fs->file('default.md', 'Default persona.'));
}

/**
 * Run load() and return the collected errors. Return null when no exception occurs.
 *
 * @return list<string>|null
 */
function promptLoaderErrors(PromptLoader $loader, ?string $path): ?array
{
    try {
        $loader->load($path);
    } catch (InvalidConfigurationException $exception) {
        return $exception->errors();
    }

    return null;
}

/**
 * Return the package root.
 */
function promptLoaderPackageRoot(): string
{
    return dirname(__DIR__, 3);
}

afterEach(function (): void {
    promptLoaderFixtures(cleanup: true);
});

it('is a final readonly class', function (): void {
    $class = new ReflectionClass(PromptLoader::class);

    expect($class->isFinal())->toBeTrue()
        ->and($class->isReadOnly())->toBeTrue();
});

// Read and trim.

it('returns the text of the configured prompt file', function (): void {
    $path = promptLoaderFs()->file('prompt.md', "You are a persona.\n\nUse short sentences.");

    expect(promptLoader()->load($path))->toBe("You are a persona.\n\nUse short sentences.");
});

it('trims the text of the prompt file', function (): void {
    $path = promptLoaderFs()->file('prompt.md', "\n\t  You are a persona.\n\nUse short sentences.  \n\n");

    expect(promptLoader()->load($path))->toBe("You are a persona.\n\nUse short sentences.");
});

// Path resolution.

it('uses an absolute Unix path as given', function (): void {
    // The base path is a different directory. The absolute path must not resolve against it.
    $path = promptLoaderFs()->file('prompts/absolute.md', 'Absolute persona.');
    $other = promptLoaderFs()->directory('other');

    expect((new PromptLoader($other))->load($path))->toBe('Absolute persona.');
});

it('uses an absolute Windows path as given', function (string $path): void {
    // The file does not exist on the test system. The error message shows that the path is not resolved.
    expect(promptLoaderErrors(promptLoader(), $path))->toBe(['The prompt file does not exist: '.$path]);
})->with([
    'back slash' => ['C:\\prompts\\feasibility.md'],
    'forward slash' => ['C:/prompts/feasibility.md'],
    'lower case drive' => ['d:\\prompts\\feasibility.md'],
]);

it('resolves a drive letter without a separator as a relative path', function (): void {
    $root = promptLoaderFs()->root();

    expect(promptLoaderErrors(promptLoader(), 'C:feasibility.md'))
        ->toBe(['The prompt file does not exist: '.$root.DIRECTORY_SEPARATOR.'C:feasibility.md']);
});

it('resolves a relative path against the base path', function (string $suffix): void {
    promptLoaderFs()->file('prompts/relative.md', 'Relative persona.');

    expect((new PromptLoader(promptLoaderFs()->root().$suffix))->load('prompts/relative.md'))->toBe('Relative persona.');
})->with([
    'no trailing separator' => [''],
    'trailing slash' => ['/'],
    'many trailing slashes' => ['//'],
    'trailing back slash' => ['\\'],
]);

it('shows the resolved path when a relative path does not exist', function (): void {
    $root = promptLoaderFs()->root();

    expect(promptLoaderErrors(new PromptLoader($root.'/'), 'prompts/missing.md'))
        ->toBe(['The prompt file does not exist: '.$root.DIRECTORY_SEPARATOR.'prompts/missing.md']);
});

// Selection order.

it('uses the configured path before the published and the default file', function (): void {
    $published = promptLoaderFs()->file('published.md', 'Published persona.');
    $configured = promptLoaderFs()->file('configured.md', 'Configured persona.');

    expect(promptLoader($published)->load($configured))->toBe('Configured persona.');
});

it('throws for a missing configured path and does not use another file', function (): void {
    // The published and the default files exist. The loader must not fall back to them.
    $published = promptLoaderFs()->file('published.md', 'Published persona.');
    $missing = promptLoaderFs()->path('missing.md');

    expect(fn () => promptLoader($published)->load($missing))->toThrow(InvalidConfigurationException::class)
        ->and(promptLoaderErrors(promptLoader($published), $missing))->toBe(['The prompt file does not exist: '.$missing]);
});

it('uses the published file when no path is configured', function (?string $path): void {
    $published = promptLoaderFs()->file('published.md', 'Published persona.');

    expect(promptLoader($published)->load($path))->toBe('Published persona.');
})->with([
    'null' => [null],
    'empty' => [''],
    'blank' => ['  '],
]);

it('uses the published file when load has no argument', function (): void {
    $published = promptLoaderFs()->file('published.md', 'Published persona.');

    expect(promptLoader($published)->load())->toBe('Published persona.');
});

it('resolves a relative published path against the base path', function (): void {
    promptLoaderFs()->file('resources/prompts/vendor/ba-panel/feasibility.md', 'Relative published persona.');

    expect(promptLoader('resources/prompts/vendor/ba-panel/feasibility.md')->load())->toBe('Relative published persona.');
});

it('uses the default file when the published file does not exist', function (): void {
    expect(promptLoader(promptLoaderFs()->path('missing-published.md'))->load())->toBe('Default persona.');
});

it('uses the default file when no published path is given', function (?string $published): void {
    expect(promptLoader($published)->load())->toBe('Default persona.');
})->with([
    'null' => [null],
    'empty' => [''],
    'blank' => ['  '],
]);

it('uses the default file when the published path is a directory', function (): void {
    expect(promptLoader(promptLoaderFs()->directory('published'))->load())->toBe('Default persona.');
});

it('resolves a relative default path against the base path', function (): void {
    promptLoaderFs()->file('prompts/default.md', 'Relative default persona.');

    expect(promptLoader(defaultPath: 'prompts/default.md')->load())->toBe('Relative default persona.');
});

it('uses the package prompt file as the default path', function (): void {
    $package = trim((string) file_get_contents(promptLoaderPackageRoot().'/resources/prompts/feasibility.md'));

    expect((new PromptLoader(promptLoaderFs()->root()))->load())->toBe($package)
        ->and((new PromptLoader(promptLoaderFs()->root()))->load(null))->toBe($package);
});

// Errors of the selected file.

it('throws when the configured path is a directory', function (): void {
    $path = promptLoaderFs()->directory('prompts');

    expect(promptLoaderErrors(promptLoader(), $path))->toBe(['The prompt file does not exist: '.$path]);
});

it('throws when the default file does not exist', function (): void {
    $default = promptLoaderFs()->path('missing-default.md');

    expect(promptLoaderErrors(promptLoader(defaultPath: $default), null))->toBe(['The prompt file does not exist: '.$default]);
});

it('throws when the selected prompt file is not readable', function (string $role): void {
    /** @var TestCase $this */
    $path = promptLoaderFs()->file('locked.md', 'Locked persona.');
    chmod($path, 0000);

    try {
        // The root user can read the file. The check then has no effect.
        if (is_readable($path)) {
            $this->markTestSkipped('The process can read a file with mode 0000, for example as root.');
        }

        $loader = match ($role) {
            'configured' => promptLoader(),
            'published' => promptLoader($path),
            default => promptLoader(defaultPath: $path),
        };

        expect(promptLoaderErrors($loader, $role === 'configured' ? $path : null))
            ->toBe(['The prompt file is not readable: '.$path]);
    } finally {
        chmod($path, 0644);
    }
})->with(['configured', 'published', 'default']);

it('throws when the selected prompt file is empty or has only whitespace', function (string $role, string $contents): void {
    $path = promptLoaderFs()->file('empty.md', $contents);

    $loader = match ($role) {
        'configured' => promptLoader(),
        'published' => promptLoader($path),
        default => promptLoader(defaultPath: $path),
    };

    expect(promptLoaderErrors($loader, $role === 'configured' ? $path : null))->toBe(['The prompt file is empty: '.$path]);
})->with(['configured', 'published', 'default'])->with([
    'empty' => [''],
    'spaces' => ['   '],
    'new lines and tabs' => ["\n\t\r\n  \n"],
]);

// The shipped prompt file.

it('ships a prompt file that is not empty', function (): void {
    $path = promptLoaderPackageRoot().'/resources/prompts/feasibility.md';

    expect($path)->toBeFile()
        ->and(trim((string) file_get_contents($path)))->not->toBe('');
});

it('loads the shipped prompt file with an absolute and a relative path', function (): void {
    $text = trim((string) file_get_contents(promptLoaderPackageRoot().'/resources/prompts/feasibility.md'));
    $loader = new PromptLoader(promptLoaderPackageRoot());

    expect($loader->load(promptLoaderPackageRoot().'/resources/prompts/feasibility.md'))->toBe($text)
        ->and($loader->load('resources/prompts/feasibility.md'))->toBe($text)
        ->and($loader->load(AiSettings::DEFAULT_INSTRUCTIONS_PATH))->toBe($text);
});

it('ships a persona without the contract rules', function (): void {
    // The contract rules stay in FeasibilityAgent. The persona must not repeat them.
    $text = (new PromptLoader(promptLoaderPackageRoot()))->load();

    expect($text)->not->toContain('Use ONLY the documentation files')
        ->and($text)->not->toContain('needs_more_info');
});
