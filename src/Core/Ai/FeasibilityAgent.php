<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Ai;

use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
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

    /**
     * The contract rules of the agent. The rules stay in code, because the report parser depends on them.
     * The host cannot change these rules. The contract block comes after the persona block.
     */
    private const string CONTRACT = <<<'TEXT'
        Sources:
        - Use ONLY the documentation files in the user message. Each file starts with a "File:" header.
        - Do not use other knowledge about the project. Do not invent facts, names, numbers, owners or dates.
        - When the files do not give a piece of information, write "Not specified in the documentation".
        - Add an open question for each piece of missing information that affects the verdict.

        Output:
        - Give a verdict for each dimension and an overall verdict.
        - Use the overall verdict "needs_more_info" when the files do not give enough information.
        - In the references, list only the paths of the files that you used.
        TEXT;

    /**
     * FeasibilityAgentFactory builds the provider from the AI settings through ProviderFactory.
     * The persona is the text of the prompt file. It gives the role, the assessment rules and the style.
     */
    public function __construct(
        private readonly AIProviderInterface $aiProvider,
        private readonly string $persona,
        ?string $workflowId = null,
    ) {
        parent::__construct($workflowId);
    }

    protected function provider(): AIProviderInterface
    {
        return $this->aiProvider;
    }

    protected function instructions(): SystemMessage
    {
        // The contract block comes last, so the persona cannot override it.
        $message = new SystemMessage($this->persona);
        $message->addContent(new TextContent(self::CONTRACT));

        return $message;
    }

    protected function contextWindow(): int
    {
        return self::CONTEXT_WINDOW;
    }
}
