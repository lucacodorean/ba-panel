<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Exceptions\InvalidConfigurationException;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\XAI\Grok;

/**
 * Run fromConfig() and return the collected errors. Return null when no exception occurs.
 *
 * @param  array<mixed>  $ai
 * @return list<string>|null
 */
function providerFactoryErrors(array $ai): ?array
{
    try {
        (new ProviderFactory)->fromConfig($ai);
    } catch (InvalidConfigurationException $exception) {
        return $exception->errors();
    }

    return null;
}

/**
 * Each supported provider name and the class that make() returns.
 *
 * @return array<string, array{string, class-string<AIProviderInterface>}>
 */
function supportedProviderClasses(): array
{
    return [
        'anthropic' => ['anthropic', Anthropic::class],
        'openai' => ['openai', OpenAI::class],
        'gemini' => ['gemini', Gemini::class],
        'mistral' => ['mistral', Mistral::class],
        'deepseek' => ['deepseek', Deepseek::class],
        'xai' => ['xai', Grok::class],
    ];
}

it('lists the supported providers in order', function (): void {
    expect(ProviderFactory::SUPPORTED_PROVIDERS)
        ->toBe(['anthropic', 'openai', 'gemini', 'mistral', 'deepseek', 'xai']);
});

it('reports whether a provider is supported', function (string $provider, bool $expected): void {
    expect(ProviderFactory::supports($provider))->toBe($expected);
})->with([
    'lowercase' => ['openai', true],
    'mixed case with whitespace' => [' OpenAI ', true],
    'uppercase' => ['ANTHROPIC', true],
    'tab and newline' => ["\txai\n", true],
    'unsupported' => ['ollama', false],
    'empty' => ['', false],
    'whitespace only' => ['   ', false],
    'partial name' => ['open', false],
]);

it('formats the unsupported message with the given value and the supported list', function (): void {
    expect(ProviderFactory::unsupportedMessage('ollama'))->toBe(
        'BAP_AI_PROVIDER value is not supported: "ollama". '
        .'Use one of these values: anthropic, openai, gemini, mistral, deepseek, xai.'
    );
});

it('builds the provider class for each supported name', function (string $provider, string $class): void {
    $instance = (new ProviderFactory)->make($provider, 'model-test', 'secret-key');

    expect($instance)->toBeInstanceOf($class)
        ->and($instance)->toBeInstanceOf(AIProviderInterface::class)
        ->and($instance->getModel())->toBe('model-test');
})->with(supportedProviderClasses());

it('ignores case and outer whitespace when it builds the provider', function (): void {
    expect((new ProviderFactory)->make('  Anthropic ', 'model-test', 'secret-key'))
        ->toBeInstanceOf(Anthropic::class);
});

it('throws one error for an unknown provider name', function (): void {
    try {
        (new ProviderFactory)->make('ollama', 'model-test', 'secret-key');
        throw new LogicException('Expected InvalidConfigurationException.');
    } catch (InvalidConfigurationException $exception) {
        expect($exception->errors())->toBe([ProviderFactory::unsupportedMessage('ollama')]);
    }
});

it('reports all missing AI values in order', function (): void {
    expect(providerFactoryErrors([]))->toBe([
        'BAP_AI_PROVIDER is required to build the AI provider.',
        'BAP_AI_MODEL is required to build the AI provider.',
        'BAP_AI_KEY is required to build the AI provider.',
    ]);
});

it('reports blank and non-string AI values as missing', function (): void {
    expect(providerFactoryErrors(['provider' => '  ', 'model' => null, 'key' => 123]))->toBe([
        'BAP_AI_PROVIDER is required to build the AI provider.',
        'BAP_AI_MODEL is required to build the AI provider.',
        'BAP_AI_KEY is required to build the AI provider.',
    ]);
});

it('reports only the missing AI value', function (string $key, string $variable): void {
    $ai = ['provider' => 'openai', 'model' => 'gpt-test', 'key' => 'secret-key'];
    unset($ai[$key]);

    expect(providerFactoryErrors($ai))->toBe([$variable.' is required to build the AI provider.']);
})->with([
    'provider' => ['provider', 'BAP_AI_PROVIDER'],
    'model' => ['model', 'BAP_AI_MODEL'],
    'key' => ['key', 'BAP_AI_KEY'],
]);

it('builds the provider from a valid config', function (string $provider, string $class): void {
    $instance = (new ProviderFactory)->fromConfig([
        'provider' => $provider,
        'model' => 'model-test',
        'key' => 'secret-key',
    ]);

    expect($instance)->toBeInstanceOf($class)
        ->and($instance->getModel())->toBe('model-test');
})->with(supportedProviderClasses());

it('throws the unsupported error from config with an unknown provider', function (): void {
    expect(providerFactoryErrors(['provider' => 'ollama', 'model' => 'model-test', 'key' => 'secret-key']))
        ->toBe([ProviderFactory::unsupportedMessage('ollama')]);
});
