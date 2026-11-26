<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Symfony\Controller;

use Lucacodorean\BaPanel\Core\Panel\PanelService;
use Lucacodorean\BaPanel\Core\View\PanelPage;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;
use Lucacodorean\BaPanel\Symfony\BaPanelBundle;
use Lucacodorean\BaPanel\Symfony\Routing\BaPanelRouteLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Serve the panel page, the documents, and the name search.
 */
final readonly class PanelController
{
    private const string QUERY_PARAMETER = 'q';

    /**
     * The header that sends the CSRF token. FeasibilityController reads this header.
     */
    public const string CSRF_HEADER = 'X-CSRF-Token';

    private const string CONTENT_TYPE = 'text/html; charset=UTF-8';

    /**
     * @param  ?CsrfTokenManagerInterface  $csrf  Null when the host has no CSRF token manager.
     */
    public function __construct(
        private PanelService $panel,
        private TemplateRenderer $renderer,
        private UrlGeneratorInterface $urls,
        private ?CsrfTokenManagerInterface $csrf = null,
    ) {}

    /**
     * Show the panel with the documentation graph.
     */
    public function index(Request $request): Response
    {
        $page = new PanelPage(
            graph: $this->panel->graph(),
            documentUrlTemplate: $this->urls->generate(BaPanelRouteLoader::ROUTE_DOCUMENTS, ['node' => PanelPage::DOCUMENT_ID_PLACEHOLDER]),
            searchUrl: $this->urls->generate(BaPanelRouteLoader::ROUTE_SEARCH),
            feasibilityUrl: $this->urls->generate(BaPanelRouteLoader::ROUTE_FEASIBILITY),
            csrfToken: $this->csrf?->getToken(BaPanelBundle::CSRF_TOKEN_ID)->getValue(),
            csrfHeader: self::CSRF_HEADER,
            locale: $request->getLocale(),
        );

        return new Response($this->renderer->render(PanelPage::TEMPLATE, $page->toArray()), Response::HTTP_OK, ['Content-Type' => self::CONTENT_TYPE]);
    }

    /**
     * Return the rendered document of the node. The document is found only by the node id.
     *
     * @throws NotFoundHttpException When the id is not in the graph.
     */
    public function show(string $node): JsonResponse
    {
        return new JsonResponse($this->panel->document($node) ?? throw new NotFoundHttpException);
    }

    /**
     * Return the nodes whose name matches the query. A blank query returns no results.
     * The query is read with all(), so a list value gives no results and not a 400 error.
     */
    public function search(Request $request): JsonResponse
    {
        return new JsonResponse($this->panel->search($request->query->all()[self::QUERY_PARAMETER] ?? null));
    }
}
