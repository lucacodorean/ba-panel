<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Ai\PromptLoader;
use Lucacodorean\BaPanel\Core\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Core\Feasibility\FeasibilityService;
use Lucacodorean\BaPanel\Symfony\Controller\FeasibilityController;
use Lucacodorean\BaPanel\Tests\Support\FeasibilityReportFixture;
use Lucacodorean\BaPanel\Tests\Support\Symfony\SymfonyPanel;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\Providers\ProviderResponse;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Testing\RequestRecord;
use Symfony\Component\HttpFoundation\Response;

/*
| These tests set a fake agent factory with one fake provider in the container. They never send a request to a real AI provider.
*/

/**
 * Write two documents and boot an enabled panel.
 */
function symfonyFeasibilityPanel(bool $csrf = false, ?FakeAIProvider $provider = null): SymfonyPanel
{
    SymfonyPanel::file('docs/invoices.md', "---\ntitle: Invoices module\n---\n# Invoices\n\nThe invoices module lists all invoices.\n");
    SymfonyPanel::file('docs/billing.md', "# Billing\n\nBilling runs each night.\n");

    return SymfonyPanel::create(SymfonyPanel::enabledConfig(), $csrf)->boot($provider);
}

/**
 * Assert the JSON error response.
 */
function symfonyFeasibilityError(Response $response, int $status, string $message): void
{
    expect($response->getStatusCode())->toBe($status)
        ->and($response->headers->get('Content-Type'))->toBe('application/json')
        ->and(SymfonyPanel::json($response))->toBe(['message' => $message]);
}

it('returns the report, the Markdown and the HTML', function (): void {
    $panel = symfonyFeasibilityPanel(provider: new FakeAIProvider(FeasibilityReportFixture::message()));

    $response = $panel->postFeasibility(['message' => 'Add a CSV export.', 'context' => [SymfonyPanel::nodeId('docs/invoices.md')]]);
    $json = SymfonyPanel::json($response);

    expect($response->getStatusCode())->toBe(200)
        ->and(array_keys($json))->toBe(['report', 'markdown', 'html'])
        ->and($json['report']['request_title'])->toBe('Export invoices as CSV')
        ->and($json['report']['metadata']['report_id'])->toMatch('/^FR-\d{8}-[a-f0-9]{6}$/')
        ->and($json['markdown'])->toStartWith('# Feasibility Report: Export invoices as CSV')
        ->and($json['html'])->toContain('<h1>Feasibility Report: Export invoices as CSV</h1>');
});

it('sends the trimmed message and the context files in order', function (): void {
    $provider = new FakeAIProvider(FeasibilityReportFixture::message());
    $panel = symfonyFeasibilityPanel(provider: $provider);
    $billing = SymfonyPanel::nodeId('docs/billing.md');
    $invoices = SymfonyPanel::nodeId('docs/invoices.md');

    $panel->postFeasibility(['message' => "  Add a CSV export.\n", 'context' => [$billing, $invoices, $billing]]);

    $provider->assertMethodCallCount('structured', 1);
    $provider->assertSent(static function (RequestRecord $record): bool {
        $content = (string) $record->messages[array_key_last($record->messages)]->getContent();

        return str_contains($content, 'Documentation files given as context: 2.')
            && strpos($content, 'Title: Billing') < strpos($content, 'Title: Invoices module')
            && str_ends_with($content, "Request to assess:\nAdd a CSV export.");
    });
});

it('uses an empty context when the context is missing', function (): void {
    $provider = new FakeAIProvider(FeasibilityReportFixture::message());
    $panel = symfonyFeasibilityPanel(provider: $provider);

    expect($panel->postFeasibility(['message' => 'Request.'])->getStatusCode())->toBe(200);

    $provider->assertSent(static fn (RequestRecord $record): bool => str_starts_with(
        (string) $record->messages[array_key_last($record->messages)]->getContent(),
        'No documentation files are given.',
    ));
});

