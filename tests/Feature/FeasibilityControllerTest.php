<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidTemplateException;
use Lucacodorean\BaPanel\Core\Feasibility\FeasibilityRequest;
use Lucacodorean\BaPanel\Core\Feasibility\FeasibilityService;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;
use Lucacodorean\BaPanel\Tests\Support\FakeFeasibilityAgentFactory;
use Lucacodorean\BaPanel\Tests\Support\FeasibilityReportFixture;
use Lucacodorean\BaPanel\Tests\Support\PanelConfig;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use Lucacodorean\BaPanel\Tests\TestCase;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\Providers\ProviderResponse;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Testing\RequestRecord;

/*
| These tests bind a fake agent factory with one fake provider in the container. They never send a request to a real AI provider.
*/

const FEASIBILITY_URL = '/ba-panel/feasibility';

const FEASIBILITY_AGENT_FAILED = 'The feasibility agent could not create a report. Try again later.';

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk and forget it when $cleanup is true.
 */
function feasibilityFixtures(bool $cleanup = false): ?TemporaryFilesystem
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
 * Create the documentation fixtures and boot the enabled panel. Fake the exception handler of the new application.
 */
function bootFeasibilityPanel(TestCase $test): void
{
    $fs = feasibilityFixtures() ?? throw new RuntimeException('No temporary filesystem.');

    $fs->file('docs/invoices.md', "---\ntitle: Invoices module\nrelations:\n  - billing.md\n---\n# Invoices\n\nThe invoices module lists all invoices.\n");
    $fs->file('docs/billing.md', "# Billing\n\nBilling runs each night.\n");

    $test->withBaPanelConfig(PanelConfig::enabled($fs->path('docs')))->rebootApplication();

    // The fake handler records the reported exceptions. It does not write a log file.
    Exceptions::fake();

    // The default provider has no responses. A test that does not bind a provider gets a 502, never a real request.
    bindFeasibilityProvider($test, new FakeAIProvider);
}

/**
 * Remove the fake agent factory. The container then builds the real factory from the "ba-panel.ai" config.
 * Use this only with a missing or unsupported provider. The real factory then throws before any HTTP request.
 */
function useRealFeasibilityAgentFactory(TestCase $test): void
{
    $test->application()->forgetInstance(FeasibilityAgentFactory::class);
}

/**
 * Replace the agent factory with a fake factory. The fake factory builds each agent with the fake provider.
 * The controller uses the factory, so no request can reach a real AI provider.
 */
function bindFeasibilityProvider(TestCase $test, FakeAIProvider $provider): FakeAIProvider
{
    $app = $test->application();

    $app->instance(FeasibilityAgentFactory::class, new FakeFeasibilityAgentFactory(
        $app->make(ProviderFactory::class),
        $app->make(AiSettings::class),
        $provider,
    ));

    return $provider;
}

/**
 * Return the node id of the fixture file.
 */
function feasibilityNodeId(string $relative): string
{
    $fs = feasibilityFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $path = realpath($fs->path($relative));

    if ($path === false) {
        throw new RuntimeException('Missing fixture: '.$relative);
    }

    return substr(sha1($path), 0, 12);
}

/**
 * Return the display path of the fixture file. The fixtures are outside the base path, so the path is absolute.
 */
function feasibilityNodePath(string $relative): string
{
    $fs = feasibilityFixtures() ?? throw new RuntimeException('No temporary filesystem.');

    return str_replace('\\', '/', (string) realpath($fs->path($relative)));
}

/**
 * Return the text content of the last message of the recorded request.
 */
function feasibilitySentContent(RequestRecord $record): string
{
    return (string) $record->messages[array_key_last($record->messages)]->getContent();
}

/**
 * Send the feasibility request as JSON.
 *
 * @param  array<string, mixed>  $payload
 * @return TestResponse<Illuminate\Http\JsonResponse>
 */
function postFeasibility(TestCase $test, array $payload): TestResponse
{
    /** @var TestResponse<Illuminate\Http\JsonResponse> $response */
    $response = $test->postJson(FEASIBILITY_URL, $payload);

    return $response;
}

afterEach(function (): void {
    feasibilityFixtures(cleanup: true);
});

