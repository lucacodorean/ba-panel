<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Ai\BaPanelAgent;
use NeuronAI\Agent\Agent;
use NeuronAI\Providers\AIProviderInterface;

/**
 * Build a provider mock. The mock fails the test when a request to the AI provider occurs.
 */
function silentProvider(): AIProviderInterface
{
    /** @var AIProviderInterface&Mockery\MockInterface $provider */
    $provider = Mockery::mock(AIProviderInterface::class);
    $provider->shouldNotReceive('chat', 'stream', 'structured');

    return $provider;
}

afterEach(function (): void {
    Mockery::close();
});

it('extends the neuron agent', function (): void {
    expect(new BaPanelAgent(silentProvider()))->toBeInstanceOf(Agent::class);
});

it('returns the provider given to the constructor', function (): void {
    $provider = silentProvider();

    expect((new BaPanelAgent($provider))->getProvider())->toBe($provider);
});

it('has non-empty instructions', function (): void {
    $content = (new BaPanelAgent(silentProvider()))->getInstructions()->getContent();

    expect($content)->toBeString()
        ->and(trim((string) $content))->not->toBe('');
});
