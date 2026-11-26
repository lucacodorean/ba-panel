<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Feasibility;

use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAssessor;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidFeasibilityRequestException;
use Lucacodorean\BaPanel\Core\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Core\Markdown\SafeMarkdownConverter;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;
use Throwable;

/**
 * Assess a request with the feasibility agent. The service is stateless. Each call builds a new agent.
 *
 * The shells map the exceptions to HTTP statuses:
 * - InvalidFeasibilityRequestException: 422 with the exception message.
 * - InvalidConfigurationException: 503 with AI_NOT_CONFIGURED.
 * - Any other Throwable: 502 with AGENT_FAILED.
 * The shells report the 5xx exceptions and never show the exception details. The details can contain provider data.
 */
final readonly class FeasibilityService
{
    /**
     * The public message when the AI provider is not configured.
     */
    public const string AI_NOT_CONFIGURED = 'The feasibility agent is not configured.';

    /**
     * The public message when the agent fails.
     */
    public const string AGENT_FAILED = 'The feasibility agent could not create a report. Try again later.';

    public function __construct(
        private GraphBuilder $builder,
        private FeasibilityAgentFactory $agents,
        private FeasibilityAssessor $assessor,
        private TemplateRenderer $renderer,
        private SafeMarkdownConverter $converter,
    ) {
    }

    /**
     * Return the report of the request as an array, as Markdown, and as safe HTML.
     * The message is the request text. The context is a list of node ids.
     * The factory builds the agent after the validation, so the caller can catch a configuration error.
     *
     * @return array{report: array<string, mixed>, markdown: string, html: string}
     *
     * @throws InvalidFeasibilityRequestException When the input is not valid.
     * @throws InvalidConfigurationException When the AI provider is not configured.
     * @throws Throwable When the agent fails.
     */
    public function assess(mixed $message, mixed $context): array
    {
        $request = FeasibilityRequest::fromInput($message, $context, $this->builder->build());

        $report = $this->assessor->assess($this->agents->make(), $request->message, $request->files);
        $markdown = $report->toMarkdown($this->renderer);

        return [
            'report' => $report->toArray(),
            'markdown' => $markdown,
            'html' => $this->converter->convert($markdown),
        ];
    }
}
