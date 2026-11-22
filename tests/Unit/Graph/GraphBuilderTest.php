<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Graph\Graph;
use Lucacodorean\BaPanel\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Graph\Node;
use Lucacodorean\BaPanel\Graph\Relationship;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk and forget it when $cleanup is true.
 */
function graphBuilderFixtures(bool $cleanup = false): ?TemporaryFilesystem
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
 * Return the temporary filesystem for the current test.
 */
function graphFs(): TemporaryFilesystem
{
    return graphBuilderFixtures() ?? throw new RuntimeException('No temporary filesystem.');
}

/**
 * Build the graph for the paths. The base path is the fixtures root.
 *
 * @param  list<string>  $paths
 */
function buildGraph(array $paths, ?string $basePath = null): Graph
{
    return (new GraphBuilder($paths, $basePath ?? graphFs()->root()))->build();
}

/**
 * Return the display paths of the graph nodes, in graph order.
 *
 * @return list<string>
 */
function graphPaths(Graph $graph): array
{
    return array_map(static fn (Node $node): string => $node->path, $graph->nodes());
}

/**
 * Return the relationships as "source path -> target path" strings.
 *
 * @return list<string>
 */
function graphLinks(Graph $graph): array
{
    $links = [];

    foreach ($graph->relationships() as $relationship) {
        $links[] = $relationship->source()->path.' -> '.$relationship->target()->path;
    }

    return $links;
}

/**
 * Return the node with the display path. Fail the test when no node has the path.
 */
function graphNodeAt(Graph $graph, string $path): Node
{
    foreach ($graph->nodes() as $node) {
        if ($node->path === $path) {
            return $node;
        }
    }

    throw new RuntimeException('No node with the path: '.$path);
}

/**
 * Return the real path of a fixture file.
 */
function graphFixtureRealPath(string $relative): string
{
    $path = realpath(graphFs()->path($relative));

    if ($path === false) {
        throw new RuntimeException('Missing fixture: '.$relative);
    }

    return $path;
}

afterEach(function (): void {
    graphBuilderFixtures(cleanup: true);
});

it('creates one node per markdown file', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', '# A');
    $fs->file('docs/b.md', '# B');

    expect(graphPaths(buildGraph(['docs'])))->toBe(['docs/a.md', 'docs/b.md']);
});

it('matches the markdown extension without case', function (): void {
    $fs = graphFs();
    $fs->file('docs/upper.MD', '# Upper');
    $fs->file('docs/mixed.Md', '# Mixed');

    expect(graphPaths(buildGraph(['docs'])))->toBe(['docs/mixed.Md', 'docs/upper.MD']);
});

it('ignores files that are not markdown', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', '# A');
    $fs->file('docs/notes.txt', 'text');
    $fs->file('docs/markdown', 'no extension');
    $fs->file('docs/a.md.bak', 'backup');

    expect(graphPaths(buildGraph(['docs'])))->toBe(['docs/a.md']);
});

it('scans directories recursively', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', '# A');
    $fs->file('docs/api/endpoints.md', '# Endpoints');
    $fs->file('docs/api/v1/legacy.md', '# Legacy');

    expect(graphPaths(buildGraph(['docs'])))->toBe(['docs/a.md', 'docs/api/endpoints.md', 'docs/api/v1/legacy.md']);
});

it('skips hidden files and hidden directories inside a scanned directory', function (): void {
    $fs = graphFs();
    $fs->file('docs/visible.md', '# Visible');
    $fs->file('docs/.hidden.md', '# Hidden');
    $fs->file('docs/.git/inside.md', '# Inside');
    $fs->file('docs/sub/.draft/inside.md', '# Draft');

    expect(graphPaths(buildGraph(['docs'])))->toBe(['docs/visible.md']);
});

it('scans a hidden path when it is configured directly', function (): void {
    $fs = graphFs();
    $fs->file('.docs/a.md', '# A');
    $fs->file('.hidden.md', '# Hidden');

    expect(graphPaths(buildGraph(['.docs', '.hidden.md'])))->toBe(['.docs/a.md', '.hidden.md']);
});

