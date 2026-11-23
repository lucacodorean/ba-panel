<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Reports\FeasibilityReport;
use Lucacodorean\BaPanel\Tests\Support\FeasibilityReportFixture;
use NeuronAI\Agent\Agent;
use NeuronAI\Exceptions\AgentException;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Testing\RequestRecord;

/*
| These tests use the Neuron fake provider. They never send a request to a real AI provider.
*/

/**
 * Collect every key of the schema recursively.
 *
 * @param  array<mixed>  $schema
 * @return list<string>
 */
function feasibilityAgentSchemaKeys(array $schema): array
{
    $keys = [];

    foreach ($schema as $key => $value) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        if (is_array($value)) {
            $keys = [...$keys, ...feasibilityAgentSchemaKeys($value)];
        }
    }

    return $keys;
}

/**
 * Run a structured request with a valid report response. Return the fake provider and the output.
 *
 * @return array{0: FakeAIProvider, 1: mixed, 2: FeasibilityAgent}
 */
function feasibilityAgentRun(): array
{
    $provider = new FakeAIProvider(FeasibilityReportFixture::message());
    $agent = new FeasibilityAgent($provider, 'agent-test-thread');

    $output = $agent->structured(new UserMessage('Assess the CSV export.'), FeasibilityReport::class);

    return [$provider, $output, $agent];
}

it('extends the neuron agent', function (): void {
    expect(new FeasibilityAgent(new FakeAIProvider))->toBeInstanceOf(Agent::class);
});

it('returns the provider given to the constructor', function (): void {
    $provider = new FakeAIProvider;

    expect((new FeasibilityAgent($provider))->getProvider())->toBe($provider);
});

it('has no thread id when no workflow id is given', function (): void {
    expect((new FeasibilityAgent(new FakeAIProvider))->getThreadId())->toBeNull();
});

it('uses the workflow id given to the constructor as the thread id', function (): void {
    expect((new FeasibilityAgent(new FakeAIProvider, 'thread-1'))->getThreadId())->toBe('thread-1');
});

it('has a large context window', function (): void {
    expect(FeasibilityAgent::CONTEXT_WINDOW)->toBe(150_000);
});

it('has instructions that limit the sources to the given files', function (): void {
    $content = (string) (new FeasibilityAgent(new FakeAIProvider))->getInstructions()->getContent();

    expect(trim($content))->not->toBe('')
        ->and($content)->toContain('Use ONLY the documentation files in the user message')
        ->and($content)->toContain('Not specified in the documentation')
        ->and($content)->toContain('needs_more_info');
});

it('does not call the provider when it is built', function (): void {
    $provider = new FakeAIProvider;

    new FeasibilityAgent($provider);

    $provider->assertNothingSent();
});

it('returns a feasibility report from a valid structured response', function (): void {
    [, $output] = feasibilityAgentRun();

    expect($output)->toBeInstanceOf(FeasibilityReport::class);

    /** @var FeasibilityReport $output */
    expect($output->requestTitle)->toBe('Export invoices as CSV')
        ->and($output->risks)->toHaveCount(1)
        ->and($output->effortBreakdown)->toHaveCount(2);
});

it('sends one structured request', function (): void {
    [$provider] = feasibilityAgentRun();

    $provider->assertCallCount(1);
    $provider->assertMethodCallCount('structured', 1);
    $provider->assertMethodCallCount('chat', 0);
});

it('sends the agent instructions as the system prompt', function (): void {
    [$provider, , $agent] = feasibilityAgentRun();

    $provider->assertSystemPrompt((string) $agent->getInstructions()->getContent());
});

it('sends the report class and the user message', function (): void {
    [$provider] = feasibilityAgentRun();

    $provider->assertSent(static fn (RequestRecord $record): bool => $record->structuredClass === FeasibilityReport::class
        && $record->messages !== []
        && $record->messages[array_key_last($record->messages)]->getContent() === 'Assess the CSV export.');
});

it('sends a schema with the report fields and without the system fields', function (): void {
    [$provider] = feasibilityAgentRun();

    $record = $provider->getRecorded()[0];
    $keys = feasibilityAgentSchemaKeys($record->structuredSchema);

    expect($record->structuredSchema)->not->toBe([])
        ->and($keys)->toContain('requestTitle')
        ->and($keys)->toContain('risks')
        ->and($keys)->toContain('effortBreakdown');

    foreach (['metadata', 'reportId', 'report_id', 'generatedAt', 'generated_at', 'createdAt', 'reportStatus', 'severity', 'effortTotalHours'] as $systemKey) {
        expect($keys)->not->toContain($systemKey);
    }
});

it('retries once and then fails when the response is not valid JSON', function (): void {
    $provider = new FakeAIProvider(new AssistantMessage('not json'), new AssistantMessage('still not json'));

    expect(fn () => (new FeasibilityAgent($provider, 'agent-test-thread'))->structured(new UserMessage('Assess.'), FeasibilityReport::class))
        ->toThrow(AgentException::class);

    $provider->assertMethodCallCount('structured', 2);
});

it('throws when the fake provider has no response', function (): void {
    $provider = new FakeAIProvider;

    expect(fn () => (new FeasibilityAgent($provider, 'agent-test-thread'))->structured(new UserMessage('Assess.'), FeasibilityReport::class))
        ->toThrow(ProviderException::class);
});
