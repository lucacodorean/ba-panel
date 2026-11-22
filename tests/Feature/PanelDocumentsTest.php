<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lucacodorean\BaPanel\Tests\Support\PanelConfig;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use Lucacodorean\BaPanel\Tests\TestCase;

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk and forget it when $cleanup is true.
 */
function panelDocumentsFixtures(bool $cleanup = false): ?TemporaryFilesystem
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
 * Create the documentation fixtures and boot the application with them.
 */
function bootPanelWithDocuments(TestCase $test): TemporaryFilesystem
{
    $fs = panelDocumentsFixtures() ?? throw new RuntimeException('No temporary filesystem.');

    $fs->file('docs/guide/overview.md', "---\ntitle: Architecture overview\nrelations:\n  - ../api/endpoints.md\n  - getting-started.md\n---\n# Overview heading\n\nSee the <script>alert(1)</script> API.\n");
    $fs->file('docs/guide/getting-started.md', "# Getting started\n\nStep one.\n");
    $fs->file('docs/api/endpoints.md', "# Endpoints\n\n| Method | Uri |\n| --- | --- |\n| GET | /ba-panel |\n");

    $test->withBaPanelConfig(PanelConfig::enabled($fs->path('docs')))->rebootApplication();

    return $fs;
}

/**
 * Return the node id of the fixture file.
 */
function panelDocumentId(string $relative): string
{
    $fs = panelDocumentsFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $path = realpath($fs->path($relative));

    if ($path === false) {
        throw new RuntimeException('Missing fixture: '.$relative);
    }

    return substr(sha1($path), 0, 12);
}

/**
 * Return the display path of the fixture file. The fixtures are outside the base path, so the path is absolute.
 */
function panelDocumentPath(string $relative): string
{
    $fs = panelDocumentsFixtures() ?? throw new RuntimeException('No temporary filesystem.');

    return str_replace('\\', '/', (string) realpath($fs->path($relative)));
}

afterEach(function (): void {
    panelDocumentsFixtures(cleanup: true);
});

it('registers the document and search routes when enabled', function (): void {
    /** @var TestCase $this */
    bootPanelWithDocuments($this);

    $documents = Route::getRoutes()->getByName('ba-panel.documents');
    $search = Route::getRoutes()->getByName('ba-panel.search');

    expect($documents?->uri())->toBe('ba-panel/documents/{node}')
        ->and($documents?->methods())->toContain('GET')
        ->and($documents?->gatherMiddleware())->toContain('web')
        ->and($search?->uri())->toBe('ba-panel/search')
        ->and($search?->methods())->toContain('GET')
        ->and($search?->gatherMiddleware())->toContain('web')
        ->and(route('ba-panel.documents', ['node' => 'abcdef012345'], false))->toBe('/ba-panel/documents/abcdef012345')
        ->and(route('ba-panel.search', absolute: false))->toBe('/ba-panel/search');
});

it('does not register the document and search routes when disabled', function (): void {
    /** @var TestCase $this */
    $this->withBaPanelConfig(PanelConfig::disabled())->rebootApplication();

    expect(Route::has('ba-panel.documents'))->toBeFalse()
        ->and(Route::has('ba-panel.search'))->toBeFalse();

    $this->get('/ba-panel/documents/abcdef012345')->assertNotFound();
    $this->get('/ba-panel/search?q=api')->assertNotFound();
});

it('passes the graph to the panel view', function (): void {
    /** @var TestCase $this */
    bootPanelWithDocuments($this);

    $overview = panelDocumentId('docs/guide/overview.md');

    $this->get('/ba-panel')
        ->assertOk()
        ->assertViewHas('graph', [
            'nodes' => [
                ['id' => panelDocumentId('docs/api/endpoints.md'), 'name' => 'endpoints', 'title' => 'Endpoints', 'path' => panelDocumentPath('docs/api/endpoints.md')],
                ['id' => panelDocumentId('docs/guide/getting-started.md'), 'name' => 'getting-started', 'title' => 'Getting started', 'path' => panelDocumentPath('docs/guide/getting-started.md')],
                ['id' => $overview, 'name' => 'overview', 'title' => 'Architecture overview', 'path' => panelDocumentPath('docs/guide/overview.md')],
            ],
            'relationships' => [
                ['source' => $overview, 'target' => panelDocumentId('docs/api/endpoints.md'), 'bidirectional' => false],
                ['source' => $overview, 'target' => panelDocumentId('docs/guide/getting-started.md'), 'bidirectional' => false],
            ],
        ]);
});

