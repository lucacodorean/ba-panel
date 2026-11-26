<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAssessor;
use Lucacodorean\BaPanel\Core\Graph\DocumentRenderer;
use Lucacodorean\BaPanel\Core\Graph\FrontMatterParser;
use Lucacodorean\BaPanel\Core\Graph\Node;
use Lucacodorean\BaPanel\Core\Markdown\SafeMarkdownConverter;
use Lucacodorean\BaPanel\Core\Reports\Enums\ReportStatus;
use Lucacodorean\BaPanel\Core\Reports\FeasibilityReport;
use Lucacodorean\BaPanel\Core\Reports\PackageVersion;
use Lucacodorean\BaPanel\Core\Reports\ReportMetadata;
use Lucacodorean\BaPanel\Core\Reports\ReportMetadataFactory;
use Lucacodorean\BaPanel\Tests\Support\FeasibilityReportFixture;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use NeuronAI\Agent\AgentInterface;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Testing\RequestRecord;

/*
| These tests use the Neuron fake provider. They never send a request to a real AI provider.
*/

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk and forget it when $cleanup is true.
 */
function assessorFixtures(bool $cleanup = false): ?TemporaryFilesystem
{
    static $fs = null;

    if ($cleanup) {
        $fs?->cleanup();
        $fs = null;

        return null;
    }

    return $fs ??= new TemporaryFilesystem;
}

/**
 * Build the assessor with its real dependencies.
 */
function feasibilityAssessor(): FeasibilityAssessor
{
    return new FeasibilityAssessor(
        new DocumentRenderer(new FrontMatterParser, new SafeMarkdownConverter),
        new ReportMetadataFactory(new PackageVersion),
    );
}

/**
 * Write the document and return its node.
 */
function assessorNode(string $id, string $path, string $title, string $contents): Node
{
    $absolute = (assessorFixtures() ?? throw new RuntimeException('No temporary filesystem.'))->file($path, $contents);

    return new Node($id, pathinfo($path, PATHINFO_FILENAME), $title, $path, $absolute);
}

/**
 * Return the text of each content block of the message.
 *
 * @return list<string>
 */
function assessorBlocks(UserMessage $message): array
{
    return array_values(array_map(
        static fn ($block): string => $block instanceof TextContent ? $block->content : '',
        $message->getContentBlocks(),
    ));
}

afterEach(function (): void {
    assessorFixtures(cleanup: true);
    Mockery::close();
});

it('puts the file count first and the request last', function (): void {
    $files = [
        assessorNode('aaaaaaaaaaaa', 'docs/one.md', 'One', "# One\n\nFirst body.\n"),
        assessorNode('bbbbbbbbbbbb', 'docs/two.md', 'Two', "# Two\n\nSecond body.\n"),
    ];

    $blocks = assessorBlocks(feasibilityAssessor()->message('Add a CSV export.', $files));

    expect($blocks)->toHaveCount(4)
        ->and($blocks[0])->toBe('Documentation files given as context: 2.')
        ->and($blocks[3])->toBe("Request to assess:\nAdd a CSV export.");
});

it('adds one block per file in the given order', function (): void {
    $files = [
        assessorNode('aaaaaaaaaaaa', 'docs/one.md', 'One title', "# One\n\nFirst body.\n"),
        assessorNode('bbbbbbbbbbbb', 'docs/two.md', 'Two title', "\n\n# Two\n\nSecond body.\n\n"),
    ];

    $blocks = assessorBlocks(feasibilityAssessor()->message('Request.', $files));

    expect($blocks[1])->toBe("File: docs/one.md\nTitle: One title\n\n# One\n\nFirst body.")
        ->and($blocks[2])->toBe("File: docs/two.md\nTitle: Two title\n\n# Two\n\nSecond body.");
});

it('removes the front matter from each file', function (): void {
    $files = [assessorNode('aaaaaaaaaaaa', 'docs/one.md', 'One', "---\ntitle: Secret title\nrelations:\n  - other.md\n---\n# Body\n")];

    $block = assessorBlocks(feasibilityAssessor()->message('Request.', $files))[1];

    expect($block)->toBe("File: docs/one.md\nTitle: One\n\n# Body")
        ->and($block)->not->toContain('Secret title')
        ->and($block)->not->toContain('relations')
        ->and($block)->not->toContain('---');
});

it('does not send the absolute path of a file', function (): void {
    $node = assessorNode('aaaaaaaaaaaa', 'docs/one.md', 'One', '# One');

    $content = (string) feasibilityAssessor()->message('Request.', [$node])->getContent();

    expect($content)->not->toContain($node->absolutePath);
});

it('marks a file that cannot be read', function (): void {
    $node = new Node('aaaaaaaaaaaa', 'gone', 'Gone', 'docs/gone.md', '/does/not/exist/gone.md');

    $blocks = assessorBlocks(feasibilityAssessor()->message('Request.', [$node]));

    expect($blocks[1])->toBe("File: docs/gone.md\nTitle: Gone\n\nThe file could not be read.");
});

