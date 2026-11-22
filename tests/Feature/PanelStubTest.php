<?php

declare(strict_types=1);

use Illuminate\Support\Js;
use Lucacodorean\BaPanel\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Tests\Support\PanelConfig;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use Lucacodorean\BaPanel\Tests\TestCase;

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk and forget it when $cleanup is true.
 */
function panelStubFixtures(bool $cleanup = false): ?TemporaryFilesystem
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
 * Create three documents with two links and boot the application. Return the page content.
 */
function panelStubWithDocuments(TestCase $test): string
{
    $fs = panelStubFixtures() ?? throw new RuntimeException('No temporary filesystem.');

    $fs->file('docs/overview.md', "---\nrelations:\n  - api/endpoints.md\n  - getting-started.md\n---\n# Overview\n");
    $fs->file('docs/getting-started.md', '# Getting started');
    $fs->file('docs/api/endpoints.md', '# Endpoints');

    $test->withBaPanelConfig(PanelConfig::enabled($fs->path('docs')))->rebootApplication();

    return (string) $test->get('/ba-panel')->assertOk()->getContent();
}

/**
 * Boot the application with an empty docs directory. Return the page content.
 */
function panelStubWithoutDocuments(TestCase $test): string
{
    $fs = panelStubFixtures() ?? throw new RuntimeException('No temporary filesystem.');

    $test->withBaPanelConfig(PanelConfig::enabled($fs->directory('docs')))->rebootApplication();

    return (string) $test->get('/ba-panel')->assertOk()->getContent();
}

/**
 * Return the first opening tag that matches the pattern. Return an empty string when no tag matches.
 */
function panelStubTag(string $content, string $pattern): string
{
    return preg_match($pattern, $content, $matches) === 1 ? $matches[0] : '';
}

/**
 * Return the content of the first style element without comments. Return an empty string when no style element exists.
 */
function panelStubStyle(string $content): string
{
    if (preg_match('/<style\b[^>]*>(.*?)<\/style>/s', $content, $matches) !== 1) {
        return '';
    }

    return (string) preg_replace('/\/\*.*?\*\//s', '', $matches[1]);
}

/**
 * Return the body of the first media block that matches the query. Return an empty string when no block matches.
 * The query is a regex fragment, for example "min-width:\s*1041px".
 */
function panelStubMedia(string $css, string $query): string
{
    if (preg_match('/@media\s*\(\s*'.$query.'\s*\)\s*\{/', $css, $matches, PREG_OFFSET_CAPTURE) !== 1) {
        return '';
    }

    $start = $matches[0][1] + strlen($matches[0][0]);
    $depth = 1;

    for ($index = $start, $length = strlen($css); $index < $length; $index++) {
        $depth += match ($css[$index]) {
            '{' => 1,
            '}' => -1,
            default => 0,
        };

        if ($depth === 0) {
            return substr($css, $start, $index - $start);
        }
    }

    return '';
}

/**
 * Remove all media blocks from the style sheet. Return the top-level rules only.
 */
function panelStubTopLevel(string $css): string
{
    while (preg_match('/@media[^{]*\{/', $css, $matches, PREG_OFFSET_CAPTURE) === 1) {
        $start = $matches[0][1];
        $depth = 0;
        $end = strlen($css);

        for ($index = $start + strlen($matches[0][0]) - 1, $length = strlen($css); $index < $length; $index++) {
            $depth += match ($css[$index]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };

            if ($depth === 0) {
                $end = $index + 1;
                break;
            }
        }

        $css = substr($css, 0, $start).substr($css, $end);
    }

    return $css;
}

/**
 * Return the declarations of all rules whose selector list contains the selector.
 * Each declaration is normalized to "property: value".
 *
 * @return list<string>
 */
function panelStubDeclarations(string $css, string $selector): array
{
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

    $declarations = [];

    foreach ($rules as $rule) {
        $selectors = array_map(
            static fn (string $item): string => (string) preg_replace('/\s+/', ' ', trim($item)),
            explode(',', $rule[1]),
        );

        if (! in_array($selector, $selectors, true)) {
            continue;
        }

        foreach (explode(';', $rule[2]) as $declaration) {
            $parts = explode(':', $declaration, 2);

            if (count($parts) === 2) {
                $declarations[] = strtolower(trim($parts[0])).': '.preg_replace('/\s+/', ' ', trim($parts[1]));
            }
        }
    }

    return $declarations;
}

afterEach(function (): void {
    panelStubFixtures(cleanup: true);
});

it('shows the real file and link counts', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);

    expect($content)->toContain('<span data-file-count>3</span> files · <span data-link-count>2</span> links')
        ->and($content)->not->toContain('<span>10</span> files');
});

