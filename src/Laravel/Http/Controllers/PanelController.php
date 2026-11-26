<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Laravel\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lucacodorean\BaPanel\Core\Panel\PanelService;
use Lucacodorean\BaPanel\Core\View\PanelPage;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serve the panel page, the documents, and the name search.
 */
final readonly class PanelController
{
    private const string QUERY_PARAMETER = 'q';

    /**
     * The Laravel CSRF middleware reads this header.
     */
    private const string CSRF_HEADER = 'X-CSRF-TOKEN';

    private const string CONTENT_TYPE = 'text/html; charset=UTF-8';

    /**
     * Show the panel with the documentation graph.
     */
    public function index(Request $request, PanelService $panel, TemplateRenderer $renderer, UrlGenerator $urls, Application $app): Response
    {
        $page = new PanelPage(
            graph: $panel->graph(),
            documentUrlTemplate: $urls->route('ba-panel.documents', ['node' => PanelPage::DOCUMENT_ID_PLACEHOLDER], false),
            searchUrl: $urls->route('ba-panel.search', [], false),
            feasibilityUrl: $urls->route('ba-panel.feasibility', [], false),
            csrfToken: $request->hasSession() ? $request->session()->token() : null,
            csrfHeader: self::CSRF_HEADER,
            locale: $app->getLocale(),
        );

        return new Response($renderer->render(PanelPage::TEMPLATE, $page->toArray()), Response::HTTP_OK, ['Content-Type' => self::CONTENT_TYPE]);
    }

    /**
     * Return the rendered document of the node. The document is found only by the node id.
     *
     * @throws NotFoundHttpException When the id is not in the graph.
     */
    public function show(string $node, PanelService $panel): JsonResponse
    {
        return new JsonResponse($panel->document($node) ?? throw new NotFoundHttpException);
    }

    /**
     * Return the nodes whose name matches the query. A blank query returns no results.
     */
    public function search(Request $request, PanelService $panel): JsonResponse
    {
        return new JsonResponse($panel->search($request->query(self::QUERY_PARAMETER)));
    }
}
