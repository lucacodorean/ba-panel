<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Lucacodorean\BaPanel\Ai\ProviderFactory;
use Lucacodorean\BaPanel\BaPanelServiceProvider;
use Lucacodorean\BaPanel\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use Lucacodorean\BaPanel\Tests\TestCase;

it('merges the package config under the ba-panel key', function (): void {
    expect(config('ba-panel'))->toBeArray()
        ->and(config('ba-panel.enabled'))->toBeFalse()
        ->and(config('ba-panel.ai'))->toBe(['provider' => null, 'model' => null, 'key' => null])
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
