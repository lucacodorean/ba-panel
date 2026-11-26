<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Ai;

use Lucacodorean\BaPanel\Core\Graph\DocumentRenderer;
use Lucacodorean\BaPanel\Core\Graph\Node;
use Lucacodorean\BaPanel\Core\Reports\FeasibilityReport;
use Lucacodorean\BaPanel\Core\Reports\ReportMetadataFactory;
use NeuronAI\Agent\AgentInterface;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\UserMessage;
use UnexpectedValueException;

/**
 * Run the feasibility agent on a request and the documentation files of the context.
 */
final readonly class FeasibilityAssessor
{
    /**
     * The number of random bytes in a thread id. Each assessment has a new thread, so no history is kept.
     */
    private const int THREAD_ID_BYTES = 16;

    private const string FILES_HEADER = 'Documentation files given as context: %d.';

    private const string NO_FILES = 'No documentation files are given. Say that the documentation gives no information.';

    /**
     * The file header. The arguments are the display path, the title and the body.
     */
    private const string FILE_FORMAT = "File: %s\nTitle: %s\n\n%s";

    private const string UNREADABLE_FILE = 'The file could not be read.';

    /**
     * The request header. The argument is the message of the user.
     */
    private const string REQUEST_FORMAT = "Request to assess:\n%s";

    private const string UNEXPECTED_OUTPUT = 'The agent did not return a feasibility report.';

    public function __construct(
        private DocumentRenderer $documents,
        private ReportMetadataFactory $metadata,
    ) {
    }

    /**
     * Assess the request against the files. Each call uses a new thread id. The report gets new metadata.
     *
     * @param list<Node> $files
     *
     * @throws UnexpectedValueException When the agent returns an other type.
     */
    public function assess(AgentInterface $agent, string $message, array $files): FeasibilityReport
    {
        $agent->setThreadId(bin2hex(random_bytes(self::THREAD_ID_BYTES)));

        $report = $agent->structured($this->message($message, $files), FeasibilityReport::class);

        if (! $report instanceof FeasibilityReport) {
            throw new UnexpectedValueException(self::UNEXPECTED_OUTPUT);
        }

        return $report->attachMetadata($this->metadata->create());
    }

    /**
     * Build the user message. The first block counts the files. Each file is one block. The request is the last block.
     * The front matter of each file is removed.
     *
     * @param list<Node> $files
     */
    public function message(string $message, array $files): UserMessage
    {
        $blocks = [new TextContent($files === [] ? self::NO_FILES : sprintf(self::FILES_HEADER, count($files)))];

        foreach ($files as $file) {
            $body = $this->documents->body($file);

            $blocks[] = new TextContent(sprintf(
                self::FILE_FORMAT,
                $file->path,
                $file->title,
                $body === null ? self::UNREADABLE_FILE : trim($body),
            ));
        }

        $blocks[] = new TextContent(sprintf(self::REQUEST_FORMAT, $message));

        return new UserMessage($blocks);
    }
}
