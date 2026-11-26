<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Graph\Node;
use Lucacodorean\BaPanel\Core\Graph\Relationship;

it('converts the node to the public data only', function (): void {
    $node = new Node('0123456789ab', 'data-model', 'Data model', 'docs/data-model.md', '/var/www/docs/data-model.md');

    expect($node->toArray())->toBe([
        'id' => '0123456789ab',
        'name' => 'data-model',
        'title' => 'Data model',
        'path' => 'docs/data-model.md',
    ])->and($node->toArray())->not->toHaveKey('absolutePath');
});

it('defines the node and the relationship as final readonly classes', function (string $class): void {
    $reflection = new ReflectionClass($class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->isReadOnly())->toBeTrue();
})->with([Node::class, Relationship::class]);

it('connects a source node and a target node', function (): void {
    $source = new Node('aaaaaaaaaaaa', 'a', 'A', 'a.md', '/a.md');
    $target = new Node('bbbbbbbbbbbb', 'b', 'B', 'b.md', '/b.md');
    $relationship = new Relationship($source, $target);

    expect($relationship->source())->toBe($source)
        ->and($relationship->target())->toBe($target)
        ->and($relationship->toArray())->toBe(['source' => 'aaaaaaaaaaaa', 'target' => 'bbbbbbbbbbbb', 'bidirectional' => false]);
});
