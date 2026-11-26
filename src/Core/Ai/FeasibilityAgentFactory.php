<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Ai;

use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;

/**
 * Build the feasibility agent from the AI settings.
 * The class is not final. A host or a test can extend it to give an other AI provider.
 * The class is readonly. A subclass must also be a readonly class.
 */
readonly class FeasibilityAgentFactory
{
    public function __construct(
        private ProviderFactory $providers,
        private AiSettings $settings,
        private PromptLoader $prompts,
    ) {
    }

    /**
     * Build a new agent. Each call builds a new agent. Each agent keeps no history between requests.
     *
     * @throws InvalidConfigurationException When the AI provider is not configured or the prompt file is not valid.
     */
    public function make(): FeasibilityAgent
    {
        return new FeasibilityAgent(
            $this->providers->fromConfig($this->settings->toArray()),
            $this->prompts->load($this->settings->instructionsPath()),
        );
    }
}