it('registers the feasibility route under the web middleware', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);

    $route = Route::getRoutes()->getByName('ba-panel.feasibility');

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe('ba-panel/feasibility')
        ->and($route?->methods())->toBe(['POST'])
        ->and($route?->gatherMiddleware())->toContain('web')
        ->and(route('ba-panel.feasibility', absolute: false))->toBe(FEASIBILITY_URL);
});

it('does not register the feasibility route when disabled', function (): void {
    /** @var TestCase $this */
    $this->withBaPanelConfig(PanelConfig::disabled())->rebootApplication();

    expect(Route::has('ba-panel.feasibility'))->toBeFalse();

    $this->postJson(FEASIBILITY_URL, ['message' => 'Request.'])->assertNotFound();
});

it('refuses a GET request', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);

    $this->getJson(FEASIBILITY_URL)->assertMethodNotAllowed();
});

it('returns the report, the Markdown and the HTML', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    bindFeasibilityProvider($this, new FakeAIProvider(FeasibilityReportFixture::message()));

    $response = postFeasibility($this, ['message' => 'Add a CSV export.', 'context' => [feasibilityNodeId('docs/invoices.md')]])
        ->assertOk()
        ->assertJsonStructure(['report' => ['metadata' => ['report_id'], 'request_title', 'risks'], 'markdown', 'html']);

    $json = $response->json();

    expect(array_keys($json))->toBe(['report', 'markdown', 'html'])
        ->and($json['report']['request_title'])->toBe('Export invoices as CSV')
        ->and($json['report']['metadata']['report_id'])->toMatch('/^FR-\d{8}-[a-f0-9]{6}$/')
        ->and($json['report']['metadata']['report_status'])->toBe('draft')
        ->and($json['report']['effort_total_hours'])->toEqual(8.5)
        ->and($json['report']['risks'][0]['severity'])->toBe(8)
        ->and($json['markdown'])->toStartWith('# Feasibility Report: Export invoices as CSV')
        ->and($json['markdown'])->toContain($json['report']['metadata']['report_id'])
        ->and($json['html'])->toContain('<h1>Feasibility Report: Export invoices as CSV</h1>')
        ->and($json['html'])->toContain('<table>');
});

it('sends the request and the file contents to the provider in one structured request', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    $provider = bindFeasibilityProvider($this, new FakeAIProvider(FeasibilityReportFixture::message()));

    postFeasibility($this, [
        'message' => '  Add a CSV export.  ',
        'context' => [feasibilityNodeId('docs/invoices.md'), feasibilityNodeId('docs/billing.md')],
    ])->assertOk();

    $provider->assertCallCount(1);
    $provider->assertMethodCallCount('structured', 1);
    $provider->assertSent(static function (RequestRecord $record): bool {
        $content = feasibilitySentContent($record);
        $invoices = "File: ".feasibilityNodePath('docs/invoices.md')."\nTitle: Invoices module\n\n# Invoices\n\nThe invoices module lists all invoices.";
        $billing = "File: ".feasibilityNodePath('docs/billing.md')."\nTitle: Billing\n\n# Billing\n\nBilling runs each night.";

        return str_contains($content, 'Documentation files given as context: 2.')
            && str_contains($content, $invoices)
            && str_contains($content, $billing)
            && strpos($content, $invoices) < strpos($content, $billing)
            && str_ends_with($content, "Request to assess:\nAdd a CSV export.")
            && ! str_contains($content, 'relations:');
    });
});

it('keeps the order of the context ids', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    $provider = bindFeasibilityProvider($this, new FakeAIProvider(FeasibilityReportFixture::message()));

    postFeasibility($this, [
        'message' => 'Request.',
        'context' => [feasibilityNodeId('docs/billing.md'), feasibilityNodeId('docs/invoices.md')],
    ])->assertOk();

    $provider->assertSent(static function (RequestRecord $record): bool {
        $content = feasibilitySentContent($record);

        return strpos($content, 'File: '.feasibilityNodePath('docs/billing.md')) < strpos($content, 'File: '.feasibilityNodePath('docs/invoices.md'));
    });
});

