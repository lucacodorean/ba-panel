<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Graph\Graph;
use Lucacodorean\BaPanel\Graph\Node;
use Lucacodorean\BaPanel\Graph\Relationship;

/**
 * Create a node with values derived from the name.
 */
function graphTestNode(string $name): Node
{
    return new Node(
        id: substr(sha1($name), 0, 12),
        name: $name,
        title: ucfirst($name),
        path: 'docs/'.$name.'.md',
        absolutePath: '/secret/root/docs/'.$name.'.md',
    );
}

it('returns the nodes in the given order', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');

    expect((new Graph([$a, $b]))->nodes())->toBe([$a, $b]);
});

it('returns an empty graph by default', function (): void {
    $graph = new Graph;

    expect($graph->nodes())->toBe([])
        ->and($graph->relationships())->toBe([])
        ->and($graph->toArray())->toBe(['nodes' => [], 'relationships' => []]);
});

it('keeps one node per id', function (): void {
    $a = graphTestNode('a');

    expect((new Graph([$a, $a]))->nodes())->toBe([$a]);
});

it('returns the node for a known id', function (): void {
    $a = graphTestNode('a');

    expect((new Graph([$a]))->node($a->id))->toBe($a);
});

it('returns null for an unknown id', function (): void {
    expect((new Graph([graphTestNode('a')]))->node('000000000000'))->toBeNull()
        ->and((new Graph)->node(''))->toBeNull();
});

it('returns the relationships in the given order', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $first = new Relationship($a, $b);
    $second = new Relationship($b, $a);

    expect((new Graph([$a, $b], [$first, $second]))->relationships())->toBe([$first, $second]);
});

/**
 * Return the names of the nodes, in the given order.
 *
 * @param  list<Node>  $nodes
 * @return list<string>
 */
function graphTestNames(array $nodes): array
{
    return array_map(static fn (Node $node): string => $node->name, $nodes);
}

it('returns only the outgoing neighbours of a node', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $c = graphTestNode('c');
    $graph = new Graph([$a, $b, $c], [
        new Relationship($a, $b),
        new Relationship($c, $a),
        new Relationship($b, $c),
    ]);

    expect($graph->relationsOf($a))->toBe([$b])
        ->and($graph->relationsOf($b))->toBe([$c])
        ->and($graph->relationsOf($c))->toBe([$a]);
});

it('does not return the source as a neighbour of the target of a one-way relationship', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');

    expect((new Graph([$a, $b], [new Relationship($a, $b)]))->relationsOf($b))->toBe([]);
});

it('returns the neighbour of a bidirectional relationship from both ends', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $graph = new Graph([$a, $b], [new Relationship($a, $b, true)]);

    expect($graph->relationsOf($a))->toBe([$b])
        ->and($graph->relationsOf($b))->toBe([$a]);
});

it('returns each neighbour once', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $graph = new Graph([$a, $b], [
        new Relationship($a, $b),
        new Relationship($b, $a),
        new Relationship($a, $b, true),
    ]);

    expect($graph->relationsOf($a))->toBe([$b])
        ->and($graph->relationsOf($b))->toBe([$a]);
});

it('sorts the neighbours by path', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $c = graphTestNode('c');
    $d = graphTestNode('d');
    $graph = new Graph([$a, $b, $c, $d], [
        new Relationship($a, $d),
        new Relationship($a, $c),
        new Relationship($b, $a, true),
    ]);

    expect($graph->relationsOf($a))->toBe([$b, $c, $d]);
});

it('returns no neighbours for an isolated node', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $c = graphTestNode('c');
    $graph = new Graph([$a, $b, $c], [new Relationship($a, $b)]);

    expect($graph->relationsOf($c))->toBe([])
        ->and((new Graph([$a]))->relationsOf($a))->toBe([]);
});

it('finds the neighbours of an equal node by id', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $graph = new Graph([$a, $b], [new Relationship($a, $b), new Relationship($b, $a, true)]);

    expect($graph->relationsOf(graphTestNode('a')))->toBe([$b])
        ->and($graph->relationsOf(graphTestNode('b')))->toBe([$a]);
});

it('returns both ends of a one-way relationship from either end', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $graph = new Graph([$a, $b], [new Relationship($a, $b)]);

    expect($graph->neighbourhoodOf($a))->toBe([$a, $b])
        ->and($graph->neighbourhoodOf($b))->toBe([$a, $b]);
});

it('returns only the direct neighbours in a chain', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $c = graphTestNode('c');
    $graph = new Graph([$a, $b, $c], [new Relationship($a, $b), new Relationship($b, $c)]);

    expect($graph->neighbourhoodOf($a))->toBe([$a, $b])
        ->and($graph->neighbourhoodOf($b))->toBe([$a, $b, $c])
        ->and($graph->neighbourhoodOf($c))->toBe([$b, $c]);
});

