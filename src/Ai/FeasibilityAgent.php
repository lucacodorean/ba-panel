<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Ai;

use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\SystemMessage;
use NeuronAI\Providers\AIProviderInterface;

/**
 * The feasibility agent. It assesses a change request or a feature request against the given documentation files.
 */
final class FeasibilityAgent extends Agent
{
    /**
     * The token budget of the conversation. The request sends many documentation files, so the budget is large.
     */
    public const int CONTEXT_WINDOW = 150_000;

    private const string INSTRUCTIONS = <<<'TEXT'
        You are a feasibility analyst for business analysts in software engineering.
        You assess one change request or one feature request.

        Sources:
        - Use ONLY the documentation files in the user message. Each file starts with a "File:" header.
        - Do not use other knowledge about the project. Do not invent facts, names, numbers, owners or dates.
        - When the files do not give a piece of information, write "Not specified in the documentation".
        - Add an open question for each piece of missing information that affects the verdict.

        Assessment:
        - State what the documentation supports and what is missing.
        - Identify the affected components, the dependencies and the risks.
        - Estimate the effort only from the documentation. Use a low confidence when the documentation is thin.
        - Give a verdict for each dimension and an overall verdict.
        - Use the overall verdict "needs_more_info" when the files do not give enough information.
        - In the references, list only the paths of the files that you used.

        Style:
        - Use short, clear sentences. Write for business analysts.
        - Do not repeat the documentation. Summarise it.
        TEXT;

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

    protected function instructions(): SystemMessage
    {
        return new SystemMessage(self::INSTRUCTIONS);
    }

    protected function contextWindow(): int
    {
        return self::CONTEXT_WINDOW;
    }
}
