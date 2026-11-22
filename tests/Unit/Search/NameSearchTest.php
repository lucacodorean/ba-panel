<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Graph\Graph;
use Lucacodorean\BaPanel\Graph\Node;
use Lucacodorean\BaPanel\Search\NameSearch;
use Lucacodorean\BaPanel\Search\SearchResult;

/**
 * Create a graph with one node per display path. The node name is the file name without the extension.
 */
function searchGraph(string ...$paths): Graph
{
    return new Graph(array_values(array_map(
        static fn (string $path): Node => new Node(
            id: substr(sha1($path), 0, 12),
            name: pathinfo($path, PATHINFO_FILENAME),
            title: $path,
            path: $path,
            absolutePath: '/root/'.$path,
        ),
        $paths,
    )));
}

/**
 * Return the result paths and scores.
 *
 * @param  list<SearchResult>  $results
 * @return array<string, float>
 */
function searchScores(array $results): array
{
    $scores = [];

    foreach ($results as $result) {
        $scores[$result->node->path] = $result->score;
    }

    return $scores;
}

it('uses a default threshold of 0.8', function (): void {
    expect(NameSearch::THRESHOLD)->toBe(0.8)
        ->and((new NameSearch)->threshold())->toBe(0.8)
        ->and((new NameSearch(0.5))->threshold())->toBe(0.5);
});

it('scores a prefix match as 1.0', function (): void {
    $results = (new NameSearch)->search(searchGraph('docs/endpoints.md'), 'endp');

    expect(searchScores($results))->toBe(['docs/endpoints.md' => 1.0]);
});

it('scores an exact match as 1.0', function (): void {
    $results = (new NameSearch)->search(searchGraph('docs/api.md'), 'api');

    expect(searchScores($results))->toBe(['docs/api.md' => 1.0]);
});

it('matches the query without case', function (): void {
    $results = (new NameSearch)->search(searchGraph('docs/DataModel.md'), 'DATA model');

    expect(searchScores($results))->toBe(['docs/DataModel.md' => 1.0]);
});

it('uses the trigram cosine when the name token does not start with the query token', function (): void {
    $results = (new NameSearch)->search(searchGraph('docs/endpoint.md'), 'endpoints');

    expect($results)->toHaveCount(1)
        ->and($results[0]->score)->toEqualWithDelta(7 / sqrt(72), 1e-9);
});

it('averages the best similarity of each query token', function (): void {
    $results = (new NameSearch(0.0))->search(searchGraph('docs/api.md'), 'api zzzz');

    expect($results)->toHaveCount(1)
        ->and($results[0]->score)->toEqualWithDelta(0.5, 1e-9);
});

it('uses the best name token for each query token', function (): void {
    $results = (new NameSearch)->search(searchGraph('docs/endpoint-api.md'), 'api endpoints');

    expect($results)->toHaveCount(1)
        ->and($results[0]->score)->toEqualWithDelta((1.0 + 7 / sqrt(72)) / 2, 1e-9);
});

it('drops results below the threshold', function (): void {
    expect((new NameSearch)->search(searchGraph('docs/api.md'), 'api zzzz'))->toBe([]);
});

it('keeps a result with a score equal to the threshold', function (): void {
    $results = (new NameSearch(0.5))->search(searchGraph('docs/api.md'), 'api zzzz');

    expect(searchScores($results))->toBe(['docs/api.md' => 0.5]);
});

it('uses a custom threshold', function (): void {
    $graph = searchGraph('docs/api.md');

    expect((new NameSearch(0.4))->search($graph, 'api zzzz'))->toHaveCount(1)
        ->and((new NameSearch(0.6))->search($graph, 'api zzzz'))->toBe([]);
});

it('does not match a name without common trigrams', function (): void {
    expect((new NameSearch)->search(searchGraph('docs/readme.md', 'docs/rapid.md'), 'api'))->toBe([]);
});

it('does not match a strong typo', function (): void {
    expect((new NameSearch)->search(searchGraph('docs/endpoints.md'), 'endpionts'))->toBe([]);
});

it('sorts by score descending and then by path ascending', function (): void {
    $graph = searchGraph('docs/z/endpoints.md', 'docs/a/endpoint.md', 'docs/b/endpoints-v2.md', 'docs/a/endpoints.md', 'docs/readme.md');

    $paths = array_keys(searchScores((new NameSearch)->search($graph, 'endpoints')));

    expect($paths)->toBe(['docs/a/endpoints.md', 'docs/b/endpoints-v2.md', 'docs/z/endpoints.md', 'docs/a/endpoint.md']);
});

it('returns no results for a blank query', function (string $query): void {
    expect((new NameSearch(0.0))->search(searchGraph('docs/api.md'), $query))->toBe([]);
})->with(['', '   ', '--__..']);

it('returns no results for an empty graph', function (): void {
    expect((new NameSearch)->search(new Graph, 'api'))->toBe([]);
});

it('scores a node whose name has no tokens as 0.0', function (): void {
    expect(searchScores((new NameSearch(0.0))->search(searchGraph('docs/---.md'), 'api')))->toBe(['docs/---.md' => 0.0])
        ->and((new NameSearch)->search(searchGraph('docs/---.md'), 'api'))->toBe([]);
});

it('returns the node in the result', function (): void {
    $graph = searchGraph('docs/api.md');
    $results = (new NameSearch)->search($graph, 'api');

    expect($results[0])->toBeInstanceOf(SearchResult::class)
        ->and($results[0]->node)->toBe($graph->nodes()[0]);
});
