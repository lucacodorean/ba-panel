<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Config\AiSettings;

it('has null values by default', function (): void {
    $settings = new AiSettings;

    expect($settings->provider)->toBeNull()
        ->and($settings->model)->toBeNull()
        ->and($settings->key)->toBeNull()
        ->and($settings->toArray())->toBe(['provider' => null, 'model' => null, 'key' => null]);
});

it('keeps the given values', function (): void {
    $settings = new AiSettings('openai', 'gpt-test', 'secret-key');

    expect($settings->toArray())->toBe(['provider' => 'openai', 'model' => 'gpt-test', 'key' => 'secret-key']);
});

it('builds the settings from the AI config array', function (): void {
    $settings = AiSettings::fromArray(['provider' => 'anthropic', 'model' => 'claude-test', 'key' => 'secret-key']);

    expect($settings->provider)->toBe('anthropic')
        ->and($settings->model)->toBe('claude-test')
        ->and($settings->key)->toBe('secret-key');
});

it('gives null for a missing value', function (): void {
    expect(AiSettings::fromArray(['provider' => 'openai'])->toArray())->toBe(['provider' => 'openai', 'model' => null, 'key' => null])
        ->and(AiSettings::fromArray([])->toArray())->toBe(['provider' => null, 'model' => null, 'key' => null]);
});

it('gives null for a value that is not a string', function (mixed $value): void {
    expect(AiSettings::fromArray(['provider' => $value, 'model' => $value, 'key' => $value])->toArray())
        ->toBe(['provider' => null, 'model' => null, 'key' => null]);
})->with([
    'integer' => [1],
    'float' => [1.5],
    'true' => [true],
    'list' => [['openai']],
    'object' => [new stdClass],
    'null' => [null],
]);

it('keeps an empty or blank string', function (): void {
    // ProviderFactory checks blank values when it builds the provider.
    expect(AiSettings::fromArray(['provider' => '', 'model' => '  ', 'key' => 'k'])->toArray())
        ->toBe(['provider' => '', 'model' => '  ', 'key' => 'k']);
});

it('ignores unknown keys', function (): void {
    expect(AiSettings::fromArray(['provider' => 'openai', 'extra' => 'value'])->toArray())
        ->toBe(['provider' => 'openai', 'model' => null, 'key' => null]);
});

it('returns the same values after a round trip', function (): void {
    $settings = new AiSettings('openai', 'gpt-test', 'secret-key');

    expect(AiSettings::fromArray($settings->toArray()))->toEqual($settings);
});
