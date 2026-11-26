<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Graph;

/**
 * The documentation graph. Each node is a document. Each relationship is a directed link between two documents.
 * A relationship can be bidirectional. Then it can be followed from both ends.
 */
final readonly class Graph
{
    /**
     * @var array<string, Node>
     */
    private array $nodes;

    /**
     * @var list<Relationship>
     */
    private array $relationships;

    /**
     * @param  list<Node>  $nodes
     * @param  list<Relationship>  $relationships
     */
    public function __construct(array $nodes = [], array $relationships = [])
    {
        // The class is readonly. Build the keyed list first, then set the property one time.
        $keyed = [];

        foreach ($nodes as $node) {
            $keyed[$node->id] = $node;
        }

        $this->nodes = $keyed;
        $this->relationships = $relationships;
    }

    /**
     * @return list<Node>
     */
    public function nodes(): array
    {
        return array_values($this->nodes);
    }

    /**
     * @return list<Relationship>
     */
    public function relationships(): array
    {
        return $this->relationships;
    }

    /**
     * Return the node with the given id. Return null when the id is unknown.
     */
    public function node(string $id): ?Node
    {
        return $this->nodes[$id] ?? null;
    }

    /**
     * Return the nodes that the links lead to from the node. Each node is listed once, sorted by path.
     * A one-way link leads only from its source. A bidirectional link leads from both ends.
     *
     * @return list<Node>
     */
    public function relationsOf(Node $node): array
    {
        /** @var array<string, Node> $neighbours Neighbours keyed by id. */
        $neighbours = [];

        foreach ($this->relationships as $relationship) {
            $other = $relationship->leadsFrom($node) ? $relationship->other($node) : null;

            if ($other !== null) {
                $neighbours[$other->id] = $other;
            }
        }

        return $this->sortByPath(array_values($neighbours));
    }

    /**
     * Return the node and its direct neighbours, sorted by path. Each node is listed once.
     * A neighbour is a node that has a link with the node, in either direction. Links are not followed further.
     *
     * @return list<Node>
     */
    public function neighbourhoodOf(Node $node): array
    {
        /** @var array<string, Node> $neighbourhood Nodes keyed by id. */
        $neighbourhood = [$node->id => $node];

        foreach ($this->relationships as $relationship) {
            $other = $relationship->other($node);

            if ($other !== null) {
                $neighbourhood[$other->id] = $other;
            }
        }

        return $this->sortByPath(array_values($neighbourhood));
    }

    /**
     * @return array{
     *     nodes: list<array{id: string, name: string, title: string, path: string}>,
     *     relationships: list<array{source: string, target: string, bidirectional: bool}>
     * }
     */
    public function toArray(): array
    {
        return [
            'nodes' => array_map(static fn (Node $node): array => $node->toArray(), $this->nodes()),
            'relationships' => array_map(
                static fn (Relationship $relationship): array => $relationship->toArray(),
                $this->relationships,
            ),
        ];
    }

    /**
     * @param  list<Node>  $nodes
     * @return list<Node>
     */
    private function sortByPath(array $nodes): array
    {
        usort($nodes, static fn (Node $first, Node $second): int => strcmp($first->path, $second->path));

        return $nodes;
    }
}