it('creates one node for a file that overlapping paths find twice', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', '# A');
    $fs->file('docs/sub/b.md', '# B');

    $graph = buildGraph(['docs', 'docs/sub', 'docs/sub/b.md', $fs->path('docs/a.md'), 'docs/../docs/a.md']);

    expect(graphPaths($graph))->toBe(['docs/a.md', 'docs/sub/b.md']);
});

it('skips a missing configured path without an exception', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', '# A');

    expect(graphPaths(buildGraph(['missing', 'docs', 'missing.md'])))->toBe(['docs/a.md']);
});

it('ignores a configured file that is not markdown', function (): void {
    $fs = graphFs();
    $fs->file('notes.txt', 'text');

    expect(buildGraph(['notes.txt'])->nodes())->toBe([]);
});

it('returns an empty graph when no path is configured', function (): void {
    $graph = buildGraph([]);

    expect($graph->nodes())->toBe([])
        ->and($graph->relationships())->toBe([]);
});

it('stops at a symbolic link loop', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', '# A');
    symlink($fs->path('docs'), $fs->path('docs/loop'));

    expect(graphPaths(buildGraph(['docs'])))->toBe(['docs/a.md']);
});

it('uses the first 12 hex characters of the sha1 of the real path as the id', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', '# A');

    $node = buildGraph(['docs'])->nodes()[0];

    expect($node->id)->toBe(substr(sha1(graphFixtureRealPath('docs/a.md')), 0, 12))
        ->and($node->id)->toMatch('/^[a-f0-9]{12}$/');
});

it('sets the name to the file name without the extension', function (): void {
    $fs = graphFs();
    $fs->file('docs/data-model.md', '# Data');

    expect(buildGraph(['docs'])->nodes()[0]->name)->toBe('data-model');
});

it('sets the absolute path to the real path', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', '# A');

    expect(buildGraph(['docs'])->nodes()[0]->absolutePath)->toBe(graphFixtureRealPath('docs/a.md'));
});

it('sets the display path relative to the base path with forward slashes', function (): void {
    $fs = graphFs();
    $fs->file('docs/api/endpoints.md', '# Endpoints');

    expect(graphPaths(buildGraph(['docs'])))->toBe(['docs/api/endpoints.md']);
});

it('accepts a base path with a trailing separator', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', '# A');

    expect(graphPaths(buildGraph(['docs'], $fs->root().DIRECTORY_SEPARATOR)))->toBe(['docs/a.md']);
});

it('keeps the absolute path when the file is outside the base path', function (): void {
    $fs = graphFs();
    $fs->file('base/placeholder.txt', '');
    $fs->file('outside/a.md', '# A');

    $graph = buildGraph([$fs->path('outside')], $fs->path('base'));

    expect(graphPaths($graph))->toBe([str_replace('\\', '/', graphFixtureRealPath('outside/a.md'))]);
});

it('does not treat a sibling directory with the same prefix as inside the base path', function (): void {
    $fs = graphFs();
    $fs->file('base/placeholder.txt', '');
    $fs->file('base-other/a.md', '# A');

    $graph = buildGraph([$fs->path('base-other')], $fs->path('base'));

    expect(graphPaths($graph))->toBe([graphFixtureRealPath('base-other/a.md')]);
});

it('resolves relative configured paths against the base path', function (): void {
    $fs = graphFs();
    $fs->file('project/docs/a.md', '# A');

    expect(graphPaths(buildGraph(['docs'], $fs->path('project'))))->toBe(['docs/a.md']);
});

it('accepts absolute configured paths', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', '# A');

    expect(graphPaths(buildGraph([$fs->path('docs')])))->toBe(['docs/a.md']);
});

it('uses the front matter title first', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\ntitle: Front matter title\n---\n# Heading title\n");

    expect(buildGraph(['docs'])->nodes()[0]->title)->toBe('Front matter title');
});

it('uses the first level-one heading when the front matter has no title', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\nrelations: []\n---\nIntro text.\n\n## Second level\n\n# Heading title\n\n# Later heading\n");

    expect(buildGraph(['docs'])->nodes()[0]->title)->toBe('Heading title');
});

it('uses the first level-one heading when the front matter title is blank', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\ntitle: '  '\n---\n# Heading title\n");

    expect(buildGraph(['docs'])->nodes()[0]->title)->toBe('Heading title');
});

