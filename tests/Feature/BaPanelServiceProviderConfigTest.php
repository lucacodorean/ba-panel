<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Lucacodorean\BaPanel\Core\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;
use Lucacodorean\BaPanel\Laravel\BaPanelServiceProvider;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use Lucacodorean\BaPanel\Tests\TestCase;

it('merges the package config under the ba-panel key', function (): void {
    expect(config('ba-panel'))->toBeArray()
        ->and(config('ba-panel.enabled'))->toBeFalse()
        ->and(config('ba-panel.ai'))->toBe(['provider' => null, 'model' => null, 'key' => null, 'instructions_path' => null])
        ->and(config('ba-panel.docs'))->toBe([]);
});

it('registers the config publish path under the ba-panel-config tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(BaPanelServiceProvider::class, 'ba-panel-config');

    expect($paths)->toHaveCount(1);

    $source = (string) array_key_first($paths);

    expect(realpath($source))->toBe(realpath(dirname(__DIR__, 2).'/config/ba-panel.php'))
        ->and($paths[$source])->toBe(config_path('ba-panel.php'));
});

it('boots when disabled with an empty config', function (): void {
    /** @var TestCase $this */
    $this->withBaPanelConfig([
        'enabled' => false,
        'ai' => ['provider' => null, 'model' => null, 'key' => null],
        'docs' => [],
    ])->rebootApplication();

    expect(app()->isBooted())->toBeTrue()
        ->and(config('ba-panel.enabled'))->toBeFalse();
});

it('throws on boot when enabled with missing values', function (): void {
    /** @var TestCase $this */
    $this->withBaPanelConfig(['enabled' => true]);

    expect(fn () => $this->rebootApplication())->toThrow(InvalidConfigurationException::class);
});

it('reports all missing values on boot', function (): void {
    /** @var TestCase $this */
    $this->withBaPanelConfig(['enabled' => true]);

    try {
        $this->rebootApplication();
        $this->fail('Expected InvalidConfigurationException.');
    } catch (InvalidConfigurationException $exception) {
        expect($exception->errors())->toEqualCanonicalizing([
            'BAP_AI_PROVIDER is required when BAP_ENABLED is true.',
            'BAP_AI_MODEL is required when BAP_ENABLED is true.',
            'BAP_AI_KEY is required when BAP_ENABLED is true.',
            'BAP_DOCS_ARRAY must contain at least one path when BAP_ENABLED is true.',
        ]);
    }
});

it('boots when enabled with a valid config', function (): void {
    /** @var TestCase $this */
    $fs = new TemporaryFilesystem;

    try {
        $this->withBaPanelConfig([
            'enabled' => true,
            'ai' => ['provider' => 'openai', 'model' => 'gpt-test', 'key' => 'secret-key'],
            'docs' => [$fs->directory('docs')],
        ])->rebootApplication();

        expect(app()->isBooted())->toBeTrue();
    } finally {
        $fs->cleanup();
    }
});

it('resolves relative docs paths against the application base path on boot', function (): void {
    /** @var TestCase $this */
    $relative = 'ba-panel-missing-'.bin2hex(random_bytes(4));
    $this->withBaPanelConfig([
        'enabled' => true,
        'ai' => ['provider' => 'openai', 'model' => 'gpt-test', 'key' => 'secret-key'],
        'docs' => [$relative],
    ]);

    try {
        $this->rebootApplication();
        $this->fail('Expected InvalidConfigurationException.');
    } catch (InvalidConfigurationException $exception) {
        expect($exception->errors())->toBe(['BAP_DOCS_ARRAY path does not exist: '.$relative]);
    }
});

it('registers the views publish path under the ba-panel-views tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(BaPanelServiceProvider::class, 'ba-panel-views');

    expect($paths)->toHaveCount(1);

    $source = (string) array_key_first($paths);

    expect(realpath($source))->toBe(realpath(dirname(__DIR__, 2).'/resources/views'))
        ->and($paths[$source])->toBe(resource_path('views/vendor/ba-panel'));
});

