<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Graph\Node;
use Lucacodorean\BaPanel\Graph\Relationship;

/**
 * Create a node with values derived from the name.
 */
function relationshipTestNode(string $name, ?string $title = null): Node
{
    return new Node(
        id: substr(sha1($name), 0, 12),
        name: $name,
        title: $title ?? ucfirst($name),
        path: 'docs/'.$name.'.md',
        absolutePath: '/secret/root/docs/'.$name.'.md',
    );
}

it('keeps the stored order of the two ends', function (): void {
    $a = relationshipTestNode('a');
    $b = relationshipTestNode('b');
    $relationship = new Relationship($b, $a);

    expect($relationship->source())->toBe($b)
        ->and($relationship->target())->toBe($a)
        ->and($relationship->toArray())->toBe(['source' => $b->id, 'target' => $a->id, 'bidirectional' => false]);
});

it('is not bidirectional by default', function (): void {
    $relationship = new Relationship(relationshipTestNode('a'), relationshipTestNode('b'));

    expect($relationship->bidirectional())->toBeFalse();
});

it('is bidirectional when the flag is set', function (): void {
    $a = relationshipTestNode('a');
    $b = relationshipTestNode('b');
    $relationship = new Relationship($a, $b, true);

    expect($relationship->bidirectional())->toBeTrue()
        ->and($relationship->source())->toBe($a)
        ->and($relationship->target())->toBe($b)
        ->and($relationship->toArray())->toBe(['source' => $a->id, 'target' => $b->id, 'bidirectional' => true]);
});

it('puts the keys source, target and bidirectional in the array', function (): void {
    $relationship = new Relationship(relationshipTestNode('a'), relationshipTestNode('b'));

    expect(array_keys($relationship->toArray()))->toBe(['source', 'target', 'bidirectional']);
});

it('leads from the source of a one-way relationship', function (): void {
    $a = relationshipTestNode('a');
    $b = relationshipTestNode('b');
    $relationship = new Relationship($a, $b);

    expect($relationship->leadsFrom($a))->toBeTrue()
        ->and($relationship->leadsFrom($b))->toBeFalse();
});

it('leads from both ends of a bidirectional relationship', function (): void {
    $a = relationshipTestNode('a');
    $b = relationshipTestNode('b');
    $relationship = new Relationship($a, $b, true);

    expect($relationship->leadsFrom($a))->toBeTrue()
        ->and($relationship->leadsFrom($b))->toBeTrue();
});

it('does not lead from a node that is not an end', function (bool $bidirectional): void {
    $relationship = new Relationship(relationshipTestNode('a'), relationshipTestNode('b'), $bidirectional);

    expect($relationship->leadsFrom(relationshipTestNode('c')))->toBeFalse();
})->with(['one-way' => [false], 'bidirectional' => [true]]);

it('compares the ends by id in leadsFrom', function (): void {
    $oneWay = new Relationship(relationshipTestNode('a'), relationshipTestNode('b'));
    $twoWay = new Relationship(relationshipTestNode('a'), relationshipTestNode('b'), true);

    expect($oneWay->leadsFrom(relationshipTestNode('a', 'Other title')))->toBeTrue()
        ->and($oneWay->leadsFrom(relationshipTestNode('b', 'Other title')))->toBeFalse()
        ->and($twoWay->leadsFrom(relationshipTestNode('b', 'Other title')))->toBeTrue();
});

it('connects each of the two ends', function (): void {
    $a = relationshipTestNode('a');
    $b = relationshipTestNode('b');
    $relationship = new Relationship($a, $b);

    expect($relationship->connects($a))->toBeTrue()
        ->and($relationship->connects($b))->toBeTrue();
});

it('does not connect a node that is not an end', function (): void {
    $relationship = new Relationship(relationshipTestNode('a'), relationshipTestNode('b'));

    expect($relationship->connects(relationshipTestNode('c')))->toBeFalse();
});

it('compares the ends by id in connects', function (): void {
    $relationship = new Relationship(relationshipTestNode('a'), relationshipTestNode('b'));

    expect($relationship->connects(relationshipTestNode('a', 'Other title')))->toBeTrue();
});

it('returns the opposite end', function (): void {
    $a = relationshipTestNode('a');
    $b = relationshipTestNode('b');
    $relationship = new Relationship($a, $b);

    expect($relationship->other($a))->toBe($b)
        ->and($relationship->other($b))->toBe($a);
});

it('returns null as the other end of a node that is not an end', function (): void {
    $relationship = new Relationship(relationshipTestNode('a'), relationshipTestNode('b'));

    expect($relationship->other(relationshipTestNode('c')))->toBeNull();
});

it('compares the ends by id in other', function (): void {
    $a = relationshipTestNode('a');
    $b = relationshipTestNode('b');
    $relationship = new Relationship($a, $b);

    expect($relationship->other(relationshipTestNode('a', 'Other title')))->toBe($b)
        ->and($relationship->other(relationshipTestNode('b', 'Other title')))->toBe($a);
});