it('uses the file name when there is no title and no level-one heading', function (): void {
    $fs = graphFs();
    $fs->file('docs/getting-started.md', "## Only a second level heading\n\nText.\n");

    expect(buildGraph(['docs'])->nodes()[0]->title)->toBe('getting-started');
});

it('uses the file name for an empty file', function (): void {
    $fs = graphFs();
    $fs->file('docs/empty.md', '');

    expect(buildGraph(['docs'])->nodes()[0]->title)->toBe('empty');
});

it('ignores invalid front matter without an exception', function (): void {
    $fs = graphFs();
    $fs->file('docs/b.md', '# B');
    $fs->file('docs/a.md', "---\ntitle: [unclosed\nrelations:\n  - b.md\n---\nText.\n");

    $graph = buildGraph(['docs']);

    expect(graphNodeAt($graph, 'docs/a.md')->title)->toBe('a')
        ->and($graph->relationships())->toBe([]);
});

it('resolves relations against the directory of the declaring document', function (): void {
    $fs = graphFs();
    $fs->file('docs/guide/overview.md', "---\nrelations:\n  - ../api/endpoints.md\n  - getting-started.md\n---\n# Overview\n");
    $fs->file('docs/guide/getting-started.md', '# Getting started');
    $fs->file('docs/api/endpoints.md', '# Endpoints');

    expect(graphLinks(buildGraph(['docs'])))->toBe([
        'docs/guide/overview.md -> docs/api/endpoints.md',
        'docs/guide/overview.md -> docs/guide/getting-started.md',
    ]);
});

it('accepts an absolute relation path to a scanned file', function (): void {
    $fs = graphFs();
    $target = $fs->file('docs/b.md', '# B');
    $fs->file('docs/a.md', "---\nrelations:\n  - ".$target."\n---\n# A\n");

    expect(graphLinks(buildGraph(['docs'])))->toBe(['docs/a.md -> docs/b.md']);
});

it('skips a relation to a missing target', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\nrelations:\n  - missing.md\n---\n# A\n");

    expect(buildGraph(['docs'])->relationships())->toBe([]);
});

it('skips a relation to a file outside the scan', function (): void {
    $fs = graphFs();
    $fs->file('outside/b.md', '# B');
    $fs->file('docs/notes.txt', 'text');
    $fs->file('docs/hidden/.c.md', '# C');
    $fs->file('docs/a.md', "---\nrelations:\n  - ../outside/b.md\n  - notes.txt\n  - hidden/.c.md\n  - hidden\n---\n# A\n");

    expect(buildGraph(['docs'])->relationships())->toBe([]);
});

it('skips a relation to the same document', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\nrelations:\n  - a.md\n  - ./a.md\n---\n# A\n");

    expect(buildGraph(['docs'])->relationships())->toBe([]);
});

it('adds a duplicate relation once', function (): void {
    $fs = graphFs();
    $fs->file('docs/b.md', '# B');
    $fs->file('docs/a.md', "---\nrelations:\n  - b.md\n  - ./b.md\n  - ../docs/b.md\n  - b.md\n---\n# A\n");

    expect(graphLinks(buildGraph(['docs'])))->toBe(['docs/a.md -> docs/b.md']);
});

it('creates one bidirectional relationship for a pair that both documents declare', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\nrelations:\n  - b.md\n---\n# A\n");
    $fs->file('docs/b.md', "---\nrelations:\n  - a.md\n---\n# B\n");

    $graph = buildGraph(['docs']);

    expect(graphLinks($graph))->toBe(['docs/a.md -> docs/b.md'])
        ->and($graph->relationships()[0]->bidirectional())->toBeTrue();
});

it('creates a one-way relationship when only one document declares the pair', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\nrelations:\n  - b.md\n---\n# A\n");
    $fs->file('docs/b.md', '# B');

    $graph = buildGraph(['docs']);

    expect(graphLinks($graph))->toBe(['docs/a.md -> docs/b.md'])
        ->and($graph->relationships()[0]->bidirectional())->toBeFalse();
});

