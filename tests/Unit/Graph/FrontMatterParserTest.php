<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Graph\FrontMatterParser;

it('returns the metadata and the body', function (): void {
    $result = (new FrontMatterParser)->parse("---\ntitle: Overview\nrelations:\n  - ../api/endpoints.md\n  - getting-started.md\n---\n# Overview\n\nText.\n");

    expect($result['metadata'])->toBe([
        'title' => 'Overview',
        'relations' => ['../api/endpoints.md', 'getting-started.md'],
    ])->and($result['body'])->toContain('# Overview')
        ->and($result['body'])->toContain('Text.')
        ->and($result['body'])->not->toContain('relations:')
        ->and($result['body'])->not->toContain('---');
});

it('returns empty metadata and the full contents without front matter', function (): void {
    $contents = "# Title\n\nText.\n";

    expect((new FrontMatterParser)->parse($contents))->toBe(['metadata' => [], 'body' => $contents]);
});

it('returns empty metadata and the full contents for invalid yaml', function (): void {
    $contents = "---\ntitle: [unclosed\n---\n# Title\n";

    expect((new FrontMatterParser)->parse($contents))->toBe(['metadata' => [], 'body' => $contents]);
});

it('returns empty metadata for scalar front matter', function (): void {
    $result = (new FrontMatterParser)->parse("---\njust a string\n---\n# Title\n");

    expect($result['metadata'])->toBe([])
        ->and($result['body'])->toContain('# Title')
        ->and($result['body'])->not->toContain('just a string');
});

it('returns empty metadata for an empty string', function (): void {
    expect((new FrontMatterParser)->parse(''))->toBe(['metadata' => [], 'body' => '']);
});