it('builds a new agent and new metadata for each request', function (): void {
    $provider = new FakeAIProvider(FeasibilityReportFixture::message(), FeasibilityReportFixture::message());
    $panel = symfonyFeasibilityPanel(provider: $provider);

    $first = SymfonyPanel::json($panel->postFeasibility(['message' => 'Request.']));
    $second = SymfonyPanel::json($panel->postFeasibility(['message' => 'Request.']));

    expect($first['report']['metadata']['report_id'])->not->toBe($second['report']['metadata']['report_id']);
    $provider->assertMethodCallCount('structured', 2);
});

it('refuses an invalid request with 422', function (array $payload, string $message): void {
    $provider = new FakeAIProvider(FeasibilityReportFixture::message());
    $panel = symfonyFeasibilityPanel(provider: $provider);

    symfonyFeasibilityError($panel->postFeasibility($payload), 422, $message);

    $provider->assertNothingSent();
    expect($panel->logger->recordsOf('error'))->toBe([]);
})->with([
    'missing message' => [['context' => []], 'The message is required.'],
    'empty message' => [['message' => ''], 'The message is required.'],
    'blank message' => [['message' => "  \n\t "], 'The message is required.'],
    'message is a number' => [['message' => 42], 'The message is required.'],
    'message too long' => [['message' => str_repeat('é', 5001)], 'The message must have at most 5000 characters.'],
    'context is null' => [['message' => 'Request.', 'context' => null], 'The context must be a list of file ids.'],
    'context is a string' => [['message' => 'Request.', 'context' => 'aaaaaaaaaaaa'], 'The context must be a list of file ids.'],
    'context is a map' => [['message' => 'Request.', 'context' => ['first' => 'aaaaaaaaaaaa']], 'The context must be a list of file ids.'],
    'too many context ids' => [['message' => 'Request.', 'context' => array_fill(0, 51, 'aaaaaaaaaaaa')], 'The context must have at most 50 files.'],
    'upper case id' => [['message' => 'Request.', 'context' => ['AAAAAAAAAAAA']], 'The context contains an invalid file id.'],
    'id with a final line break' => [['message' => 'Request.', 'context' => ["aaaaaaaaaaaa\n"]], 'The context contains an invalid file id.'],
    'path as id' => [['message' => 'Request.', 'context' => ['../../etc/passwd']], 'The context contains an invalid file id.'],
    'unknown id' => [['message' => 'Request.', 'context' => ['ffffffffffff']], 'The context contains an unknown file id: ffffffffffff.'],
]);

it('refuses a body that is not a JSON object with 422', function (string $body): void {
    $provider = new FakeAIProvider(FeasibilityReportFixture::message());
    $panel = symfonyFeasibilityPanel(provider: $provider);

    symfonyFeasibilityError($panel->postFeasibility($body), 422, FeasibilityController::BODY_INVALID);

    $provider->assertNothingSent();
})->with([
    'not JSON' => ['not json'],
    'empty body' => [''],
    'JSON string' => ['"Request."'],
    'JSON number' => ['42'],
    'JSON null' => ['null'],
]);

it('uses the public messages of the controller', function (): void {
    expect(FeasibilityController::BODY_INVALID)->toBe('The request body must be a JSON object.')
        ->and(FeasibilityController::CSRF_INVALID)->toBe('The CSRF token is invalid.');
});

it('accepts a request without a CSRF header when the host has no CSRF token manager', function (): void {
    $panel = symfonyFeasibilityPanel(provider: new FakeAIProvider(FeasibilityReportFixture::message()));

    expect($panel->postFeasibility(['message' => 'Request.'])->getStatusCode())->toBe(200);
});

it('accepts the CSRF token of the panel page', function (): void {
    $panel = symfonyFeasibilityPanel(csrf: true, provider: new FakeAIProvider(FeasibilityReportFixture::message()));
    $token = $panel->csrfToken();

    expect($token)->not->toBeNull()
        ->and($panel->postFeasibility(['message' => 'Request.'], $token)->getStatusCode())->toBe(200);
});

it('checks the CSRF token before the body', function (): void {
    $panel = symfonyFeasibilityPanel(csrf: true);
    $token = $panel->csrfToken();

    symfonyFeasibilityError($panel->postFeasibility('not json', $token), 422, FeasibilityController::BODY_INVALID);
    symfonyFeasibilityError($panel->postFeasibility('not json'), 403, FeasibilityController::CSRF_INVALID);
});