it('does not mark a duplicate declaration from the same document as bidirectional', function (): void {
    $fs = graphFs();
    $fs->file('docs/b.md', '# B');
    $fs->file('docs/a.md', "---\nrelations:\n  - b.md\n  - ./b.md\n  - b.md\n---\n# A\n");

    $graph = buildGraph(['docs']);

    expect($graph->relationships())->toHaveCount(1)
        ->and($graph->relationships()[0]->bidirectional())->toBeFalse();
});

it('keeps the bidirectional relationship in the position of the first declaration', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\nrelations:\n  - c.md\n  - b.md\n---\n# A\n");
    $fs->file('docs/b.md', '# B');
    $fs->file('docs/c.md', "---\nrelations:\n  - a.md\n---\n# C\n");

    $graph = buildGraph(['docs']);
    $flags = array_map(static fn (Relationship $relationship): bool => $relationship->bidirectional(), $graph->relationships());

    expect(graphLinks($graph))->toBe(['docs/a.md -> docs/c.md', 'docs/a.md -> docs/b.md'])
        ->and($flags)->toBe([true, false]);
});

it('keeps the graph node instances in a bidirectional relationship', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\nrelations:\n  - b.md\n---\n# A\n");
    $fs->file('docs/b.md', "---\nrelations:\n  - a.md\n---\n# B\n");

    $graph = buildGraph(['docs']);
    $relationship = $graph->relationships()[0];

    expect($relationship->source())->toBe(graphNodeAt($graph, 'docs/a.md'))
        ->and($relationship->target())->toBe(graphNodeAt($graph, 'docs/b.md'));
});

it('creates one relationship when the two ends use different relation paths', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\nrelations:\n  - ./sub/b.md\n---\n# A\n");
    $fs->file('docs/sub/b.md', "---\nrelations:\n  - ../a.md\n  - ../../docs/a.md\n---\n# B\n");

    $graph = buildGraph(['docs']);

    expect(graphLinks($graph))->toBe(['docs/a.md -> docs/sub/b.md'])
        ->and($graph->relationships()[0]->bidirectional())->toBeTrue();
});

it('keeps the direction of a relation that only the later document declares', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', '# A');
    $fs->file('docs/b.md', "---\nrelations:\n  - a.md\n---\n# B\n");

    $graph = buildGraph(['docs']);

    expect(graphLinks($graph))->toBe(['docs/b.md -> docs/a.md'])
        ->and($graph->relationships()[0]->bidirectional())->toBeFalse();
});

it('keeps the direction of the first declaration in path order', function (): void {
    $fs = graphFs();
    $fs->file('docs/z.md', "---\nrelations:\n  - a.md\n---\n# Z\n");
    $fs->file('docs/a.md', "---\nrelations:\n  - z.md\n---\n# A\n");

    $graph = buildGraph(['docs/z.md', 'docs/a.md']);
    $relationship = $graph->relationships()[0];

    expect(graphLinks($graph))->toBe(['docs/a.md -> docs/z.md'])
        ->and($relationship->bidirectional())->toBeTrue()
        ->and($relationship->source())->toBe(graphNodeAt($graph, 'docs/a.md'))
        ->and($relationship->target())->toBe(graphNodeAt($graph, 'docs/z.md'));
});

it('keeps the relationships in declaration order', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\nrelations:\n  - c.md\n  - b.md\n---\n# A\n");
    $fs->file('docs/b.md', "---\nrelations:\n  - c.md\n  - a.md\n---\n# B\n");
    $fs->file('docs/c.md', "---\nrelations:\n  - b.md\n  - a.md\n---\n# C\n");

    $graph = buildGraph(['docs']);
    $flags = array_map(static fn (Relationship $relationship): bool => $relationship->bidirectional(), $graph->relationships());

    expect(graphLinks($graph))->toBe([
        'docs/a.md -> docs/c.md',
        'docs/a.md -> docs/b.md',
        'docs/b.md -> docs/c.md',
    ])->and($flags)->toBe([true, true, true]);
});

