<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Core\Graph\Node;
use Lucacodorean\BaPanel\Tests\Support\PanelConfig;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use Lucacodorean\BaPanel\Tests\TestCase;

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk and forget it when $cleanup is true.
 */
function graphBindingFixtures(bool $cleanup = false): ?TemporaryFilesystem
{
    static $fs = null;

    if ($cleanup) {
        $fs?->cleanup();
        $fs = null;

        return null;
    }

    return $fs ??= new TemporaryFilesystem;
}

afterEach(function (): void {
    graphBindingFixtures(cleanup: true);
});

it('resolves the graph builder from the configured docs paths', function (): void {
    /** @var TestCase $this */
    $fs = graphBindingFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('docs/a.md', '# A');
    $fs->file('more/b.md', '# B');

    $config = PanelConfig::enabled($fs->path('docs'));
    $config['docs'] = [$fs->path('docs'), $fs->path('more/b.md')];
    $this->withBaPanelConfig($config)->rebootApplication();

    $names = array_map(static fn (Node $node): string => $node->name, app(GraphBuilder::class)->build()->nodes());

    expect($names)->toBe(['a', 'b']);
});

it('resolves the graph builder when the panel is disabled', function (): void {
    /** @var TestCase $this */
    $this->withBaPanelConfig(PanelConfig::disabled())->rebootApplication();

    expect(app(GraphBuilder::class)->build()->nodes())->toBe([]);
});

it('reads the docs config when the builder is resolved', function (): void {
    /** @var TestCase $this */
    $fs = graphBindingFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->file('docs/a.md', '# A');
    $this->withBaPanelConfig(PanelConfig::disabled())->rebootApplication();

    config()->set('ba-panel.docs', [$fs->path('docs'), 42, null]);

    expect(app(GraphBuilder::class)->build()->nodes())->toHaveCount(1);
});

it('ignores a docs config that is not a list', function (): void {
    /** @var TestCase $this */
    $this->withBaPanelConfig(PanelConfig::disabled())->rebootApplication();

    config()->set('ba-panel.docs', 'docs');

    expect(app(GraphBuilder::class)->build()->nodes())->toBe([]);
});
