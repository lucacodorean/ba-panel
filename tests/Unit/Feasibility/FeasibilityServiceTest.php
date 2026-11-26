<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAssessor;
use Lucacodorean\BaPanel\Core\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidFeasibilityRequestException;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidTemplateException;
use Lucacodorean\BaPanel\Core\Feasibility\FeasibilityService;
use Lucacodorean\BaPanel\Core\Graph\DocumentRenderer;
use Lucacodorean\BaPanel\Core\Graph\FrontMatterParser;
use Lucacodorean\BaPanel\Core\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Core\Markdown\SafeMarkdownConverter;
use Lucacodorean\BaPanel\Core\Reports\PackageVersion;
use Lucacodorean\BaPanel\Core\Reports\ReportMetadataFactory;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;
use Lucacodorean\BaPanel\Tests\Support\FakeFeasibilityAgentFactory;
use Lucacodorean\BaPanel\Tests\Support\FeasibilityReportFixture;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Testing\RequestRecord;

/*
| These tests use the Neuron fake provider. They never send a request to a real AI provider.
*/

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk and forget it when $cleanup is true.
 */
function feasibilityServiceFixtures(bool $cleanup = false): ?TemporaryFilesystem
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
 * Build the service with the given agent factory and template directories. Write one document as the context.
 *
 * @param  list<string>|null  $templates
 */
function feasibilityService(FeasibilityAgentFactory $agents, ?array $templates = null): FeasibilityService
{
    $fs = feasibilityServiceFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('docs/invoices.md', "---\ntitle: Invoices module\n---\n# Invoices\n\nThe invoices module lists all invoices.\n");

    $frontMatter = new FrontMatterParser;
    $converter = new SafeMarkdownConverter;
    $documents = new DocumentRenderer($frontMatter, $converter);

    return new FeasibilityService(
        new GraphBuilder([$fs->path('docs')], $fs->root(), $frontMatter),
        $agents,
        new FeasibilityAssessor($documents, new ReportMetadataFactory(new PackageVersion)),
        new TemplateRenderer($templates ?? [dirname(__DIR__, 3).'/resources/views']),
        $converter,
    );
}

/**
 * Build an agent factory that gives each agent the fake provider.
 */
function feasibilityServiceAgents(FakeAIProvider $provider): FeasibilityAgentFactory
{
    return new FakeFeasibilityAgentFactory(new ProviderFactory, new AiSettings, $provider);
}

/**
 * Return the node id of the fixture file.
 */
function feasibilityServiceId(string $relative): string
{
    $fs = feasibilityServiceFixtures() ?? throw new RuntimeException('No temporary filesystem.');

    return substr(sha1((string) realpath($fs->path($relative))), 0, 12);
}

afterEach(function (): void {
    feasibilityServiceFixtures(cleanup: true);
});

it('has the public error messages', function (): void {
    expect(FeasibilityService::AI_NOT_CONFIGURED)->toBe('The feasibility agent is not configured.')
        ->and(FeasibilityService::AGENT_FAILED)->toBe('The feasibility agent could not create a report. Try again later.');
});

it('returns the report, the Markdown and the HTML', function (): void {
    $service = feasibilityService(feasibilityServiceAgents(new FakeAIProvider(FeasibilityReportFixture::message())));

    $result = $service->assess('Add a CSV export.', [feasibilityServiceId('docs/invoices.md')]);

    expect(array_keys($result))->toBe(['report', 'markdown', 'html'])
        ->and($result['report']['request_title'])->toBe('Export invoices as CSV')
        ->and($result['report']['metadata']['report_id'])->toMatch('/^FR-\d{8}-[a-f0-9]{6}$/')
        ->and($result['markdown'])->toStartWith('# Feasibility Report: Export invoices as CSV')
        ->and($result['markdown'])->toEndWith("</sub>\n")
        ->and($result['html'])->toContain('<h1>Feasibility Report: Export invoices as CSV</h1>');
});

it('sends the trimmed message and the context file to the provider', function (): void {
    $provider = new FakeAIProvider(FeasibilityReportFixture::message());
    $service = feasibilityService(feasibilityServiceAgents($provider));

    $service->assess("  Add a CSV export.\n", [feasibilityServiceId('docs/invoices.md')]);

    $provider->assertMethodCallCount('structured', 1);
    $provider->assertSent(static function (RequestRecord $record): bool {
        $content = (string) $record->messages[array_key_last($record->messages)]->getContent();

        return str_contains($content, 'Documentation files given as context: 1.')
            && str_contains($content, 'Title: Invoices module')
            && str_ends_with($content, "Request to assess:\nAdd a CSV export.");
    });
});