it('returns only the outgoing neighbours of a built node', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\nrelations:\n  - b.md\n---\n# A\n");
    $fs->file('docs/b.md', "---\nrelations:\n  - c.md\n  - a.md\n---\n# B\n");
    $fs->file('docs/c.md', '# C');
    $fs->file('docs/d.md', '# D');

    $graph = buildGraph(['docs']);

    expect(graphPaths(new Graph($graph->relationsOf(graphNodeAt($graph, 'docs/a.md')))))->toBe(['docs/b.md'])
        ->and(graphPaths(new Graph($graph->relationsOf(graphNodeAt($graph, 'docs/b.md')))))->toBe(['docs/a.md', 'docs/c.md'])
        ->and($graph->relationsOf(graphNodeAt($graph, 'docs/c.md')))->toBe([])
        ->and($graph->relationsOf(graphNodeAt($graph, 'docs/d.md')))->toBe([]);
});

it('returns the neighbourhood of each node of a built graph', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\nrelations:\n  - b.md\n---\n# A\n");
    $fs->file('docs/b.md', "---\nrelations:\n  - c.md\n---\n# B\n");
    $fs->file('docs/c.md', "---\nrelations:\n  - d.md\n---\n# C\n");
    $fs->file('docs/d.md', "---\nrelations:\n  - c.md\n---\n# D\n");
    $fs->file('docs/e.md', "---\nrelations:\n  - a.md\n---\n# E\n");
    $fs->file('docs/f.md', '# F');

    $graph = buildGraph(['docs']);
    $neighbourhood = static fn (string $path): array => graphPaths(new Graph($graph->neighbourhoodOf(graphNodeAt($graph, $path))));

    expect($neighbourhood('docs/a.md'))->toBe(['docs/a.md', 'docs/b.md', 'docs/e.md'])
        ->and($neighbourhood('docs/b.md'))->toBe(['docs/a.md', 'docs/b.md', 'docs/c.md'])
        ->and($neighbourhood('docs/c.md'))->toBe(['docs/b.md', 'docs/c.md', 'docs/d.md'])
        ->and($neighbourhood('docs/d.md'))->toBe(['docs/c.md', 'docs/d.md'])
        ->and($neighbourhood('docs/e.md'))->toBe(['docs/a.md', 'docs/e.md'])
        ->and($neighbourhood('docs/f.md'))->toBe(['docs/f.md']);
});

it('skips relation entries that are not strings', function (): void {
    $fs = graphFs();
    $fs->file('docs/b.md', '# B');
    $fs->file('docs/a.md', "---\nrelations:\n  - 123\n  - true\n  - null\n  - [b.md]\n  - {path: b.md}\n  - ''\n  - '   '\n---\n# A\n");

    expect(buildGraph(['docs'])->relationships())->toBe([]);
});

it('trims relation entries', function (): void {
    $fs = graphFs();
    $fs->file('docs/b.md', '# B');
    $fs->file('docs/a.md', "---\nrelations:\n  - '  b.md  '\n---\n# A\n");

    expect(graphLinks(buildGraph(['docs'])))->toBe(['docs/a.md -> docs/b.md']);
});

it('ignores relations that are not a list', function (): void {
    $fs = graphFs();
    $fs->file('docs/b.md', '# B');
    $fs->file('docs/a.md', "---\nrelations: b.md\n---\n# A\n");

    expect(buildGraph(['docs'])->relationships())->toBe([]);
});

it('sorts the nodes by absolute path', function (): void {
    $fs = graphFs();
    $fs->file('docs/z.md', '# Z');
    $fs->file('docs/b/c.md', '# C');
    $fs->file('docs/a.md', '# A');

    $graph = buildGraph(['docs/z.md', 'docs/b', 'docs/a.md']);
    $absolutePaths = array_map(static fn (Node $node): string => $node->absolutePath, $graph->nodes());
    $sorted = $absolutePaths;
    sort($sorted, SORT_STRING);

    expect($absolutePaths)->toBe($sorted)
        ->and(graphPaths($graph))->toBe(['docs/a.md', 'docs/b/c.md', 'docs/z.md']);
});

it('creates relationships that reference the graph nodes', function (): void {
    $fs = graphFs();
    $fs->file('docs/a.md', "---\nrelations:\n  - b.md\n---\n# A\n");
    $fs->file('docs/b.md', '# B');

    $graph = buildGraph(['docs']);
    $relationship = $graph->relationships()[0];

    expect($graph->node($relationship->source()->id))->toBe($relationship->source())
        ->and($graph->node($relationship->target()->id))->toBe($relationship->target());
});
