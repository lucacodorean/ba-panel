<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Ai;

use NeuronAI\Agent\Agent;
use NeuronAI\Providers\AIProviderInterface;

final class BaPanelAgent extends Agent
{
    private const string INSTRUCTIONS = 'You are a feasibility agent for business analysts. '
        .'Assess each request against the project documentation. '
        .'State what the documentation supports, what is missing, and the main risks. '
        .'Ask for more information when the request is not clear.';

    /**
     * The container builds the provider from the "ba-panel.ai" config through ProviderFactory.
     */
    public function __construct(private readonly AIProviderInterface $aiProvider, ?string $workflowId = null)
    {
        parent::__construct($workflowId);
    }

    protected function provider(): AIProviderInterface
    {
        return $this->aiProvider;
    }

    protected function instructions(): string
    {
        return self::INSTRUCTIONS;
    }
}
