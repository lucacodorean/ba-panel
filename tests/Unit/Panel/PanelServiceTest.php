<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Graph\DocumentRenderer;
use Lucacodorean\BaPanel\Core\Graph\FrontMatterParser;
use Lucacodorean\BaPanel\Core\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Core\Markdown\SafeMarkdownConverter;
use Lucacodorean\BaPanel\Core\Panel\PanelService;
use Lucacodorean\BaPanel\Core\Search\NameSearch;
use Lucacodorean\BaPanel\Core\Search\Tokenizer;
use Lucacodorean\BaPanel\Core\Search\Vectorizer;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk and forget it when $cleanup is true.
 */
function panelServiceFixtures(bool $cleanup = false): ?TemporaryFilesystem
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
 * Write three documents with two links and build the service. The docs are under the base path.
 */
function panelService(): PanelService
{
    $fs = panelServiceFixtures() ?? throw new RuntimeException('No temporary filesystem.');

    $fs->file('docs/overview.md', "---\ntitle: Architecture overview\nrelations:\n  - api/endpoints.md\n  - getting-started.md\n---\n# Overview\n\nSee <script>alert(1)</script>.\n");
    $fs->file('docs/getting-started.md', "# Getting started\n");
    $fs->file('docs/api/endpoints.md', "# Endpoints\n");

    $frontMatter = new FrontMatterParser;

    return new PanelService(
        new GraphBuilder(['docs'], $fs->root(), $frontMatter),
        new DocumentRenderer($frontMatter, new SafeMarkdownConverter),
        new NameSearch(new Tokenizer, new Vectorizer),
    );
}

/**
 * Return the node id of the fixture file.
 */
function panelServiceId(string $relative): string
{
    $fs = panelServiceFixtures() ?? throw new RuntimeException('No temporary filesystem.');

    return substr(sha1((string) realpath($fs->path($relative))), 0, 12);
}

afterEach(function (): void {
    panelServiceFixtures(cleanup: true);
});

it('has the query limit', function (): void {
    expect(PanelService::MAX_QUERY_LENGTH)->toBe(200);
});

it('returns the documentation graph', function (): void {
    $graph = panelService()->graph();

    expect(array_column($graph['nodes'], 'path'))->toBe(['docs/api/endpoints.md', 'docs/getting-started.md', 'docs/overview.md'])
        ->and($graph['relationships'])->toBe([
            ['source' => panelServiceId('docs/overview.md'), 'target' => panelServiceId('docs/api/endpoints.md'), 'bidirectional' => false],
            ['source' => panelServiceId('docs/overview.md'), 'target' => panelServiceId('docs/getting-started.md'), 'bidirectional' => false],
        ]);
});

it('returns the document with the html, the relations and the subgraph', function (): void {
    $service = panelService();
    $id = panelServiceId('docs/overview.md');

    $document = $service->document($id);

    expect($document)->not->toBeNull()
        ->and(array_keys($document ?? []))->toBe(['id', 'name', 'title', 'path', 'html', 'relations', 'subgraph'])
        ->and($document['id'] ?? null)->toBe($id)
        ->and($document['name'] ?? null)->toBe('overview')
        ->and($document['title'] ?? null)->toBe('Architecture overview')
        ->and($document['path'] ?? null)->toBe('docs/overview.md')
        ->and($document['html'] ?? '')->toContain('<h1>Overview</h1>')
        ->and($document['html'] ?? '')->not->toContain('<script>')
        ->and($document['relations'] ?? null)->toBe([
            ['id' => panelServiceId('docs/api/endpoints.md'), 'name' => 'endpoints', 'path' => 'docs/api/endpoints.md'],
            ['id' => panelServiceId('docs/getting-started.md'), 'name' => 'getting-started', 'path' => 'docs/getting-started.md'],
        ])
        ->and($document['subgraph'] ?? [])->toContain($id, panelServiceId('docs/api/endpoints.md'), panelServiceId('docs/getting-started.md'))
        ->and($document['subgraph'] ?? [])->toHaveCount(3);
});

it('returns no relations for a document without links', function (): void {
    $service = panelService();
    $id = panelServiceId('docs/getting-started.md');

    $document = $service->document($id);

    expect($document['relations'] ?? null)->toBe([])
        ->and($document['subgraph'] ?? [])->toContain($id, panelServiceId('docs/overview.md'));
});

it('returns null for an unknown id', function (string $id): void {
    expect(panelService()->document($id))->toBeNull();
})->with([
    'unknown hex id' => ['ffffffffffff'],
    'path' => ['docs/overview.md'],
    'empty' => [''],
]);

it('returns the search results with the query and the threshold', function (): void {
    $result = panelService()->search('endpoints');

    expect(array_keys($result))->toBe(['query', 'threshold', 'results'])
        ->and($result['query'])->toBe('endpoints')
        ->and($result['threshold'])->toBe(NameSearch::THRESHOLD)
        ->and($result['results'])->not->toBe([])
        ->and($result['results'][0]['id'])->toBe(panelServiceId('docs/api/endpoints.md'))
        ->and(array_keys($result['results'][0]))->toBe(['id', 'name', 'path', 'score']);
});

it('trims the query', function (): void {
    $result = panelService()->search("  endpoints \n");

    expect($result['query'])->toBe('endpoints')
        ->and($result['results'])->not->toBe([]);
});

it('cuts the query to the maximum length in characters', function (): void {
    $result = panelService()->search(str_repeat('é', 250));

    expect(mb_strlen($result['query']))->toBe(200)
        ->and($result['query'])->toBe(str_repeat('é', 200));
});

it('returns no results for a blank query or a value that is not a string', function (mixed $query): void {
    $result = panelService()->search($query);

    expect($result['query'])->toBe('')
        ->and($result['threshold'])->toBe(NameSearch::THRESHOLD)
        ->and($result['results'])->toBe([]);
})->with([
    'empty' => [''],
    'blank' => ["  \t\n"],
    'null' => [null],
    'list' => [['endpoints']],
    'integer' => [42],
]);