it('returns the rendered document with its relations', function (): void {
    /** @var TestCase $this */
    bootPanelWithDocuments($this);

    $id = panelDocumentId('docs/guide/overview.md');

    $response = $this->getJson('/ba-panel/documents/'.$id)
        ->assertOk()
        ->assertExactJsonStructure(['id', 'name', 'title', 'path', 'html', 'relations' => ['*' => ['id', 'name', 'path']], 'subgraph'])
        ->assertJsonPath('id', $id)
        ->assertJsonPath('name', 'overview')
        ->assertJsonPath('title', 'Architecture overview')
        ->assertJsonPath('path', panelDocumentPath('docs/guide/overview.md'))
        ->assertJsonPath('relations', [
            ['id' => panelDocumentId('docs/api/endpoints.md'), 'name' => 'endpoints', 'path' => panelDocumentPath('docs/api/endpoints.md')],
            ['id' => panelDocumentId('docs/guide/getting-started.md'), 'name' => 'getting-started', 'path' => panelDocumentPath('docs/guide/getting-started.md')],
        ])
        ->assertJsonPath('subgraph', [
            panelDocumentId('docs/api/endpoints.md'),
            panelDocumentId('docs/guide/getting-started.md'),
            $id,
        ]);

    $html = (string) $response->json('html');

    $subgraph = $response->json('subgraph');

    expect($subgraph)->toBeList()
        ->and(array_filter((array) $subgraph, 'is_string'))->toHaveCount(3);

    expect($html)->toContain('<h1>Overview heading</h1>')
        ->and($html)->not->toContain('Architecture overview')
        ->and($html)->not->toContain('relations:')
        ->and($html)->not->toContain('<script>')
        ->toContain('&lt;script&gt;');
});

it('does not list the document that declares the opened document as a relation', function (): void {
    /** @var TestCase $this */
    bootPanelWithDocuments($this);

    $id = panelDocumentId('docs/api/endpoints.md');

    $response = $this->getJson('/ba-panel/documents/'.$id)
        ->assertOk()
        ->assertExactJsonStructure(['id', 'name', 'title', 'path', 'html', 'relations', 'subgraph'])
        ->assertJsonPath('relations', [])
        ->assertJsonPath('subgraph', [$id, panelDocumentId('docs/guide/overview.md')]);

    expect((string) $response->json('html'))->toContain('<table>');
});

it('lists a mutually declared document once from both ends', function (): void {
    /** @var TestCase $this */
    $fs = panelDocumentsFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('docs/a.md', "---\nrelations:\n  - b.md\n  - b.md\n---\n# A\n");
    $fs->file('docs/b.md', "---\nrelations:\n  - a.md\n---\n# B\n");
    $this->withBaPanelConfig(PanelConfig::enabled($fs->path('docs')))->rebootApplication();

    $a = panelDocumentId('docs/a.md');
    $b = panelDocumentId('docs/b.md');

    $this->getJson('/ba-panel/documents/'.$a)
        ->assertOk()
        ->assertJsonPath('relations', [['id' => $b, 'name' => 'b', 'path' => panelDocumentPath('docs/b.md')]])
        ->assertJsonPath('subgraph', [$a, $b]);

    $this->getJson('/ba-panel/documents/'.$b)
        ->assertOk()
        ->assertJsonPath('relations', [['id' => $a, 'name' => 'a', 'path' => panelDocumentPath('docs/a.md')]])
        ->assertJsonPath('subgraph', [$a, $b]);
});

