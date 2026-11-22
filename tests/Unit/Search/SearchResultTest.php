<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Graph\Node;
use Lucacodorean\BaPanel\Search\SearchResult;

it('converts the result to the public data with the score rounded to 4 decimals', function (): void {
    $node = new Node('0123456789ab', 'endpoint', 'Endpoint', 'docs/endpoint.md', '/root/docs/endpoint.md');

    expect((new SearchResult($node, 0.824957911))->toArray())->toBe([
        'id' => '0123456789ab',
        'name' => 'endpoint',
        'path' => 'docs/endpoint.md',
        'score' => 0.825,
    ])->and((new SearchResult($node, 0.123456))->toArray()['score'])->toBe(0.1235)
        ->and((new SearchResult($node, 1.0))->toArray()['score'])->toBe(1.0);
});

it('does not put the absolute path in the array', function (): void {
    $node = new Node('0123456789ab', 'a', 'A', 'docs/a.md', '/root/docs/a.md');

    expect((new SearchResult($node, 1.0))->toArray())->not->toHaveKey('absolutePath')
        ->and(json_encode((new SearchResult($node, 1.0))->toArray()))->not->toContain('/root/');
});
