<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\OpenAI\OpenAI;

/*
| These tests only build the agent. They never send a request to an AI provider.
*/

/**
 * Build the factory with the AI settings of the given "ai" config value. A value that is not an array gives empty settings.
 */
function feasibilityAgentFactory(mixed $ai): FeasibilityAgentFactory
{
    return new FeasibilityAgentFactory(new ProviderFactory, AiSettings::fromArray(is_array($ai) ? $ai : []));
}

/**
 * Run make() and return the collected errors. Return null when no exception occurs.
 *
 * @return list<string>|null
 */
function feasibilityAgentFactoryErrors(FeasibilityAgentFactory $factory): ?array
{
    try {
        $factory->make();
    } catch (InvalidConfigurationException $exception) {
        return $exception->errors();
    }

    return null;
}

it('builds an agent with the provider and the model of the config', function (string $provider, string $class): void {
    $agent = feasibilityAgentFactory(['provider' => $provider, 'model' => 'model-test', 'key' => 'secret-key'])->make();

    expect($agent)->toBeInstanceOf(FeasibilityAgent::class)
        ->and($agent->getProvider())->toBeInstanceOf($class)
        ->and($agent->getProvider()->getModel())->toBe('model-test');
})->with([
    'openai' => ['openai', OpenAI::class],
    'anthropic' => ['anthropic', Anthropic::class],
]);

it('builds a new agent on each call', function (): void {
    $factory = feasibilityAgentFactory(['provider' => 'openai', 'model' => 'model-test', 'key' => 'secret-key']);

    expect($factory->make())->not->toBe($factory->make());
});

it('builds each agent from the given settings', function (): void {
    $factory = new FeasibilityAgentFactory(new ProviderFactory, new AiSettings('openai', 'first-model', 'secret-key'));

    expect($factory->make()->getProvider()->getModel())->toBe('first-model')
        ->and($factory->make()->getProvider()->getModel())->toBe('first-model');
});

it('builds the agent with the settings of each factory', function (): void {
    $first = new FeasibilityAgentFactory(new ProviderFactory, new AiSettings('openai', 'first-model', 'secret-key'));
    $second = new FeasibilityAgentFactory(new ProviderFactory, new AiSettings('anthropic', 'second-model', 'secret-key'));

    expect($first->make()->getProvider())->toBeInstanceOf(OpenAI::class)
        ->and($second->make()->getProvider())->toBeInstanceOf(Anthropic::class)
        ->and($second->make()->getProvider()->getModel())->toBe('second-model');
});

it('throws with every missing AI value when the settings are empty', function (): void {
    expect(feasibilityAgentFactoryErrors(new FeasibilityAgentFactory(new ProviderFactory, new AiSettings)))->toBe([
        'BAP_AI_PROVIDER is required to build the AI provider.',
        'BAP_AI_MODEL is required to build the AI provider.',
        'BAP_AI_KEY is required to build the AI provider.',
    ]);
});

it('throws with every missing AI value', function (mixed $ai): void {
    expect(feasibilityAgentFactoryErrors(feasibilityAgentFactory($ai)))->toBe([
        'BAP_AI_PROVIDER is required to build the AI provider.',
        'BAP_AI_MODEL is required to build the AI provider.',
        'BAP_AI_KEY is required to build the AI provider.',
    ]);
})->with([
    'null values' => [['provider' => null, 'model' => null, 'key' => null]],
    'empty array' => [[]],
    'missing key' => [null],
    'not an array' => ['openai'],
    'values that are not strings' => [['provider' => 1, 'model' => ['gpt'], 'key' => true]],
]);

it('throws when one AI value is blank', function (): void {
    expect(feasibilityAgentFactoryErrors(feasibilityAgentFactory(['provider' => 'openai', 'model' => 'model-test', 'key' => '  '])))
        ->toBe(['BAP_AI_KEY is required to build the AI provider.']);
});

it('throws when the provider is not supported', function (): void {
    expect(fn () => feasibilityAgentFactory(['provider' => 'unknown-provider', 'model' => 'model-test', 'key' => 'secret-key'])->make())
        ->toThrow(InvalidConfigurationException::class);
});

it('is not final, so a host or a test can extend it', function (): void {
    expect((new ReflectionClass(FeasibilityAgentFactory::class))->isFinal())->toBeFalse();
});