it('lists only the outgoing relations of a document that others declare', function (): void {
    /** @var TestCase $this */
    $fs = panelDocumentsFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('docs/api/errors.md', "---\nrelations:\n  - endpoints.md\n  - ../guide/overview.md\n---\n# Errors\n");
    bootPanelWithDocuments($this);

    $this->getJson('/ba-panel/documents/'.panelDocumentId('docs/guide/overview.md'))
        ->assertOk()
        ->assertJsonPath('relations', [
            ['id' => panelDocumentId('docs/api/endpoints.md'), 'name' => 'endpoints', 'path' => panelDocumentPath('docs/api/endpoints.md')],
            ['id' => panelDocumentId('docs/guide/getting-started.md'), 'name' => 'getting-started', 'path' => panelDocumentPath('docs/guide/getting-started.md')],
        ]);

    $this->getJson('/ba-panel/documents/'.panelDocumentId('docs/api/errors.md'))
        ->assertOk()
        ->assertJsonPath('relations', [
            ['id' => panelDocumentId('docs/api/endpoints.md'), 'name' => 'endpoints', 'path' => panelDocumentPath('docs/api/endpoints.md')],
            ['id' => panelDocumentId('docs/guide/overview.md'), 'name' => 'overview', 'path' => panelDocumentPath('docs/guide/overview.md')],
        ]);
});

it('returns the ids of the direct neighbours in both directions as the subgraph', function (): void {
    /** @var TestCase $this */
    $fs = panelDocumentsFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('docs/api/errors.md', "---\nrelations:\n  - ../guide/overview.md\n---\n# Errors\n");
    bootPanelWithDocuments($this);

    $endpoints = panelDocumentId('docs/api/endpoints.md');
    $errors = panelDocumentId('docs/api/errors.md');
    $overview = panelDocumentId('docs/guide/overview.md');

    $this->getJson('/ba-panel/documents/'.$errors)
        ->assertOk()
        ->assertJsonPath('relations', [
            ['id' => $overview, 'name' => 'overview', 'path' => panelDocumentPath('docs/guide/overview.md')],
        ])
        ->assertJsonPath('subgraph', [$errors, $overview]);

    $this->getJson('/ba-panel/documents/'.$endpoints)
        ->assertOk()
        ->assertJsonPath('relations', [])
        ->assertJsonPath('subgraph', [$endpoints, $overview]);
});

it('includes an incoming-only neighbour in the subgraph but not in the relations', function (): void {
    /** @var TestCase $this */
    $fs = panelDocumentsFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('docs/api/errors.md', "---\nrelations:\n  - ../guide/overview.md\n---\n# Errors\n");
    bootPanelWithDocuments($this);

    $endpoints = panelDocumentId('docs/api/endpoints.md');
    $errors = panelDocumentId('docs/api/errors.md');
    $gettingStarted = panelDocumentId('docs/guide/getting-started.md');
    $overview = panelDocumentId('docs/guide/overview.md');

    $response = $this->getJson('/ba-panel/documents/'.$overview)
        ->assertOk()
        ->assertJsonPath('relations', [
            ['id' => $endpoints, 'name' => 'endpoints', 'path' => panelDocumentPath('docs/api/endpoints.md')],
            ['id' => $gettingStarted, 'name' => 'getting-started', 'path' => panelDocumentPath('docs/guide/getting-started.md')],
        ])
        ->assertJsonPath('subgraph', [$endpoints, $errors, $gettingStarted, $overview]);

    expect(array_column((array) $response->json('relations'), 'id'))->not->toContain($errors);
});

it('does not add the neighbours of a neighbour to the subgraph', function (): void {
    /** @var TestCase $this */
    $fs = panelDocumentsFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('docs/a.md', "---\nrelations:\n  - b.md\n---\n# A\n");
    $fs->file('docs/b.md', "---\nrelations:\n  - c.md\n---\n# B\n");
    $fs->file('docs/c.md', "---\nrelations:\n  - d.md\n---\n# C\n");
    $fs->file('docs/d.md', '# D');
    $this->withBaPanelConfig(PanelConfig::enabled($fs->path('docs')))->rebootApplication();

    $this->getJson('/ba-panel/documents/'.panelDocumentId('docs/a.md'))
        ->assertOk()
        ->assertJsonPath('subgraph', [panelDocumentId('docs/a.md'), panelDocumentId('docs/b.md')]);

    $this->getJson('/ba-panel/documents/'.panelDocumentId('docs/c.md'))
        ->assertOk()
        ->assertJsonPath('relations', [['id' => panelDocumentId('docs/d.md'), 'name' => 'd', 'path' => panelDocumentPath('docs/d.md')]])
        ->assertJsonPath('subgraph', [panelDocumentId('docs/b.md'), panelDocumentId('docs/c.md'), panelDocumentId('docs/d.md')]);
});

