<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Http\Controllers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lucacodorean\BaPanel\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Ai\FeasibilityAssessor;
use Lucacodorean\BaPanel\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Exceptions\InvalidFeasibilityRequestException;
use Lucacodorean\BaPanel\Graph\Graph;
use Lucacodorean\BaPanel\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Graph\Node;
use Lucacodorean\BaPanel\Markdown\SafeMarkdownConverter;
use Lucacodorean\BaPanel\Reports\FeasibilityReport;
use Throwable;

/**
 * Assess a request with the feasibility agent. The request and the response are JSON.
 */
final class FeasibilityController
{
    /**
     * Longer messages are refused.
     */
    public const int MAX_MESSAGE_LENGTH = 5000;

    /**
     * Larger contexts are refused.
     */
    public const int MAX_CONTEXT_FILES = 50;

    private const string INPUT_MESSAGE = 'message';

    private const string INPUT_CONTEXT = 'context';

    /**
     * Match exactly 12 lowercase hex characters. "\z" refuses a final line break. "$" accepts one.
     */
    private const string NODE_ID_PATTERN = '/^[a-f0-9]{12}\z/';

    private const string MESSAGE_REQUIRED = 'The message is required.';

    private const string MESSAGE_TOO_LONG = 'The message must have at most %d characters.';

    private const string CONTEXT_NOT_LIST = 'The context must be a list of file ids.';

    private const string CONTEXT_TOO_LARGE = 'The context must have at most %d files.';

    private const string CONTEXT_INVALID_ID = 'The context contains an invalid file id.';

    private const string CONTEXT_UNKNOWN_ID = 'The context contains an unknown file id: %s.';

    private const string AI_NOT_CONFIGURED = 'The feasibility agent is not configured.';

    private const string AGENT_FAILED = 'The feasibility agent could not create a report. Try again later.';

    /**
     * Return the report of the request as an array, as Markdown, and as safe HTML.
     *
     * Request: {"message": string, "context": list<string>}. Each context item is a node id.
     * Response 200: {"report": array, "markdown": string, "html": string}.
     * Errors: {"message": string} with 422 for an invalid request, 503 when the AI is not configured, 502 when the agent fails.
     */
    public function assess(
        Request $request,
        Container $container,
        GraphBuilder $builder,
        FeasibilityAssessor $assessor,
        SafeMarkdownConverter $converter,
    ): JsonResponse {
        try {
            $message = $this->message($request->input(self::INPUT_MESSAGE));
            $files = $this->files($request->input(self::INPUT_CONTEXT, []), $builder->build());
        } catch (InvalidFeasibilityRequestException $exception) {
            return $this->error($exception->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // The response does not include the exception details. The details can contain provider data.
        try {
            $report = $this->report($container, $assessor, $message, $files);
        } catch (InvalidConfigurationException $exception) {
            report($exception);

            return $this->error(self::AI_NOT_CONFIGURED, JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        } catch (Throwable $exception) {
            report($exception);

            return $this->error(self::AGENT_FAILED, JsonResponse::HTTP_BAD_GATEWAY);
        }

        $markdown = $report->toMarkdown();

        return new JsonResponse([
            'report' => $report->toArray(),
            'markdown' => $markdown,
            'html' => $converter->convert($markdown),
        ]);
    }

    /**
     * Build the agent and run the assessment. The container builds the agent here, so a configuration error can be caught.
     * The container throws InvalidConfigurationException when the AI provider is not configured.
     *
     * @param list<Node> $files
     */
    private function report(Container $container, FeasibilityAssessor $assessor, string $message, array $files): FeasibilityReport
    {
        /** @var FeasibilityAgent $agent */
        $agent = $container->make(FeasibilityAgent::class);

        return $assessor->assess($agent, $message, $files);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }

    /**
     * Return the trimmed message.
     *
     * @throws InvalidFeasibilityRequestException
     */
    private function message(mixed $message): string
    {
        $message = is_string($message) ? trim($message) : '';

        if ($message === '') {
            throw new InvalidFeasibilityRequestException(self::MESSAGE_REQUIRED);
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new InvalidFeasibilityRequestException(sprintf(self::MESSAGE_TOO_LONG, self::MAX_MESSAGE_LENGTH));
        }

        return $message;
    }

    /**
     * Return the nodes of the context ids, in the given order. A repeated id is used once.
     * The nodes are found only by id in the graph. A path is never read from the request.
     *
     * @return list<Node>
     *
     * @throws InvalidFeasibilityRequestException
     */
    private function files(mixed $context, Graph $graph): array
    {
        if (! is_array($context) || ! array_is_list($context)) {
            throw new InvalidFeasibilityRequestException(self::CONTEXT_NOT_LIST);
        }

        if (count($context) > self::MAX_CONTEXT_FILES) {
            throw new InvalidFeasibilityRequestException(sprintf(self::CONTEXT_TOO_LARGE, self::MAX_CONTEXT_FILES));
        }

        $files = [];

        foreach ($context as $id) {
            if (! is_string($id) || preg_match(self::NODE_ID_PATTERN, $id) !== 1) {
                throw new InvalidFeasibilityRequestException(self::CONTEXT_INVALID_ID);
            }

            $files[$id] = $graph->node($id) ?? throw new InvalidFeasibilityRequestException(sprintf(self::CONTEXT_UNKNOWN_ID, $id));
        }

        return array_values($files);
    }
}