it('counts a mutually declared pair as one link', function (): void {
    /** @var TestCase $this */
    $fs = panelStubFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('docs/a.md', "---\nrelations:\n  - b.md\n  - c.md\n---\n# A\n");
    $fs->file('docs/b.md', "---\nrelations:\n  - a.md\n---\n# B\n");
    $fs->file('docs/c.md', '# C');
    $this->withBaPanelConfig(PanelConfig::enabled($fs->path('docs')))->rebootApplication();

    $content = (string) $this->get('/ba-panel')->assertOk()->getContent();
    $graph = app(GraphBuilder::class)->build()->toArray();

    expect($content)->toContain('<span data-file-count>3</span> files · <span data-link-count>2</span> links')
        ->and($graph['relationships'])->toHaveCount(2)
        ->and($content)->toContain('var data = '.Js::from($graph).';');
});

it('embeds the bidirectional flag of each relationship', function (): void {
    /** @var TestCase $this */
    $fs = panelStubFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('docs/a.md', "---\nrelations:\n  - b.md\n  - c.md\n---\n# A\n");
    $fs->file('docs/b.md', "---\nrelations:\n  - a.md\n---\n# B\n");
    $fs->file('docs/c.md', '# C');
    $this->withBaPanelConfig(PanelConfig::enabled($fs->path('docs')))->rebootApplication();

    $content = (string) $this->get('/ba-panel')->assertOk()->getContent();
    $graph = app(GraphBuilder::class)->build()->toArray();
    $flags = array_column($graph['relationships'], 'bidirectional');

    expect($flags)->toBe([true, false])
        ->and($content)->toContain('var data = '.Js::from($graph).';');
});

it('shows zero counts for an empty graph', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithoutDocuments($this);

    expect($content)->toContain('<span data-file-count>0</span> files · <span data-link-count>0</span> links');
});

it('embeds the graph data with Js::from', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);

    $graph = app(GraphBuilder::class)->build()->toArray();

    expect($graph['nodes'])->toHaveCount(3)
        ->and($content)->toContain('var data = '.Js::from($graph).';');
});

it('embeds an empty graph when there are no documents', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithoutDocuments($this);

    expect($content)->toContain('var data = '.Js::from(['nodes' => [], 'relationships' => []]).';');
});

it('embeds the document and search endpoint urls', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);

    expect($content)
        ->toContain('var documentUrlTemplate = '.Js::from(route('ba-panel.documents', ['node' => '000000000000'], false)).';')
        ->toContain('var searchUrl = '.Js::from(route('ba-panel.search', [], false)).';')
        ->toContain("var documentUrlTemplate = '\\/ba-panel\\/documents\\/000000000000';")
        ->toContain("var searchUrl = '\\/ba-panel\\/search';");
});

it('does not embed absolute urls for the document and search endpoints', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);

    expect($content)->not->toContain((string) Js::from(route('ba-panel.documents', ['node' => '000000000000'])))
        ->and($content)->not->toContain((string) Js::from(route('ba-panel.search')))
        ->and(preg_match('#https?:(\\\\?/){2}[^"\'\s]*ba-panel(\\\\?/)(documents|search)#i', $content))->toBe(0);
});

it('keeps the server graph container empty', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);

    expect(preg_match('/<div class="graph"[^>]*>\s*<\/div>/', $content))->toBe(1);
});

it('places a hidden show all files button next to the graph', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);
    $button = panelStubTag($content, '/<button[^>]*data-graph-show-all[^>]*>/');

    expect($button)->not->toBe('')
        ->and($button)->toContain('type="button"')
        ->and(preg_match('/\shidden[\s>]/', $button))->toBe(1)
        ->and(preg_match('/<div class="graph"[^>]*>\s*<\/div>\s*<button[^>]*data-graph-show-all[^>]*>Show all files<\/button>/', $content))->toBe(1);
});

it('shows the show all files button once', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);

    expect(substr_count($content, 'data-graph-show-all hidden'))->toBe(1)
        ->and(substr_count($content, '>Show all files</button>'))->toBe(1);
});

it('defines arrow markers for the edges in the script', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);
    $script = panelStubTag($content, '/<script\b[^>]*>(?:(?!<\/script>).)*?var data = .*?<\/script>/s');

    expect($script)->not->toBe('')
        ->toContain("'ba-panel-arrow'")
        ->toContain("'ba-panel-arrow-active'")
        ->toContain("'auto-start-reverse'")
        ->toContain('marker-end')
        ->toContain('marker-start');
});

it('removes the static readme placeholder', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);

    expect($content)->not->toContain('README.md')
        ->and($content)->not->toContain('Project documentation')
        ->and($content)->not->toContain('Entry point for the knowledge base')
        ->and($content)->not->toContain('architecture/overview.md')
        ->and($content)->not->toContain('agents/feasibility-agent.md');
});

it('shows the reader empty state before the first selection', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);

    expect($content)->toContain('<p class="reader-empty">Select a node in the graph to read the document.</p>')
        ->and($content)->toContain('data-reader-path>No file open</span>');
});

it('hides the context chip before a document is open', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);
    $chip = panelStubTag($content, '/<span[^>]*class="context-chip"[^>]*>/');

    expect($chip)->not->toBe('')
        ->and(preg_match('/\shidden[\s>]/', $chip))->toBe(1)
        ->and($content)->toContain('<span data-context-name></span>');
});