it('lists each document of a cycle once in the subgraph', function (): void {
    /** @var TestCase $this */
    $fs = panelDocumentsFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('docs/a.md', "---\nrelations:\n  - b.md\n---\n# A\n");
    $fs->file('docs/b.md', "---\nrelations:\n  - c.md\n---\n# B\n");
    $fs->file('docs/c.md', "---\nrelations:\n  - a.md\n---\n# C\n");
    $fs->file('docs/d.md', '# D');
    $this->withBaPanelConfig(PanelConfig::enabled($fs->path('docs')))->rebootApplication();

    $this->getJson('/ba-panel/documents/'.panelDocumentId('docs/b.md'))
        ->assertOk()
        ->assertJsonPath('subgraph', [
            panelDocumentId('docs/a.md'),
            panelDocumentId('docs/b.md'),
            panelDocumentId('docs/c.md'),
        ]);
});

it('returns an empty relation list for a document without relations', function (): void {
    /** @var TestCase $this */
    $fs = panelDocumentsFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('docs/isolated.md', "# Isolated\n");
    bootPanelWithDocuments($this);

    $this->getJson('/ba-panel/documents/'.panelDocumentId('docs/isolated.md'))
        ->assertOk()
        ->assertJsonPath('relations', [])
        ->assertJsonPath('subgraph', [panelDocumentId('docs/isolated.md')]);
});

it('returns 404 for an unknown document id', function (): void {
    /** @var TestCase $this */
    bootPanelWithDocuments($this);

    $this->getJson('/ba-panel/documents/000000000000')->assertNotFound();
});

it('returns 404 for a document id that is not 12 lowercase hex characters', function (string $node): void {
    /** @var TestCase $this */
    bootPanelWithDocuments($this);

    $this->get('/ba-panel/documents/'.$node)->assertNotFound();
})->with([
    'too short' => ['abcdef01234'],
    'too long' => ['abcdef0123456'],
    'not hex' => ['zzzzzzzzzzzz'],
    'upper case' => ['ABCDEF012345'],
    'path traversal' => ['..%2F..%2Fetc%2Fpasswd'],
    'file name' => ['overview.md'],
]);

it('returns 404 for a document id in upper case of a known id', function (): void {
    /** @var TestCase $this */
    bootPanelWithDocuments($this);

    $this->get('/ba-panel/documents/'.strtoupper(panelDocumentId('docs/guide/overview.md')))->assertNotFound();
});

it('returns the matching files for a search query', function (): void {
    /** @var TestCase $this */
    bootPanelWithDocuments($this);

    $this->getJson('/ba-panel/search?q=endp')
        ->assertOk()
        ->assertExactJson([
            'query' => 'endp',
            'threshold' => 0.8,
            'results' => [
                ['id' => panelDocumentId('docs/api/endpoints.md'), 'name' => 'endpoints', 'path' => panelDocumentPath('docs/api/endpoints.md'), 'score' => 1],
            ],
        ]);
});

it('returns no results when no file matches', function (): void {
    /** @var TestCase $this */
    bootPanelWithDocuments($this);

    $this->getJson('/ba-panel/search?q=zzzz')
        ->assertOk()
        ->assertExactJson(['query' => 'zzzz', 'threshold' => 0.8, 'results' => []]);
});

it('returns no results for a blank or missing query', function (string $uri): void {
    /** @var TestCase $this */
    bootPanelWithDocuments($this);

    $this->getJson($uri)
        ->assertOk()
        ->assertExactJson(['query' => '', 'threshold' => 0.8, 'results' => []]);
})->with([
    'missing' => ['/ba-panel/search'],
    'empty' => ['/ba-panel/search?q='],
    'blank' => ['/ba-panel/search?q=%20%20%20'],
    'array' => ['/ba-panel/search?q[]=endpoints'],
]);

it('trims the search query', function (): void {
    /** @var TestCase $this */
    bootPanelWithDocuments($this);

    $this->getJson('/ba-panel/search?q='.rawurlencode('  getting  '))
        ->assertOk()
        ->assertJsonPath('query', 'getting')
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.name', 'getting-started');
});

it('cuts the search query to 200 characters', function (): void {
    /** @var TestCase $this */
    bootPanelWithDocuments($this);

    $this->getJson('/ba-panel/search?q='.str_repeat('a', 250))
        ->assertOk()
        ->assertJsonPath('query', str_repeat('a', 200));
});