it('uses a repeated context id once', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    $provider = bindFeasibilityProvider($this, new FakeAIProvider(FeasibilityReportFixture::message()));
    $id = feasibilityNodeId('docs/invoices.md');

    postFeasibility($this, ['message' => 'Request.', 'context' => [$id, $id, $id]])->assertOk();

    $provider->assertSent(static fn (RequestRecord $record): bool => str_contains(feasibilitySentContent($record), 'Documentation files given as context: 1.')
        && substr_count(feasibilitySentContent($record), 'File: ') === 1);
});

it('accepts the maximum number of context ids', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    bindFeasibilityProvider($this, new FakeAIProvider(FeasibilityReportFixture::message()));

    $context = array_fill(0, FeasibilityRequest::MAX_CONTEXT_FILES, feasibilityNodeId('docs/billing.md'));

    postFeasibility($this, ['message' => 'Request.', 'context' => $context])->assertOk();
});

it('accepts a message with the maximum length', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    bindFeasibilityProvider($this, new FakeAIProvider(FeasibilityReportFixture::message()));

    postFeasibility($this, ['message' => str_repeat('é', FeasibilityRequest::MAX_MESSAGE_LENGTH), 'context' => []])->assertOk();
});

it('accepts an empty or missing context', function (array $payload): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    $provider = bindFeasibilityProvider($this, new FakeAIProvider(FeasibilityReportFixture::message()));

    postFeasibility($this, $payload)->assertOk();

    $provider->assertSent(static fn (RequestRecord $record): bool => str_starts_with(
        feasibilitySentContent($record),
        'No documentation files are given.',
    ));
})->with([
    'empty context' => [['message' => 'Request.', 'context' => []]],
    'missing context' => [['message' => 'Request.']],
]);

it('creates a new agent and new metadata for each request', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    $provider = bindFeasibilityProvider($this, new FakeAIProvider(FeasibilityReportFixture::message(), FeasibilityReportFixture::message()));

    $first = postFeasibility($this, ['message' => 'Request.'])->assertOk()->json('report.metadata.report_id');
    $second = postFeasibility($this, ['message' => 'Request.'])->assertOk()->json('report.metadata.report_id');

    expect($first)->not->toBe($second);
    $provider->assertMethodCallCount('structured', 2);
});

it('escapes raw HTML of the report in the HTML response', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    bindFeasibilityProvider($this, new FakeAIProvider(FeasibilityReportFixture::message(FeasibilityReportFixture::data([
        'recommendation' => 'Run <script>alert(1)</script> now.',
        'references' => ['[link](javascript:alert(1))'],
    ]))));

    $json = postFeasibility($this, ['message' => 'Request.'])->assertOk()->json();

    expect($json['markdown'])->toContain('<script>alert(1)</script>')
        ->and($json['html'])->not->toContain('<script>')
        ->and($json['html'])->toContain('&lt;script&gt;')
        ->and($json['html'])->not->toContain('javascript:');
});