it('connects the search input to the hidden results list', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);
    $input = panelStubTag($content, '/<input[^>]*id="docs-filter"[^>]*>/');
    $results = panelStubTag($content, '/<[a-z]+[^>]*id="docs-filter-results"[^>]*>/');

    expect($input)->toContain('aria-controls="docs-filter-results"')
        ->and($input)->toContain('aria-expanded="false"')
        ->and($results)->not->toBe('')
        ->and(preg_match('/\shidden[\s>]/', $results))->toBe(1);
});

it('shows a text when no file matches the filter', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);

    expect($content)->toContain('No matching files');
});

it('does not load an external script or stylesheet', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);

    expect(preg_match('/<script\b[^>]*\ssrc=/i', $content))->toBe(0)
        ->and(preg_match('/<link\b[^>]*\shref=/i', $content))->toBe(0)
        ->and(preg_match('/\bcdn\b|unpkg|jsdelivr|cdnjs/i', $content))->toBe(0)
        ->and(preg_match('/https:\/\//i', $content))->toBe(0);
});

it('supports the reduced motion preference', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);

    expect($content)->toContain('(prefers-reduced-motion: reduce)');
});

it('does not expose the absolute path through the graph data keys', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);

    expect($content)->not->toContain('absolutePath');
});

it('scrolls the reader inside a bounded height', function (): void {
    /** @var TestCase $this */
    $css = panelStubTopLevel(panelStubStyle(panelStubWithDocuments($this)));
    $reader = panelStubDeclarations($css, '.reader');

    expect($reader)->toContain('overflow-y: auto')
        ->toContain('contain: size')
        ->and(in_array('max-height: 100%', $reader, true))->toBeFalse();
});

it('defines the scrollbar color for the light and the dark theme', function (): void {
    /** @var TestCase $this */
    $css = panelStubTopLevel(panelStubStyle(panelStubWithDocuments($this)));
    $light = array_filter(panelStubDeclarations($css, ':root'), static fn (string $item): bool => str_starts_with($item, '--color-scrollbar: '));
    $dark = array_filter(panelStubDeclarations($css, ':root[data-theme="dark"]'), static fn (string $item): bool => str_starts_with($item, '--color-scrollbar: '));

    expect($light)->toHaveCount(1)
        ->and($dark)->toHaveCount(1);
});

it('keeps the reader bar sticky at the top', function (): void {
    /** @var TestCase $this */
    $css = panelStubTopLevel(panelStubStyle(panelStubWithDocuments($this)));

    expect(panelStubDeclarations($css, '.reader-bar'))->toContain('position: sticky')
        ->toContain('top: 0');
});

it('keeps one flex line for the docs body on wide screens', function (): void {
    /** @var TestCase $this */
    $media = panelStubMedia(panelStubStyle(panelStubWithDocuments($this)), 'min-width:\s*1041px');

    expect($media)->not->toBe('')
        ->and(panelStubDeclarations($media, '.docs-body'))->toContain('flex-wrap: nowrap');
});

it('gives the reader a minimum height on narrow screens', function (): void {
    /** @var TestCase $this */
    $media = panelStubMedia(panelStubStyle(panelStubWithDocuments($this)), 'max-width:\s*1040px');
    $minHeight = array_filter(panelStubDeclarations($media, '.reader'), static fn (string $item): bool => str_starts_with($item, 'min-height: '));

    expect($media)->not->toBe('')
        ->and($minHeight)->toHaveCount(1);
});

it('scrolls code blocks and tables horizontally inside the block', function (): void {
    /** @var TestCase $this */
    $css = panelStubTopLevel(panelStubStyle(panelStubWithDocuments($this)));

    expect(panelStubDeclarations($css, '.markdown pre'))->toContain('overflow-x: auto')
        ->and(panelStubDeclarations($css, '.markdown table'))->toContain('overflow-x: auto');
});

it('resets the reader scroll position when a document opens', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);
    $render = panelStubTag($content, '/function\s+renderDocument\s*\(.*?(?=\n\s*function\s)/s');

    expect(preg_match_all('/\breader\.scrollTop\s*=\s*0\s*;/', $content))->toBeGreaterThanOrEqual(1)
        ->and($render)->not->toBe('')
        ->and(preg_match('/\breader\.scrollTop\s*=\s*0\s*;/', $render))->toBe(1);
});

it('keeps the reader markup', function (): void {
    /** @var TestCase $this */
    $content = panelStubWithDocuments($this);
    $article = panelStubTag($content, '/<article\b[^>]*\bdata-reader\b[^>]*>.*?<\/article>/s');
    $open = panelStubTag($article, '/<article\b[^>]*>/');

    expect($article)->not->toBe('')
        ->and(preg_match('/\sclass="reader"/', $open))->toBe(1)
        ->and(preg_match('/<div\b[^>]*\sclass="reader-bar"/', $article))->toBe(1)
        ->and(preg_match('/<div\b[^>]*\sdata-reader-content\b/', $article))->toBe(1);
});
