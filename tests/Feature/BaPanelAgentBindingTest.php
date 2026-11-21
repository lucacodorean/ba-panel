<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Ai\BaPanelAgent;
use Lucacodorean\BaPanel\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Tests\Support\PanelConfig;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use Lucacodorean\BaPanel\Tests\TestCase;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\XAI\Grok;

/*
| These tests resolve the agent from the container only. They never send a request to an AI provider.
*/

it('resolves the agent with the configured provider', function (string $provider, string $class): void {
    /** @var TestCase $this */
    $fs = new TemporaryFilesystem;

    try {
        $this->withBaPanelConfig(PanelConfig::enabled($fs->directory('docs'), [
            'provider' => $provider,
            'model' => 'model-test',
        ]))->rebootApplication();

        $agent = app(BaPanelAgent::class);

        expect($agent)->toBeInstanceOf(BaPanelAgent::class)
            ->and($agent->getProvider())->toBeInstanceOf($class)
            ->and($agent->getProvider()->getModel())->toBe('model-test');
    } finally {
        $fs->cleanup();
    }
})->with([
    'anthropic' => ['anthropic', Anthropic::class],
    'openai' => ['openai', OpenAI::class],
    'gemini' => ['gemini', Gemini::class],
    'mistral' => ['mistral', Mistral::class],
    'deepseek' => ['deepseek', Deepseek::class],
    'xai' => ['xai', Grok::class],
]);

it('throws when it resolves the agent with an empty AI config', function (): void {
    expect(config('ba-panel.enabled'))->toBeFalse()
        ->and(fn () => app(BaPanelAgent::class))->toThrow(InvalidConfigurationException::class);
});

it('reports every missing AI value when it resolves the agent', function (): void {
    try {
        app(BaPanelAgent::class);
        throw new LogicException('Expected InvalidConfigurationException.');
    } catch (InvalidConfigurationException $exception) {
        expect($exception->errors())->toBe([
            'BAP_AI_PROVIDER is required to build the AI provider.',
            'BAP_AI_MODEL is required to build the AI provider.',
            'BAP_AI_KEY is required to build the AI provider.',
        ]);
    }
});

it('does not build the agent on boot when disabled', function (): void {
    /** @var TestCase $this */
    $this->withBaPanelConfig(PanelConfig::disabled())->rebootApplication();

    expect(app()->isBooted())->toBeTrue()
        ->and(app()->resolved(BaPanelAgent::class))->toBeFalse();
});

it('builds a new agent on each resolve', function (): void {
    /** @var TestCase $this */
    $fs = new TemporaryFilesystem;

    try {
        $this->withBaPanelConfig(PanelConfig::enabled($fs->directory('docs')))->rebootApplication();

        expect(app(BaPanelAgent::class))->not->toBe(app(BaPanelAgent::class));
    } finally {
        $fs->cleanup();
    }
});

it('registers the provider factory as a singleton', function (): void {
    expect(app()->isShared(ProviderFactory::class))->toBeTrue()
        ->and(app(ProviderFactory::class))->toBe(app(ProviderFactory::class));
});
