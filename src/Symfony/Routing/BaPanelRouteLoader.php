<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Symfony\Routing;

use Lucacodorean\BaPanel\Symfony\Controller\FeasibilityController;
use Lucacodorean\BaPanel\Symfony\Controller\PanelController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Load the panel routes. The host imports them with "resource: ." and "type: ba_panel".
 * The loader returns no routes when the panel is not enabled.
 */
final class BaPanelRouteLoader extends Loader
{
    public const string TYPE = 'ba_panel';

    public const string ROUTE_INDEX = 'ba_panel';

    public const string ROUTE_DOCUMENTS = 'ba_panel_documents';

    public const string ROUTE_SEARCH = 'ba_panel_search';

    public const string ROUTE_FEASIBILITY = 'ba_panel_feasibility';

    /**
     * The node id is the only input. The controller never reads a path from the request.
     */
    private const string NODE_REQUIREMENT = '[a-f0-9]{12}';

    private const string CONTROLLER = '_controller';

    /**
     * @param ?bool $enabled The "ba_panel.enabled" value. Null when the environment variable is not set.
     */
    public function __construct(private readonly ?bool $enabled, ?string $env = null)
    {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();

        if ($this->enabled !== true) {
            return $routes;
        }

        $routes->add(self::ROUTE_INDEX, new Route('/ba-panel', [self::CONTROLLER => PanelController::class.'::index'], methods: ['GET']));
        $routes->add(self::ROUTE_DOCUMENTS, new Route(
            '/ba-panel/documents/{node}',
            [self::CONTROLLER => PanelController::class.'::show'],
            ['node' => self::NODE_REQUIREMENT],
            methods: ['GET'],
        ));
        $routes->add(self::ROUTE_SEARCH, new Route('/ba-panel/search', [self::CONTROLLER => PanelController::class.'::search'], methods: ['GET']));
        // The request gives node ids only. The controller finds each file through the graph.
        $routes->add(self::ROUTE_FEASIBILITY, new Route('/ba-panel/feasibility', [self::CONTROLLER => FeasibilityController::class.'::assess'], methods: ['POST']));

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === self::TYPE;
    }
}
