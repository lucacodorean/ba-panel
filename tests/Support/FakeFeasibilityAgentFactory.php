<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Tests\Support;

use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Ai\PromptLoader;
use Lucacodorean\BaPanel\Core\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Core\Config\AiSettings;
use NeuronAI\Providers\AIProviderInterface;

/**
 * Build each agent with the given test provider. This factory never reads the AI settings or a prompt file.
 * It never builds a real provider, so a test cannot send a request to a real AI provider.
 * The parent class is readonly. Thus this class must also be readonly.
 */
final readonly class FakeFeasibilityAgentFactory extends FeasibilityAgentFactory
{
    /**
     * The persona of each test agent.
     */
    public const string PERSONA = 'You are the test persona of the feasibility agent.';

    public function __construct(
        ProviderFactory $providers,
        AiSettings $settings,
        PromptLoader $prompts,
        private AIProviderInterface $provider,
    ) {
        parent::__construct($providers, $settings, $prompts);
    }

    /**
     * Build a new agent with the test provider and the test persona. Each call returns a new agent.
     */
    public function make(): FeasibilityAgent
    {
        return new FeasibilityAgent($this->provider, self::PERSONA);
    }
}
