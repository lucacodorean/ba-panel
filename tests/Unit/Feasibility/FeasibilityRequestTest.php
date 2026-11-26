<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Exceptions\InvalidFeasibilityRequestException;
use Lucacodorean\BaPanel\Core\Feasibility\FeasibilityRequest;
use Lucacodorean\BaPanel\Core\Graph\Graph;
use Lucacodorean\BaPanel\Core\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Core\Graph\FrontMatterParser;
use Lucacodorean\BaPanel\Core\Graph\Node;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk and forget it when $cleanup is true.
 */
function feasibilityRequestFixtures(bool $cleanup = false): ?TemporaryFilesystem
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
 * Build the graph of two documents: docs/invoices.md and docs/billing.md.
 */
function feasibilityRequestGraph(): Graph
{
    $fs = feasibilityRequestFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('docs/invoices.md', "# Invoices\n");
    $fs->file('docs/billing.md', "# Billing\n");

    return (new GraphBuilder([$fs->path('docs')], $fs->root(), new FrontMatterParser))->build();
}

/**
 * Return the node id of the fixture file.
 */
function feasibilityRequestId(string $relative): string
{
    $fs = feasibilityRequestFixtures() ?? throw new RuntimeException('No temporary filesystem.');

    return substr(sha1((string) realpath($fs->path($relative))), 0, 12);
}

/**
 * Return the error message of the input. Return null when the input is valid.
 */
function feasibilityRequestError(mixed $message, mixed $context): ?string
{
    try {
        FeasibilityRequest::fromInput($message, $context, feasibilityRequestGraph());
    } catch (InvalidFeasibilityRequestException $exception) {
        return $exception->getMessage();
    }

    return null;
}

afterEach(function (): void {
    feasibilityRequestFixtures(cleanup: true);
});

it('has the request limits', function (): void {
    expect(FeasibilityRequest::MAX_MESSAGE_LENGTH)->toBe(5000)
        ->and(FeasibilityRequest::MAX_CONTEXT_FILES)->toBe(50);
});

it('trims the message', function (): void {
    $request = FeasibilityRequest::fromInput("  Add a CSV export.\n", [], feasibilityRequestGraph());

    expect($request->message)->toBe('Add a CSV export.')
        ->and($request->files)->toBe([]);
});

it('finds each context id in the graph and keeps the order', function (): void {
    $graph = feasibilityRequestGraph();
    $billing = feasibilityRequestId('docs/billing.md');
    $invoices = feasibilityRequestId('docs/invoices.md');

    $request = FeasibilityRequest::fromInput('Request.', [$billing, $invoices], $graph);

    expect(array_map(static fn (Node $node): string => $node->id, $request->files))->toBe([$billing, $invoices])
        ->and($request->files[0])->toBe($graph->node($billing));
});

it('uses a repeated id once and keeps the first position', function (): void {
    $graph = feasibilityRequestGraph();
    $billing = feasibilityRequestId('docs/billing.md');
    $invoices = feasibilityRequestId('docs/invoices.md');

    $request = FeasibilityRequest::fromInput('Request.', [$invoices, $billing, $invoices, $billing], $graph);

    expect(array_map(static fn (Node $node): string => $node->id, $request->files))->toBe([$invoices, $billing])
        ->and(array_keys($request->files))->toBe([0, 1]);
});

it('accepts a message with the maximum length in characters', function (): void {
    $request = FeasibilityRequest::fromInput(str_repeat('é', 5000), [], feasibilityRequestGraph());

    expect(mb_strlen($request->message))->toBe(5000);
});

it('accepts the maximum number of context ids', function (): void {
    feasibilityRequestGraph();
    $context = array_fill(0, 50, feasibilityRequestId('docs/billing.md'));

    expect(feasibilityRequestError('Request.', $context))->toBeNull();
});

it('refuses an invalid input', function (mixed $message, mixed $context, string $error): void {
    expect(feasibilityRequestError($message, $context))->toBe($error);
})->with([
    'missing message' => [null, [], 'The message is required.'],
    'empty message' => ['', [], 'The message is required.'],
    'blank message' => ["  \n\t ", [], 'The message is required.'],
    'message is a number' => [42, [], 'The message is required.'],
    'message is a list' => [['Request.'], [], 'The message is required.'],
    'message too long' => [str_repeat('a', 5001), [], 'The message must have at most 5000 characters.'],
    'multibyte message too long' => [str_repeat('é', 5001), [], 'The message must have at most 5000 characters.'],
    'context is null' => ['Request.', null, 'The context must be a list of file ids.'],
    'context is a string' => ['Request.', 'aaaaaaaaaaaa', 'The context must be a list of file ids.'],
    'context is a map' => ['Request.', ['first' => 'aaaaaaaaaaaa'], 'The context must be a list of file ids.'],
    'context is not a list' => ['Request.', [1 => 'aaaaaaaaaaaa'], 'The context must be a list of file ids.'],
    'context is a number' => ['Request.', 1, 'The context must be a list of file ids.'],
    'too many context ids' => ['Request.', array_fill(0, 51, 'aaaaaaaaaaaa'), 'The context must have at most 50 files.'],
    'upper case id' => ['Request.', ['AAAAAAAAAAAA'], 'The context contains an invalid file id.'],
    'short id' => ['Request.', ['aaaaaaaaaaa'], 'The context contains an invalid file id.'],
    'long id' => ['Request.', ['aaaaaaaaaaaaa'], 'The context contains an invalid file id.'],
    'non hex id' => ['Request.', ['gggggggggggg'], 'The context contains an invalid file id.'],
    'id with a final line break' => ['Request.', ["aaaaaaaaaaaa\n"], 'The context contains an invalid file id.'],
    'id with a leading space' => ['Request.', [' aaaaaaaaaaaa'], 'The context contains an invalid file id.'],
    'path as id' => ['Request.', ['../../etc/passwd'], 'The context contains an invalid file id.'],
    'number as id' => ['Request.', [123456789012], 'The context contains an invalid file id.'],
    'null as id' => ['Request.', [null], 'The context contains an invalid file id.'],
    'list as id' => ['Request.', [['aaaaaaaaaaaa']], 'The context contains an invalid file id.'],
    'unknown id' => ['Request.', ['ffffffffffff'], 'The context contains an unknown file id: ffffffffffff.'],
]);

it('checks the message before the context', function (): void {
    expect(feasibilityRequestError('', 'not a list'))->toBe('The message is required.');
});

it('checks the context size before the ids', function (): void {
    expect(feasibilityRequestError('Request.', array_fill(0, 51, 'not-an-id')))->toBe('The context must have at most 50 files.');
});

it('refuses an unknown id after a known id', function (): void {
    feasibilityRequestGraph();

    expect(feasibilityRequestError('Request.', [feasibilityRequestId('docs/invoices.md'), 'ffffffffffff']))
        ->toBe('The context contains an unknown file id: ffffffffffff.');
});
