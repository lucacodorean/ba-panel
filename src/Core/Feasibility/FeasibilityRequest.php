<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Feasibility;

use Lucacodorean\BaPanel\Core\Exceptions\InvalidFeasibilityRequestException;
use Lucacodorean\BaPanel\Core\Graph\Graph;
use Lucacodorean\BaPanel\Core\Graph\Node;

/**
 * A validated feasibility request: the trimmed message and the nodes of the context.
 * The shells give the raw input. This class owns the limits and the error messages.
 */
final readonly class FeasibilityRequest
{
    /**
     * Longer messages are refused.
     */
    public const int MAX_MESSAGE_LENGTH = 5000;

    /**
     * Larger contexts are refused.
     */
    public const int MAX_CONTEXT_FILES = 50;

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

    /**
     * @param list<Node> $files
     */
    private function __construct(
        public string $message,
        public array $files,
    ) {
    }

    /**
     * Validate the raw input. The context is a list of node ids. Each id is found only in the graph.
     * A path is never read from the input. A repeated id is used once. The order of the ids is kept.
     *
     * @throws InvalidFeasibilityRequestException When the input is not valid. The message is safe to show to the user.
     */
    public static function fromInput(mixed $message, mixed $context, Graph $graph): self
    {
        return new self(self::message($message), self::files($context, $graph));
    }

    /**
     * @throws InvalidFeasibilityRequestException
     */
    private static function message(mixed $message): string
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
     * @return list<Node>
     *
     * @throws InvalidFeasibilityRequestException
     */
    private static function files(mixed $context, Graph $graph): array
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
