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

it('has a default prompt path in the package', function (): void {
    expect(realpath(AiSettings::DEFAULT_INSTRUCTIONS_PATH))->toBe(realpath(dirname(__DIR__, 3).'/resources/prompts/feasibility.md'))
        ->and(AiSettings::DEFAULT_INSTRUCTIONS_PATH)->toBeFile();
});

it('has no prompt path by default', function (): void {
    $settings = new AiSettings;

    expect($settings->instructionsPath)->toBeNull()
        ->and($settings->instructionsPath())->toBeNull();
});

it('gives no prompt path for a null or blank value', function (?string $path): void {
    // PromptLoader selects the published or the default prompt file for a null value.
    expect((new AiSettings(instructionsPath: $path))->instructionsPath())->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
    'spaces' => ['   '],
    'tabs and new lines' => ["\t\n"],
]);

it('gives no prompt path when the config key is missing or not a string', function (array $ai): void {
    $settings = AiSettings::fromArray($ai);

    expect($settings->instructionsPath)->toBeNull()
        ->and($settings->instructionsPath())->toBeNull();
})->with([
    'missing key' => [['provider' => 'openai']],
    'null' => [['instructions_path' => null]],
    'integer' => [['instructions_path' => 1]],
    'list' => [['instructions_path' => ['prompt.md']]],
]);

it('reads the prompt path from the AI config array as given', function (string $path): void {
    $settings = AiSettings::fromArray(['provider' => 'openai', 'instructions_path' => $path]);

    expect($settings->instructionsPath)->toBe($path)
        ->and($settings->instructionsPath())->toBe($path);
})->with([
    'absolute' => ['/var/prompts/feasibility.md'],
    'relative' => ['resources/prompts/vendor/ba-panel/feasibility.md'],
    'windows' => ['C:\\prompts\\feasibility.md'],
    'surrounding spaces' => [' prompts/feasibility.md '],
]);

it('keeps the configured prompt path from the constructor', function (): void {
    expect((new AiSettings('openai', 'gpt-test', 'secret-key', '/var/prompts/custom.md'))->instructionsPath())
        ->toBe('/var/prompts/custom.md');
});

it('does not add the prompt path to the provider array', function (): void {
    expect(AiSettings::fromArray(['provider' => 'openai', 'model' => 'gpt-test', 'key' => 'secret-key', 'instructions_path' => '/var/prompt.md'])->toArray())
        ->toBe(['provider' => 'openai', 'model' => 'gpt-test', 'key' => 'secret-key']);
});