it('does not follow relationships further than one step', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $c = graphTestNode('c');
    $d = graphTestNode('d');
    $graph = new Graph([$a, $b, $c, $d], [
        new Relationship($a, $b),
        new Relationship($b, $c),
        new Relationship($c, $d),
    ]);

    expect($graph->neighbourhoodOf($a))->toBe([$a, $b])
        ->and($graph->neighbourhoodOf($d))->toBe([$c, $d]);
});

it('returns both ends of a bidirectional relationship from either end', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $graph = new Graph([$a, $b], [new Relationship($a, $b, true)]);

    expect($graph->neighbourhoodOf($a))->toBe([$a, $b])
        ->and($graph->neighbourhoodOf($b))->toBe([$a, $b]);
});

it('includes a neighbour with an incoming relationship only', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $c = graphTestNode('c');
    $graph = new Graph([$a, $b, $c], [new Relationship($a, $b), new Relationship($c, $b)]);

    expect($graph->neighbourhoodOf($b))->toBe([$a, $b, $c])
        ->and($graph->neighbourhoodOf($a))->toBe([$a, $b])
        ->and($graph->neighbourhoodOf($c))->toBe([$b, $c]);
});

it('returns only the node itself for an isolated node', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $c = graphTestNode('c');
    $graph = new Graph([$a, $b, $c], [new Relationship($a, $b)]);

    expect($graph->neighbourhoodOf($c))->toBe([$c])
        ->and((new Graph([$a]))->neighbourhoodOf($a))->toBe([$a]);
});

it('lists each neighbour once in a cycle', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $c = graphTestNode('c');
    $graph = new Graph([$a, $b, $c], [
        new Relationship($a, $b),
        new Relationship($b, $c),
        new Relationship($c, $a),
    ]);

    expect($graph->neighbourhoodOf($a))->toBe([$a, $b, $c])
        ->and($graph->neighbourhoodOf($b))->toBe([$a, $b, $c])
        ->and($graph->neighbourhoodOf($c))->toBe([$a, $b, $c]);
});

it('lists each neighbour once with multiple relationships to it', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $graph = new Graph([$a, $b], [
        new Relationship($a, $b),
        new Relationship($b, $a),
        new Relationship($a, $b, true),
    ]);

    expect($graph->neighbourhoodOf($a))->toBe([$a, $b])
        ->and($graph->neighbourhoodOf($b))->toBe([$a, $b]);
});

it('lists the node itself once with a relationship to itself', function (): void {
    $a = graphTestNode('a');
    $graph = new Graph([$a], [new Relationship($a, $a)]);

    expect($graph->neighbourhoodOf($a))->toBe([$a]);
});

it('sorts the neighbourhood by path and includes the selected node', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $c = graphTestNode('c');
    $d = graphTestNode('d');
    $graph = new Graph([$d, $c, $b, $a], [
        new Relationship($c, $d),
        new Relationship($a, $c),
        new Relationship($c, $b),
    ]);

    expect(graphTestNames($graph->neighbourhoodOf($c)))->toBe(['a', 'b', 'c', 'd'])
        ->and(graphTestNames($graph->neighbourhoodOf($d)))->toBe(['c', 'd']);
});

it('finds the neighbourhood of an equal node by id', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $graph = new Graph([$a, $b], [new Relationship($a, $b)]);

    expect(graphTestNames($graph->neighbourhoodOf(graphTestNode('b'))))->toBe(['a', 'b']);
});

it('converts the graph to an array of nodes and relationships', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');

    expect((new Graph([$a, $b], [new Relationship($a, $b)]))->toArray())->toBe([
        'nodes' => [
            ['id' => $a->id, 'name' => 'a', 'title' => 'A', 'path' => 'docs/a.md'],
            ['id' => $b->id, 'name' => 'b', 'title' => 'B', 'path' => 'docs/b.md'],
        ],
        'relationships' => [
            ['source' => $a->id, 'target' => $b->id, 'bidirectional' => false],
        ],
    ]);
});

it('puts the bidirectional flag in the array', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $c = graphTestNode('c');
    $graph = new Graph([$a, $b, $c], [new Relationship($a, $b, true), new Relationship($c, $a)]);

    expect($graph->toArray()['relationships'])->toBe([
        ['source' => $a->id, 'target' => $b->id, 'bidirectional' => true],
        ['source' => $c->id, 'target' => $a->id, 'bidirectional' => false],
    ]);
});

it('never puts the absolute path in the array', function (): void {
    $a = graphTestNode('a');
    $b = graphTestNode('b');
    $array = (new Graph([$a, $b], [new Relationship($a, $b)]))->toArray();

    expect(json_encode($array))->not->toContain('/secret/root')
        ->and(array_keys($array['nodes'][0]))->toBe(['id', 'name', 'title', 'path']);
});
