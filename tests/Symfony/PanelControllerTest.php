<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Search\NameSearch;
use Lucacodorean\BaPanel\Tests\Support\Symfony\SymfonyPanel;
use Symfony\Component\HttpFoundation\Request;

/*
| These tests send HTTP requests to the panel routes of the test kernel.
*/

/**
 * Write three documents with two links. The docs are under the project directory.
 */
function symfonyPanelDocuments(): void
{
    SymfonyPanel::file('docs/overview.md', "---\ntitle: Architecture overview\nrelations:\n  - api/endpoints.md\n  - getting-started.md\n---\n# Overview heading\n\nSee the <script>alert(1)</script> API.\n");
    SymfonyPanel::file('docs/getting-started.md', "# Getting started\n");
    SymfonyPanel::file('docs/api/endpoints.md', "# Endpoints\n");
}

/**
 * Boot an enabled panel with the documents.
 */
function symfonyPanel(bool $csrf = false): SymfonyPanel
{
    symfonyPanelDocuments();

    return SymfonyPanel::create(SymfonyPanel::enabledConfig(), $csrf)->boot();
}

/**
 * Return the panel page content.
 */
function symfonyPanelPage(SymfonyPanel $panel): string
{
    $response = $panel->get('/ba-panel');

    expect($response->getStatusCode())->toBe(200);

    return (string) $response->getContent();
}

it('returns the panel page as HTML', function (): void {
    $response = symfonyPanel()->get('/ba-panel');
    $content = (string) $response->getContent();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('text/html; charset=UTF-8')
        ->and($content)->toStartWith('<!DOCTYPE html>')
        ->and($content)->toContain('Workspace')
        ->and($content)->toContain('Feasibility agent')
        ->and($content)->not->toContain('<?')
        ->and($content)->not->toContain('{{');
});

it('embeds the graph, the urls and the limits in the panel page', function (): void {
    $panel = symfonyPanel();
    $content = symfonyPanelPage($panel);

    expect($content)->toContain('var documentUrlTemplate = "\/ba-panel\/documents\/000000000000";')
        ->and($content)->toContain('var searchUrl = "\/ba-panel\/search";')
        ->and($content)->toContain('var feasibilityUrl = "\/ba-panel\/feasibility";')
        ->and($content)->toContain('var MAX_CONTEXT_FILES = 50;')
        ->and($content)->toContain('maxlength="5000"')
        ->and($content)->toContain('<span data-file-count>3</span> files · <span data-link-count>2</span> links')
        ->and(preg_match('/var data = (.*?);\n/', $content, $matches))->toBe(1);

    $graph = json_decode($matches[1], true);

    expect(array_column($graph['nodes'] ?? [], 'path'))->toBe(['docs/api/endpoints.md', 'docs/getting-started.md', 'docs/overview.md'])
        ->and($graph['relationships'] ?? [])->toHaveCount(2);
});

it('does not render the CSRF meta tag without a CSRF token manager', function (): void {
    $content = symfonyPanelPage(symfonyPanel());

    expect($content)->not->toContain('<meta name="csrf-token"')
        ->and($content)->toContain('var csrfHeader = "X-CSRF-Token";');
});

it('renders the CSRF token of the ba-panel id with a CSRF token manager', function (): void {
    $panel = symfonyPanel(csrf: true);
    $content = symfonyPanelPage($panel);

    expect(preg_match('/<meta name="csrf-token" content="([^"]+)">/', $content, $matches))->toBe(1)
        ->and($content)->toContain('var csrfHeader = "X-CSRF-Token";');
});

it('sets the lang attribute from the request locale', function (): void {
    $panel = symfonyPanel();
    $request = Request::create('/ba-panel');
    $request->setLocale('pt_BR');

    expect((string) $panel->handle($request)->getContent())->toContain('<html lang="pt-BR">');
});

it('uses a host template that overrides the panel template', function (): void {
    $panel = symfonyPanel();
    SymfonyPanel::file('templates/bundles/BaPanelBundle/panel.php', '<p>Host <?= $e($csrfHeader) ?> <?= $js($searchUrl) ?></p>');

    expect((string) $panel->get('/ba-panel')->getContent())->toBe('<p>Host X-CSRF-Token "\/ba-panel\/search"</p>');
});