it('refuses a missing or invalid CSRF token with 403', function (?string $token): void {
    $provider = new FakeAIProvider(FeasibilityReportFixture::message());
    $panel = symfonyFeasibilityPanel(csrf: true, provider: $provider);
    $panel->csrfToken();

    symfonyFeasibilityError($panel->postFeasibility(['message' => 'Request.'], $token), 403, 'The CSRF token is invalid.');

    $provider->assertNothingSent();
})->with([
    'missing header' => [null],
    'empty header' => [''],
    'wrong token' => ['wrong-token'],
]);

it('returns 503 when the AI provider is not configured', function (): void {
    $panel = symfonyFeasibilityPanel();
    $panel->useAgentFactory(new FeasibilityAgentFactory(new ProviderFactory, new AiSettings, new PromptLoader(SymfonyPanel::projectDir())));

    $response = $panel->postFeasibility(['message' => 'Request.']);

    symfonyFeasibilityError($response, 503, FeasibilityService::AI_NOT_CONFIGURED);
    expect((string) $response->getContent())->not->toContain('BAP_AI_');

    $errors = $panel->logger->recordsOf('error');

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['context']['exception'] ?? null)->toBeInstanceOf(InvalidConfigurationException::class);
});

it('validates the request before it builds the agent', function (): void {
    $panel = symfonyFeasibilityPanel();
    $panel->useAgentFactory(new FeasibilityAgentFactory(new ProviderFactory, new AiSettings, new PromptLoader(SymfonyPanel::projectDir())));

    symfonyFeasibilityError($panel->postFeasibility(['message' => '']), 422, 'The message is required.');
});

it('returns 502 and logs the error when the provider has no response', function (): void {
    $panel = symfonyFeasibilityPanel(provider: new FakeAIProvider);

    $response = $panel->postFeasibility(['message' => 'Request.']);

    symfonyFeasibilityError($response, 502, FeasibilityService::AGENT_FAILED);
    expect((string) $response->getContent())->not->toContain('FakeAIProvider');

    $errors = $panel->logger->recordsOf('error');

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['context']['exception'] ?? null)->toBeInstanceOf(ProviderException::class);
});

it('returns 502 when the provider does not return valid JSON', function (): void {
    $provider = new FakeAIProvider(new AssistantMessage('not json'), new AssistantMessage('still not json'));
    $panel = symfonyFeasibilityPanel(provider: $provider);

    symfonyFeasibilityError($panel->postFeasibility(['message' => 'Request.']), 502, FeasibilityService::AGENT_FAILED);
    $provider->assertMethodCallCount('structured', 2);
});

it('does not send the provider error details in the response', function (): void {
    $panel = symfonyFeasibilityPanel(provider: new class extends FakeAIProvider
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

    $response = $panel->postFeasibility(['message' => 'Request.']);

    symfonyFeasibilityError($response, 502, FeasibilityService::AGENT_FAILED);
    expect((string) $response->getContent())->not->toContain('secret-key-123')
        ->and((string) $response->getContent())->not->toContain('api.example.test')
        ->and($panel->logger->recordsOf('error')[0]['context']['exception'] ?? null)->toBeInstanceOf(RuntimeException::class);
});

it('returns 502 when the report template fails', function (): void {
    $panel = symfonyFeasibilityPanel(provider: new FakeAIProvider(FeasibilityReportFixture::message()));
    SymfonyPanel::file('templates/bundles/BaPanelBundle/reports/feasibility.php', '<?php throw new RuntimeException("Template failed with secret-detail.");');

    $response = $panel->postFeasibility(['message' => 'Request.']);

    symfonyFeasibilityError($response, 502, FeasibilityService::AGENT_FAILED);
    expect((string) $response->getContent())->not->toContain('secret-detail');
});

it('uses a host report template', function (): void {
    $panel = symfonyFeasibilityPanel(provider: new FakeAIProvider(FeasibilityReportFixture::message()));
    SymfonyPanel::file('templates/bundles/BaPanelBundle/reports/feasibility.php', "# Host report <?= \$e(\$report->requestTitle) ?>\n");

    $json = SymfonyPanel::json($panel->postFeasibility(['message' => 'Request.']));

    expect($json['markdown'])->toBe("# Host report Export invoices as CSV\n");
});
