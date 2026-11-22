<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lucacodorean\BaPanel\Graph\DocumentRenderer;
use Lucacodorean\BaPanel\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Graph\Node;
use Lucacodorean\BaPanel\Search\NameSearch;
use Lucacodorean\BaPanel\Search\SearchResult;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serve the panel page, the documents, and the name search.
 */
final class PanelController
{
    private const string VIEW = 'ba-panel::panel';

    private const string QUERY_PARAMETER = 'q';

    /**
     * Longer queries are cut to this number of characters.
     */
    private const int MAX_QUERY_LENGTH = 200;

    /**
     * Show the panel with the documentation graph.
     */
    public function index(GraphBuilder $builder): View
    {
        return view(self::VIEW, ['graph' => $builder->build()->toArray()]);
    }

    /**
     * Return the rendered document of the node. The document is found only by the node id.
     * The relations are the nodes that the links lead to. The subgraph is the ids of the node and its direct neighbours.
     *
     * @throws NotFoundHttpException When the id is not in the graph.
     */
    public function show(string $node, GraphBuilder $builder, DocumentRenderer $renderer): JsonResponse
    {
        $graph = $builder->build();
        $document = $graph->node($node);

        if ($document === null) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse([
            ...$document->toArray(),
            'html' => $renderer->render($document),
            'relations' => array_map(
                static fn (Node $target): array => ['id' => $target->id, 'name' => $target->name, 'path' => $target->path],
                $graph->relationsOf($document),
            ),
            'subgraph' => array_map(static fn (Node $neighbour): string => $neighbour->id, $graph->neighbourhoodOf($document)),
        ]);
    }

    /**
     * Return the nodes whose name matches the query. A blank query returns no results.
     */
    public function search(Request $request, GraphBuilder $builder, NameSearch $search): JsonResponse
    {
        $query = $request->query(self::QUERY_PARAMETER);
        $query = is_string($query) ? mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH) : '';

        $results = $query === '' ? [] : $search->search($builder->build(), $query);

        return new JsonResponse([
            'query' => $query,
            'threshold' => $search->threshold(),
            'results' => array_map(static fn (SearchResult $result): array => $result->toArray(), $results),
        ]);
    }
}