it('returns the rendered document with its relations', function (): void {
    $panel = symfonyPanel();
    $id = SymfonyPanel::nodeId('docs/overview.md');

    $response = $panel->get('/ba-panel/documents/'.$id);
    $json = SymfonyPanel::json($response);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('application/json')
        ->and(array_keys($json))->toBe(['id', 'name', 'title', 'path', 'html', 'relations', 'subgraph'])
        ->and($json['id'])->toBe($id)
        ->and($json['title'])->toBe('Architecture overview')
        ->and($json['path'])->toBe('docs/overview.md')
        ->and($json['html'])->toContain('<h1>Overview heading</h1>')
        ->and($json['html'])->not->toContain('<script>')
        ->and($json['relations'])->toBe([
            ['id' => SymfonyPanel::nodeId('docs/api/endpoints.md'), 'name' => 'endpoints', 'path' => 'docs/api/endpoints.md'],
            ['id' => SymfonyPanel::nodeId('docs/getting-started.md'), 'name' => 'getting-started', 'path' => 'docs/getting-started.md'],
        ])
        ->and($json['subgraph'])->toHaveCount(3);
});

it('returns 404 for an unknown document id', function (): void {
    expect(symfonyPanel()->get('/ba-panel/documents/ffffffffffff')->getStatusCode())->toBe(404);
});

it('returns 404 for a node value that does not match the requirement', function (string $node): void {
    expect(symfonyPanel()->get('/ba-panel/documents/'.$node)->getStatusCode())->toBe(404);
})->with([
    'upper case' => ['ABCDEF012345'],
    'short' => ['abcdef01234'],
    'long' => ['abcdef0123456'],
    'non hex' => ['gggggggggggg'],
    'path' => ['..%2F..%2Fetc%2Fpasswd'],
]);

it('refuses a POST request to the panel and the documents', function (): void {
    $panel = symfonyPanel();

    expect($panel->postJson('/ba-panel', '{}')->getStatusCode())->toBe(405)
        ->and($panel->postJson('/ba-panel/search', '{}')->getStatusCode())->toBe(405);
});

it('refuses a GET request to the feasibility route', function (): void {
    expect(symfonyPanel()->get('/ba-panel/feasibility')->getStatusCode())->toBe(405);
});

it('returns the search results as JSON', function (): void {
    $panel = symfonyPanel();

    $response = $panel->get('/ba-panel/search', ['q' => '  endpoints ']);
    $json = SymfonyPanel::json($response);

    expect($response->getStatusCode())->toBe(200)
        ->and(array_keys($json))->toBe(['query', 'threshold', 'results'])
        ->and($json['query'])->toBe('endpoints')
        ->and($json['threshold'])->toBe(NameSearch::THRESHOLD)
        ->and($json['results'][0]['id'] ?? null)->toBe(SymfonyPanel::nodeId('docs/api/endpoints.md'));
});

it('returns no search results for a blank or missing query', function (array $query): void {
    $json = SymfonyPanel::json(symfonyPanel()->get('/ba-panel/search', $query));

    expect($json['query'])->toBe('')
        ->and($json['results'])->toBe([]);
})->with([
    'missing' => [[]],
    'empty' => [['q' => '']],
    'blank' => [['q' => '   ']],
]);

it('returns no search results and no error for a list value', function (): void {
    $response = symfonyPanel()->handle(Request::create('/ba-panel/search?q[]=endpoints'));
    $json = SymfonyPanel::json($response);

    expect($response->getStatusCode())->toBe(200)
        ->and($json['query'])->toBe('')
        ->and($json['results'])->toBe([]);
});

it('cuts a long search query to 200 characters', function (): void {
    $json = SymfonyPanel::json(symfonyPanel()->get('/ba-panel/search', ['q' => str_repeat('é', 250)]));

    expect($json['query'])->toBe(str_repeat('é', 200));
});
