<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\View\PanelPage;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;

/**
 * Build a page with an empty graph and the given CSRF token.
 */
function panelPage(?string $csrfToken = 'token-123', string $locale = 'en'): PanelPage
{
    return new PanelPage(
        graph: ['nodes' => [], 'relationships' => []],
        documentUrlTemplate: '/ba-panel/documents/'.PanelPage::DOCUMENT_ID_PLACEHOLDER,
        searchUrl: '/ba-panel/search',
        feasibilityUrl: '/ba-panel/feasibility',
        csrfToken: $csrfToken,
        csrfHeader: 'X-Test-Token',
        locale: $locale,
    );
}

/**
 * Render the package panel template with the page data.
 */
function panelPageHtml(PanelPage $page): string
{
    return (new TemplateRenderer([dirname(__DIR__, 3).'/resources/views']))->render(PanelPage::TEMPLATE, $page->toArray());
}

it('has the template name, the placeholder and the default locale', function (): void {
    expect(PanelPage::TEMPLATE)->toBe('panel')
        ->and(PanelPage::DOCUMENT_ID_PLACEHOLDER)->toBe('000000000000')
        ->and(PanelPage::DOCUMENT_ID_PLACEHOLDER)->toMatch('/^[a-f0-9]{12}\z/')
        ->and(PanelPage::DEFAULT_LOCALE)->toBe('en');
});

it('returns the template variables', function (): void {
    $graph = ['nodes' => [['id' => 'abcdef012345', 'name' => 'a', 'title' => 'A', 'path' => 'docs/a.md']], 'relationships' => []];

    $page = new PanelPage($graph, '/documents/000000000000', '/search', '/feasibility', 'token', 'X-Header', 'de', 100, 5);

    expect($page->toArray())->toBe([
        'graph' => $graph,
        'documentUrlTemplate' => '/documents/000000000000',
        'searchUrl' => '/search',
        'feasibilityUrl' => '/feasibility',
        'csrfToken' => 'token',
        'csrfHeader' => 'X-Header',
        'locale' => 'de',
        'maxMessageLength' => 100,
        'maxContextFiles' => 5,
    ]);
});

it('uses the request limits by default', function (): void {
    $data = panelPage()->toArray();

    expect($data['locale'])->toBe('en')
        ->and($data['maxMessageLength'])->toBe(5000)
        ->and($data['maxContextFiles'])->toBe(50);
});

it('renders the CSRF meta tag only with a token', function (): void {
    expect(panelPageHtml(panelPage('token-123')))->toContain('<meta name="csrf-token" content="token-123">')
        ->and(panelPageHtml(panelPage(null)))->not->toContain('<meta name="csrf-token"')
        ->and(panelPageHtml(panelPage('')))->not->toContain('<meta name="csrf-token"');
});

it('escapes the CSRF token in the meta tag', function (): void {
    expect(panelPageHtml(panelPage('a"><script>')))->toContain('<meta name="csrf-token" content="a&quot;&gt;&lt;script&gt;">');
});

it('renders the header name, the urls and the limits as JSON', function (): void {
    $html = panelPageHtml(panelPage());

    expect($html)->toContain('var csrfHeader = "X-Test-Token";')
        ->and($html)->toContain('var documentUrlTemplate = "\/ba-panel\/documents\/000000000000";')
        ->and($html)->toContain('var searchUrl = "\/ba-panel\/search";')
        ->and($html)->toContain('var feasibilityUrl = "\/ba-panel\/feasibility";')
        ->and($html)->toContain('var MAX_CONTEXT_FILES = 50;')
        ->and($html)->toContain('maxlength="5000"')
        ->and($html)->toContain('var data = {"nodes":[],"relationships":[]};');
});

it('converts the locale to a language tag', function (): void {
    expect(panelPageHtml(panelPage(locale: 'pt_BR')))->toContain('<html lang="pt-BR">')
        ->and(panelPageHtml(panelPage(locale: '')))->toContain('<html lang="en">');
});

it('does not break the script block with graph data', function (): void {
    $graph = ['nodes' => [['id' => 'abcdef012345', 'name' => '</script><script>alert(1)</script>', 'title' => "it's & \"x\"", 'path' => 'docs/a.md']], 'relationships' => []];
    $html = panelPageHtml(new PanelPage($graph, '/d/000000000000', '/s', '/f', null, 'X'));

    expect($html)->not->toContain('</script><script>alert(1)')
        ->and(preg_match('/var data = (.*?);\n/', $html, $matches))->toBe(1)
        ->and(json_decode($matches[1], true))->toBe($graph);
});

it('renders the full page without template tags', function (): void {
    $html = panelPageHtml(panelPage());

    expect($html)->toStartWith('<!DOCTYPE html>')
        ->and(rtrim($html))->toEndWith('</html>')
        ->and($html)->not->toContain('<?')
        ->and($html)->not->toContain('{{')
        ->and($html)->not->toContain('{!!')
        ->and($html)->not->toContain('@json');
});