it('refuses an invalid request with 422', function (array $payload, string $message): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    $provider = bindFeasibilityProvider($this, new FakeAIProvider(FeasibilityReportFixture::message()));

    postFeasibility($this, $payload)
        ->assertUnprocessable()
        ->assertExactJson(['message' => $message]);

    $provider->assertNothingSent();
})->with([
    'missing message' => [['context' => []], 'The message is required.'],
    'empty message' => [['message' => ''], 'The message is required.'],
    'blank message' => [['message' => "  \n\t "], 'The message is required.'],
    'message is a number' => [['message' => 42], 'The message is required.'],
    'message is a list' => [['message' => ['Request.']], 'The message is required.'],
    'message too long' => [['message' => str_repeat('a', 5001)], 'The message must have at most 5000 characters.'],
    'multibyte message too long' => [['message' => str_repeat('é', 5001)], 'The message must have at most 5000 characters.'],
    'context is a string' => [['message' => 'Request.', 'context' => 'aaaaaaaaaaaa'], 'The context must be a list of file ids.'],
    'context is a map' => [['message' => 'Request.', 'context' => ['first' => 'aaaaaaaaaaaa']], 'The context must be a list of file ids.'],
    'context is a number' => [['message' => 'Request.', 'context' => 1], 'The context must be a list of file ids.'],
    'too many context ids' => [['message' => 'Request.', 'context' => array_fill(0, 51, 'aaaaaaaaaaaa')], 'The context must have at most 50 files.'],
    'upper case id' => [['message' => 'Request.', 'context' => ['AAAAAAAAAAAA']], 'The context contains an invalid file id.'],
    'short id' => [['message' => 'Request.', 'context' => ['aaaaaaaaaaa']], 'The context contains an invalid file id.'],
    'long id' => [['message' => 'Request.', 'context' => ['aaaaaaaaaaaaa']], 'The context contains an invalid file id.'],
    'non hex id' => [['message' => 'Request.', 'context' => ['gggggggggggg']], 'The context contains an invalid file id.'],
    'path as id' => [['message' => 'Request.', 'context' => ['../../etc/passwd']], 'The context contains an invalid file id.'],
    'number as id' => [['message' => 'Request.', 'context' => [123456789012]], 'The context contains an invalid file id.'],
    'list as id' => [['message' => 'Request.', 'context' => [['aaaaaaaaaaaa']]], 'The context contains an invalid file id.'],
    'context is null' => [['message' => 'Request.', 'context' => null], 'The context must be a list of file ids.'],
    'unknown id' => [['message' => 'Request.', 'context' => ['ffffffffffff']], 'The context contains an unknown file id: ffffffffffff.'],
]);

it('refuses an unknown id after a known id', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    $provider = bindFeasibilityProvider($this, new FakeAIProvider(FeasibilityReportFixture::message()));

    postFeasibility($this, ['message' => 'Request.', 'context' => [feasibilityNodeId('docs/invoices.md'), 'ffffffffffff']])
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'The context contains an unknown file id: ffffffffffff.']);

    $provider->assertNothingSent();
});

it('returns 503 when the AI provider is not configured', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    useRealFeasibilityAgentFactory($this);
    $this->application()->make(Repository::class)->set('ba-panel.ai', ['provider' => null, 'model' => null, 'key' => null]);

    postFeasibility($this, ['message' => 'Request.'])
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'The feasibility agent is not configured.']);

    Exceptions::assertReported(InvalidConfigurationException::class);
});

it('reads the AI config of the current request after boot', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    useRealFeasibilityAgentFactory($this);

    // The config is valid at boot. The change after boot applies to the next agent.
    $this->application()->make(Repository::class)->set('ba-panel.ai.key', null);

    postFeasibility($this, ['message' => 'Request.'])
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => FeasibilityService::AI_NOT_CONFIGURED]);
});

it('does not send the configuration errors in the 503 response', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    useRealFeasibilityAgentFactory($this);
    $this->application()->make(Repository::class)->set('ba-panel.ai', ['provider' => null, 'model' => null, 'key' => null]);

    $content = (string) postFeasibility($this, ['message' => 'Request.'])->assertServiceUnavailable()->getContent();

    expect($content)->not->toContain('BAP_AI_')
        ->and(json_decode($content, true))->toBe(['message' => FeasibilityService::AI_NOT_CONFIGURED]);
});

it('returns 503 when the AI provider is not supported', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    useRealFeasibilityAgentFactory($this);
    $this->application()->make(Repository::class)->set('ba-panel.ai.provider', 'unknown-provider');

    postFeasibility($this, ['message' => 'Request.'])
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'The feasibility agent is not configured.']);
});

it('validates the request before it builds the agent', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    useRealFeasibilityAgentFactory($this);
    $this->application()->make(Repository::class)->set('ba-panel.ai', ['provider' => null, 'model' => null, 'key' => null]);

    postFeasibility($this, ['message' => ''])->assertUnprocessable();
});

it('uses the public messages of the feasibility service', function (): void {
    expect(FeasibilityService::AGENT_FAILED)->toBe(FEASIBILITY_AGENT_FAILED)
        ->and(FeasibilityService::AI_NOT_CONFIGURED)->toBe('The feasibility agent is not configured.')
        ->and(FeasibilityRequest::MAX_MESSAGE_LENGTH)->toBe(5000)
        ->and(FeasibilityRequest::MAX_CONTEXT_FILES)->toBe(50);
});

