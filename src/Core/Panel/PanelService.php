<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Panel;

use Lucacodorean\BaPanel\Core\Graph\DocumentRenderer;
use Lucacodorean\BaPanel\Core\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Core\Graph\Node;
use Lucacodorean\BaPanel\Core\Search\NameSearch;
use Lucacodorean\BaPanel\Core\Search\SearchResult;

/**
 * Give the data of the panel: the documentation graph, the documents, and the name search.
 * The shells send the data as the HTTP response.
 */
final readonly class PanelService
{
    /**
     * Longer queries are cut to this number of characters.
     */
    public const int MAX_QUERY_LENGTH = 200;

    public function __construct(
        private GraphBuilder $builder,
        private DocumentRenderer $documents,
        private NameSearch $search,
    ) {}

    /**
     * Return the documentation graph.
     *
     * @return array{
     *     nodes: list<array{id: string, name: string, title: string, path: string}>,
     *     relationships: list<array{source: string, target: string, bidirectional: bool}>
     * }
     */
    public function graph(): array
    {
        return $this->builder->build()->toArray();
    }

    /**
     * Return the rendered document of the node. The document is found only by the node id.
     * The relations are the nodes that the links lead to. The subgraph is the ids of the node and its direct neighbours.
     * Return null when the id is not in the graph.
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     title: string,
     *     path: string,
     *     html: string,
     *     relations: list<array{id: string, name: string, path: string}>,
     *     subgraph: list<string>
     * }|null
     */
    public function document(string $id): ?array
    {
        $graph = $this->builder->build();
        $document = $graph->node($id);

        if ($document === null) {
            return null;
        }

        return [
            ...$document->toArray(),
            'html' => $this->documents->render($document),
            'relations' => array_map(
                static fn (Node $target): array => ['id' => $target->id, 'name' => $target->name, 'path' => $target->path],
                $graph->relationsOf($document),
            ),
            'subgraph' => array_map(static fn (Node $neighbour): string => $neighbour->id, $graph->neighbourhoodOf($document)),
        ];
    }

    /**
     * Return the nodes whose name matches the query. The query is trimmed and cut to MAX_QUERY_LENGTH characters.
     * A blank query or a value that is not a string returns no results.
     *
     * @return array{query: string, threshold: float, results: list<array{id: string, name: string, path: string, score: float}>}
     */
    public function search(mixed $query): array
    {
        $query = is_string($query) ? mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH) : '';

        $results = $query === '' ? [] : $this->search->search($this->builder->build(), $query);

        return [
            'query' => $query,
            'threshold' => $this->search->threshold(),
            'results' => array_map(static fn (SearchResult $result): array => $result->toArray(), $results),
        ];
    }
}
