<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Tests\Support;

use Illuminate\Contracts\Config\Repository;
use Lucacodorean\BaPanel\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Ai\ProviderFactory;
use NeuronAI\Providers\AIProviderInterface;

/**
 * Build each agent with the given test provider. This factory never reads the AI config.
 * It never builds a real provider, so a test cannot send a request to a real AI provider.
 * The parent class is readonly. Thus this class must also be readonly.
 */
final readonly class FakeFeasibilityAgentFactory extends FeasibilityAgentFactory
{
    public function __construct(
        ProviderFactory $providers,
        Repository $config,
        private AIProviderInterface $provider,
    ) {
        parent::__construct($providers, $config);
    }

    /**
     * Build a new agent with the test provider. Each call returns a new agent.
     */
    public function make(): FeasibilityAgent
    {
        return new FeasibilityAgent($this->provider);
    }
}