it('does not report a 422 error to the exception handler', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);

    postFeasibility($this, ['message' => ''])->assertUnprocessable();

    Exceptions::assertNothingReported();
});

it('returns 502 and reports the error when the report template fails', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    bindFeasibilityProvider($this, new FakeAIProvider(FeasibilityReportFixture::message()));

    $template = resource_path('views/vendor/ba-panel/reports/feasibility.php');
    $directory = dirname($template);

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($template, '<?php throw new RuntimeException("Template failed with secret-detail.");');

    try {
        $response = postFeasibility($this, ['message' => 'Request.'])
            ->assertStatus(502)
            ->assertExactJson(['message' => FEASIBILITY_AGENT_FAILED]);

        expect((string) $response->getContent())->not->toContain('secret-detail');
        Exceptions::assertReported(static fn (RuntimeException $exception): bool => $exception->getMessage() === 'Template failed with secret-detail.');
    } finally {
        File::deleteDirectory(resource_path('views/vendor/ba-panel'));
    }
});

it('returns 502 when the report template is missing', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    bindFeasibilityProvider($this, new FakeAIProvider(FeasibilityReportFixture::message()));

    // The renderer has no package directory, so it cannot find the report template.
    $this->application()->instance(TemplateRenderer::class, new TemplateRenderer([feasibilityFixtures()?->path('missing') ?? '']));

    postFeasibility($this, ['message' => 'Request.'])
        ->assertStatus(502)
        ->assertExactJson(['message' => FEASIBILITY_AGENT_FAILED]);

    Exceptions::assertReported(InvalidTemplateException::class);
});

it('returns 502 when the provider has no response', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    bindFeasibilityProvider($this, new FakeAIProvider);

    $response = postFeasibility($this, ['message' => 'Request.'])
        ->assertStatus(502)
        ->assertExactJson(['message' => FEASIBILITY_AGENT_FAILED]);

    expect((string) $response->getContent())->not->toContain('FakeAIProvider');
    Exceptions::assertReported(ProviderException::class);
});

it('returns 502 when the provider does not return valid JSON', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    $provider = bindFeasibilityProvider($this, new FakeAIProvider(new AssistantMessage('not json'), new AssistantMessage('still not json')));

    postFeasibility($this, ['message' => 'Request.'])
        ->assertStatus(502)
        ->assertExactJson(['message' => FEASIBILITY_AGENT_FAILED]);

    $provider->assertMethodCallCount('structured', 2);
});

it('returns 502 when the report is not valid', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    $invalid = FeasibilityReportFixture::message(FeasibilityReportFixture::data(['requestTitle' => '']));
    bindFeasibilityProvider($this, new FakeAIProvider($invalid, clone $invalid));

    postFeasibility($this, ['message' => 'Request.'])
        ->assertStatus(502)
        ->assertExactJson(['message' => FEASIBILITY_AGENT_FAILED]);
});

it('returns a valid report after one invalid response', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    $provider = bindFeasibilityProvider($this, new FakeAIProvider(new AssistantMessage('not json'), FeasibilityReportFixture::message()));

    postFeasibility($this, ['message' => 'Request.'])->assertOk();

    $provider->assertMethodCallCount('structured', 2);
});

it('does not send the provider error details in the response', function (): void {
    /** @var TestCase $this */
    bootFeasibilityPanel($this);
    bindFeasibilityProvider($this, new class extends FakeAIProvider
    {
        /**
         * @param  Message|Message[]  $messages
         * @param  array<string, mixed>  $response_schema
         */
        public function structured(array|Message $messages, string $class, array $response_schema): ProviderResponse
        {
            throw new RuntimeException('Provider failed with secret-key-123 at https://api.example.test');
        }
    });

    $response = postFeasibility($this, ['message' => 'Request.'])
        ->assertStatus(502)
        ->assertExactJson(['message' => FEASIBILITY_AGENT_FAILED]);

    expect((string) $response->getContent())->not->toContain('secret-key-123')
        ->and((string) $response->getContent())->not->toContain('api.example.test');
    Exceptions::assertReported(RuntimeException::class);
});
