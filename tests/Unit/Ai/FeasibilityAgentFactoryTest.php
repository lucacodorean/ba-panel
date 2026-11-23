<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Lucacodorean\BaPanel\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Exceptions\InvalidConfigurationException;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\OpenAI\OpenAI;

/*
| These tests only build the agent. They never send a request to an AI provider.
*/

/**
 * Build the factory with a config repository that has the given "ba-panel.ai" value.
 */
function feasibilityAgentFactory(mixed $ai): FeasibilityAgentFactory
{
    return new FeasibilityAgentFactory(new ProviderFactory, new Repository(['ba-panel' => ['ai' => $ai]]));
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

it('reads the config on each call', function (): void {
    $config = new Repository(['ba-panel' => ['ai' => ['provider' => 'openai', 'model' => 'first-model', 'key' => 'secret-key']]]);
    $factory = new FeasibilityAgentFactory(new ProviderFactory, $config);

    $first = $factory->make();
    $config->set('ba-panel.ai.model', 'second-model');

    expect($first->getProvider()->getModel())->toBe('first-model')
        ->and($factory->make()->getProvider()->getModel())->toBe('second-model');
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