it('says that no files are given when the context is empty', function (): void {
    $blocks = assessorBlocks(feasibilityAssessor()->message('Request.', []));

    expect($blocks)->toBe([
        'No documentation files are given. Say that the documentation gives no information.',
        "Request to assess:\nRequest.",
    ]);
});

it('returns a user message', function (): void {
    expect(feasibilityAssessor()->message('Request.', []))->toBeInstanceOf(UserMessage::class);
});

it('returns the report of the agent with new metadata', function (): void {
    $provider = new FakeAIProvider(FeasibilityReportFixture::message());

    // The assessor uses the current time. The test compares it with times before and after the call.
    // The ISO-8601 format has no fractions of a second. Thus the start is rounded down to the second.
    $before = new DateTimeImmutable('@'.time());
    $report = feasibilityAssessor()->assess(new FeasibilityAgent($provider, 'Test persona.'), 'Add a CSV export.', []);
    $after = new DateTimeImmutable;
    $metadata = $report->metadata();
    $created = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $metadata->createdAt);

    expect($report)->toBeInstanceOf(FeasibilityReport::class)
        ->and($report->requestTitle)->toBe('Export invoices as CSV')
        ->and($metadata->reportId)->toMatch('/^FR-\d{8}-[a-f0-9]{6}$/')
        ->and($metadata->reportStatus)->toBe(ReportStatus::Draft)
        ->and($metadata->author)->toBe(ReportMetadata::AUTHOR)
        ->and($created)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($created >= $before && $created <= $after)->toBeTrue()
        ->and($metadata->generatedAt)->toBe($metadata->createdAt);
});

it('sends the built message to the provider in one structured request', function (): void {
    $provider = new FakeAIProvider(FeasibilityReportFixture::message());
    $files = [assessorNode('aaaaaaaaaaaa', 'docs/invoices.md', 'Invoices', "---\ntitle: Invoices\n---\n# Invoices\n\nThe invoice module.\n")];

    feasibilityAssessor()->assess(new FeasibilityAgent($provider, 'Test persona.'), 'Add a CSV export.', $files);

    $provider->assertMethodCallCount('structured', 1);
    $provider->assertSent(static function (RequestRecord $record): bool {
        $content = (string) $record->messages[array_key_last($record->messages)]->getContent();

        return $record->structuredClass === FeasibilityReport::class
            && str_contains($content, "File: docs/invoices.md\nTitle: Invoices\n\n# Invoices\n\nThe invoice module.")
            && str_contains($content, "Request to assess:\nAdd a CSV export.")
            && ! str_contains($content, 'title: Invoices');
    });
});

it('sets a new random thread id on each agent', function (): void {
    $assessor = feasibilityAssessor();
    $first = new FeasibilityAgent(new FakeAIProvider(FeasibilityReportFixture::message()), 'Test persona.');
    $second = new FeasibilityAgent(new FakeAIProvider(FeasibilityReportFixture::message()), 'Test persona.');

    $assessor->assess($first, 'Request.', []);
    $assessor->assess($second, 'Request.', []);

    expect($first->getThreadId())->toMatch('/^[a-f0-9]{32}$/')
        ->and($second->getThreadId())->toMatch('/^[a-f0-9]{32}$/')
        ->and($first->getThreadId())->not->toBe($second->getThreadId());
});

it('creates new metadata for each report', function (): void {
    $assessor = feasibilityAssessor();

    $first = $assessor->assess(new FeasibilityAgent(new FakeAIProvider(FeasibilityReportFixture::message()), 'Test persona.'), 'Request.', []);
    $second = $assessor->assess(new FeasibilityAgent(new FakeAIProvider(FeasibilityReportFixture::message()), 'Test persona.'), 'Request.', []);

    expect($first->metadata()->reportId)->not->toBe($second->metadata()->reportId);
});

it('throws when the agent does not return a feasibility report', function (): void {
    /** @var AgentInterface&Mockery\MockInterface $agent */
    $agent = Mockery::mock(AgentInterface::class);
    $agent->shouldReceive('setThreadId')->once()->andReturnSelf();
    $agent->shouldReceive('structured')->once()->andReturn(new stdClass);

    expect(fn () => feasibilityAssessor()->assess($agent, 'Request.', []))
        ->toThrow(UnexpectedValueException::class, 'The agent did not return a feasibility report.');
});

it('throws when the agent returns null', function (): void {
    /** @var AgentInterface&Mockery\MockInterface $agent */
    $agent = Mockery::mock(AgentInterface::class);
    $agent->shouldReceive('setThreadId')->once()->andReturnSelf();
    $agent->shouldReceive('structured')->once()->andReturnNull();

    expect(fn () => feasibilityAssessor()->assess($agent, 'Request.', []))
        ->toThrow(UnexpectedValueException::class);
});
