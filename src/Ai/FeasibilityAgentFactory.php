<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Ai;

use Illuminate\Contracts\Config\Repository;
use Lucacodorean\BaPanel\Exceptions\InvalidConfigurationException;

/**
 * Build the feasibility agent from the "ba-panel.ai" config.
 * The class is not final. A host or a test can extend it to give an other AI provider.
 * The class is readonly. A subclass must also be a readonly class.
 */
readonly class FeasibilityAgentFactory
{
    private const string CONFIG_AI = 'ba-panel.ai';

    public function __construct(
        private ProviderFactory $providers,
        private Repository $config,
    ) {
    }

    /**
     * Build a new agent. The config is read on each call. Each agent keeps no history between requests.
     *
     * @throws InvalidConfigurationException When the AI provider is not configured.
     */
    public function make(): FeasibilityAgent
    {
        $ai = $this->config->get(self::CONFIG_AI);

        return new FeasibilityAgent($this->providers->fromConfig(is_array($ai) ? $ai : []));
    }
}