it('publishes the plain PHP templates with the ba-panel-views tag', function (): void {
    /** @var TestCase $this */
    $target = resource_path('views/vendor/ba-panel');
    $package = dirname(__DIR__, 2).'/resources/views';

    try {
        $this->artisan('vendor:publish', ['--tag' => 'ba-panel-views', '--force' => true])->assertSuccessful();

        expect(is_file($target.'/panel.php'))->toBeTrue()
            ->and(is_file($target.'/reports/feasibility.php'))->toBeTrue()
            ->and(file_get_contents($target.'/panel.php'))->toBe(file_get_contents($package.'/panel.php'))
            ->and(file_get_contents($target.'/reports/feasibility.php'))->toBe(file_get_contents($package.'/reports/feasibility.php'))
            ->and(glob($target.'/{,*/}*.blade.php', GLOB_BRACE))->toBe([]);
    } finally {
        File::deleteDirectory($target);
    }
});

it('resolves a published template before the package template', function (): void {
    /** @var TestCase $this */
    $target = resource_path('views/vendor/ba-panel');

    try {
        $this->artisan('vendor:publish', ['--tag' => 'ba-panel-views', '--force' => true])->assertSuccessful();

        $renderer = $this->application()->make(TemplateRenderer::class);

        expect(realpath($renderer->resolve('panel')))->toBe(realpath($target.'/panel.php'))
            ->and(realpath($renderer->resolve('reports/feasibility')))->toBe(realpath($target.'/reports/feasibility.php'));
    } finally {
        File::deleteDirectory($target);
    }
});

it('does not load the routes and does not validate the config when disabled', function (): void {
    /** @var TestCase $this */
    // The AI and docs values are not valid. The disabled panel does not check them.
    $this->withBaPanelConfig([
        'enabled' => false,
        'ai' => ['provider' => 'ollama', 'model' => null, 'key' => null],
        'docs' => ['ba-panel-missing-'.bin2hex(random_bytes(4))],
    ])->rebootApplication();

    expect($this->application()->isBooted())->toBeTrue()
        ->and(Route::has('ba-panel'))->toBeFalse()
        ->and(Route::has('ba-panel.feasibility'))->toBeFalse();
});

it('does not enable the panel for a truthy value that is not true', function (mixed $enabled): void {
    /** @var TestCase $this */
    $this->withBaPanelConfig(['enabled' => $enabled, 'docs' => ['ba-panel-missing']])->rebootApplication();

    expect(Route::has('ba-panel'))->toBeFalse();
})->with([
    'string true' => ['true'],
    'integer one' => [1],
]);

it('throws on boot when enabled with an unsupported provider', function (): void {
    /** @var TestCase $this */
    $fs = new TemporaryFilesystem;

    try {
        $this->withBaPanelConfig([
            'enabled' => true,
            'ai' => ['provider' => 'ollama', 'model' => 'llama-test', 'key' => 'secret-key'],
            'docs' => [$fs->directory('docs')],
        ]);

        $this->rebootApplication();
        $this->fail('Expected InvalidConfigurationException.');
    } catch (InvalidConfigurationException $exception) {
        expect($exception->errors())->toBe([ProviderFactory::unsupportedMessage('ollama')]);
    } finally {
        $fs->cleanup();
    }
});

it('registers the prompts publish path under the ba-panel-prompts tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(BaPanelServiceProvider::class, 'ba-panel-prompts');

    expect($paths)->toHaveCount(1);

    $source = (string) array_key_first($paths);

    expect(realpath($source))->toBe(realpath(dirname(__DIR__, 2).'/resources/prompts'))
        ->and($paths[$source])->toBe(resource_path('prompts/vendor/ba-panel'));
});

it('publishes the feasibility prompt with the ba-panel-prompts tag', function (): void {
    /** @var TestCase $this */
    $target = resource_path('prompts/vendor/ba-panel');
    $package = dirname(__DIR__, 2).'/resources/prompts';

    try {
        $this->artisan('vendor:publish', ['--tag' => 'ba-panel-prompts', '--force' => true])->assertSuccessful();

        expect(is_file($target.'/feasibility.md'))->toBeTrue()
            ->and(file_get_contents($target.'/feasibility.md'))->toBe(file_get_contents($package.'/feasibility.md'));
    } finally {
        File::deleteDirectory($target);
    }
});