it('builds a new agent and new metadata for each call', function (): void {
    $provider = new FakeAIProvider(FeasibilityReportFixture::message(), FeasibilityReportFixture::message());
    $service = feasibilityService(feasibilityServiceAgents($provider));

    $first = $service->assess('Request.', []);
    $second = $service->assess('Request.', []);

    expect($first['report']['metadata']['report_id'])->not->toBe($second['report']['metadata']['report_id']);
    $provider->assertMethodCallCount('structured', 2);
});

it('does not keep the history of a previous call', function (): void {
    $provider = new FakeAIProvider(FeasibilityReportFixture::message(), FeasibilityReportFixture::message());
    $service = feasibilityService(feasibilityServiceAgents($provider));

    $service->assess('First request.', []);
    $service->assess('Second request.', []);

    $records = $provider->getRecorded();
    $second = implode("\n", array_map(static fn ($message): string => (string) $message->getContent(), $records[1]->messages));

    // The stateless agent sends only the second request. The first request is not in the history.
    expect($records)->toHaveCount(2)
        ->and(count($records[1]->messages))->toBe(count($records[0]->messages))
        ->and($second)->toContain('Second request.')
        ->and($second)->not->toContain('First request.');
});

it('escapes raw HTML of the report in the HTML', function (): void {
    $service = feasibilityService(feasibilityServiceAgents(new FakeAIProvider(FeasibilityReportFixture::message(FeasibilityReportFixture::data([
        'recommendation' => 'Run <script>alert(1)</script> now.',
    ])))));

    $result = $service->assess('Request.', []);

    expect($result['markdown'])->toContain('<script>alert(1)</script>')
        ->and($result['html'])->not->toContain('<script>')
        ->and($result['html'])->toContain('&lt;script&gt;');
});

it('validates the input before it builds the agent', function (mixed $message, mixed $context): void {
    // The real factory with empty settings throws on make(). The validation error comes first.
    $service = feasibilityService(new FeasibilityAgentFactory(new ProviderFactory, new AiSettings));

    expect(fn () => $service->assess($message, $context))->toThrow(InvalidFeasibilityRequestException::class);
})->with([
    'empty message' => ['', []],
    'context not a list' => ['Request.', 'x'],
    'unknown id' => ['Request.', ['ffffffffffff']],
]);

it('does not send an invalid request to the provider', function (): void {
    $provider = new FakeAIProvider(FeasibilityReportFixture::message());
    $service = feasibilityService(feasibilityServiceAgents($provider));

    expect(fn () => $service->assess('', []))->toThrow(InvalidFeasibilityRequestException::class, 'The message is required.');
    $provider->assertNothingSent();
});

it('throws the configuration error of the agent factory', function (): void {
    $service = feasibilityService(new FeasibilityAgentFactory(new ProviderFactory, new AiSettings));

    expect(fn () => $service->assess('Request.', []))->toThrow(InvalidConfigurationException::class);
});

it('throws the provider error', function (): void {
    $service = feasibilityService(feasibilityServiceAgents(new FakeAIProvider));

    expect(fn () => $service->assess('Request.', []))->toThrow(ProviderException::class);
});

it('throws when the report template is missing', function (): void {
    $fs = feasibilityServiceFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $service = feasibilityService(feasibilityServiceAgents(new FakeAIProvider(FeasibilityReportFixture::message())), [$fs->path('missing')]);

    expect(fn () => $service->assess('Request.', []))->toThrow(InvalidTemplateException::class);
});

it('uses the report template of the first template directory', function (): void {
    $fs = feasibilityServiceFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('host/reports/feasibility.php', "# Host report <?= \$e(\$report->requestTitle) ?>\n");
    $service = feasibilityService(
        feasibilityServiceAgents(new FakeAIProvider(FeasibilityReportFixture::message())),
        [$fs->path('host'), dirname(__DIR__, 3).'/resources/views'],
    );

    $result = $service->assess('Request.', []);

    expect($result['markdown'])->toBe("# Host report Export invoices as CSV\n")
        ->and($result['html'])->toBe("<h1>Host report Export invoices as CSV</h1>\n");
});
