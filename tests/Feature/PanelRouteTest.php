<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Lucacodorean\BaPanel\Core\Feasibility\FeasibilityRequest;
use Lucacodorean\BaPanel\Core\View\PanelPage;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;
use Lucacodorean\BaPanel\Laravel\Http\Controllers\PanelController;
use Lucacodorean\BaPanel\Tests\Support\PanelConfig;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use Lucacodorean\BaPanel\Tests\TestCase;

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk when $cleanup is true.
 */
function panelRouteFixtures(bool $cleanup = false): ?TemporaryFilesystem
{
    static $fs = null;

    if ($cleanup) {
        $fs?->cleanup();
        $fs = null;

        return null;
    }

    return $fs ??= new TemporaryFilesystem;
}

/**
 * Boot the application with a valid enabled config.
 */
function bootEnabledPanel(TestCase $test): void
{
    $fs = panelRouteFixtures();

    $test->withBaPanelConfig(PanelConfig::enabled((string) $fs?->directory('docs')))->rebootApplication();
}

afterEach(function (): void {
    panelRouteFixtures(cleanup: true);
});

it('registers the named route when enabled', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $route = Route::getRoutes()->getByName('ba-panel');

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe('ba-panel')
        ->and($route?->methods())->toContain('GET')
        ->and($route?->gatherMiddleware())->toContain('web')
        ->and(route('ba-panel', absolute: false))->toBe('/ba-panel');
});

it('does not register the route when disabled', function (): void {
    /** @var TestCase $this */
    $this->withBaPanelConfig(PanelConfig::disabled())->rebootApplication();

    expect(Route::has('ba-panel'))->toBeFalse();

    $this->get('/ba-panel')->assertNotFound();
});

it('does not register the route with the default config', function (): void {
    /** @var TestCase $this */
    expect(Route::has('ba-panel'))->toBeFalse();

    $this->get('/ba-panel')->assertNotFound();
});

it('resolves the panel template even when disabled', function (): void {
    /** @var TestCase $this */
    $this->withBaPanelConfig(PanelConfig::disabled())->rebootApplication();

    $renderer = $this->application()->make(TemplateRenderer::class);

    expect(realpath($renderer->resolve(PanelPage::TEMPLATE)))->toBe(realpath(dirname(__DIR__, 2).'/resources/views/panel.php'));
});

it('returns the panel page as HTML', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $response = $this->get('/ba-panel')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

    $content = (string) $response->getContent();

    expect($content)->toStartWith('<!DOCTYPE html>')
        ->and($content)->toContain('</html>')
        ->and($content)->not->toContain('<?php')
        ->and($content)->not->toContain('<?=')
        ->and($content)->not->toContain('{{')
        ->and($content)->not->toContain('@if');
});

it('sets the lang attribute from the application locale', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);
    $this->application()->setLocale('pt_BR');

    expect((string) $this->get('/ba-panel')->assertOk()->getContent())->toContain('<html lang="pt-BR">');
});

it('escapes the locale in the lang attribute', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);
    $this->application()->setLocale('en"><script>');

    $content = (string) $this->get('/ba-panel')->assertOk()->getContent();

    expect($content)->toContain('<html lang="en&quot;&gt;&lt;script&gt;">')
        ->and($content)->not->toContain('<html lang="en"><script>');
});

it('sets the message limit on the message input', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $content = (string) $this->get('/ba-panel')->assertOk()->getContent();

    expect($content)->toContain('maxlength="'.FeasibilityRequest::MAX_MESSAGE_LENGTH.'"')
        ->and($content)->toContain('maxlength="5000"')
        ->and($content)->toContain('var MAX_CONTEXT_FILES = 50;');
});

it('renders the session token in the CSRF meta tag', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $response = $this->get('/ba-panel')->assertOk();

    expect((string) $response->getContent())->toContain('<meta name="csrf-token" content="'.session()->token().'">');
});

it('does not render the CSRF meta tag when the request has no session', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $request = Request::create('/ba-panel');
    $response = $this->application()->call([$this->application()->make(PanelController::class), 'index'], ['request' => $request]);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and((string) $response->getContent())->not->toContain('<meta name="csrf-token"')
        ->and((string) $response->getContent())->toContain('var csrfHeader = "X-CSRF-TOKEN";');
});

it('uses a host template that overrides the panel template', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $template = resource_path('views/vendor/ba-panel/panel.php');
    $directory = dirname($template);

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($template, '<p>Host panel <?= $e($csrfHeader) ?> <?= $js($searchUrl) ?></p>');

    try {
        $this->get('/ba-panel')
            ->assertOk()
            ->assertContent('<p>Host panel X-CSRF-TOKEN "\/ba-panel\/search"</p>');
    } finally {
        File::deleteDirectory(resource_path('views/vendor/ba-panel'));
    }
});

it('renders the panel text', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $this->get('/ba-panel')
        ->assertOk()
        ->assertSee('Workspace')
        ->assertSee('Documentation')
        ->assertSee('Feasibility agent')
        ->assertSee('placeholder="Describe what you want to build…"', false);
});

it('renders an empty graph container', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $content = (string) $this->get('/ba-panel')->assertOk()->getContent();

    expect($content)->toContain('class="graph"')
        ->and(preg_match('/<div class="graph"[^>]*>\s*<\/div>/', $content))->toBe(1);
});

it('does not render the placeholder graph', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $content = (string) $this->get('/ba-panel')->assertOk()->getContent();

    expect($content)->not->toContain('<line')
        ->and($content)->not->toContain('Click a file to read it')
        ->and($content)->not->toContain('data-model.md')
        ->and($content)->not->toContain('services.md');
});

it('disables the send and export buttons', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $content = (string) $this->get('/ba-panel')->assertOk()->getContent();

    expect(preg_match('/<button[^>]*class="[^"]*send-button[^"]*"[^>]*\sdisabled[\s>]/', $content))->toBe(1)
        ->and(preg_match_all('/<button[^>]*class="[^"]*export-button[^"]*"[^>]*\sdisabled[\s>]/', $content))->toBe(1)
        ->and(preg_match_all('/<button[^>]*class="[^"]*export-button[^"]*"/', $content))->toBe(1);
});

it('has one Markdown export button and no PDF export', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $content = (string) $this->get('/ba-panel')->assertOk()->getContent();

    expect(preg_match('/<button[^>]*class="[^"]*export-button[^"]*"[^>]*\sdata-export-md[\s>]/', $content))->toBe(1)
        ->and($content)->toContain('.md</span>')
        ->and($content)->not->toContain('data-export-pdf')
        ->and($content)->not->toContain('.pdf');
});

it('has the CSRF token meta tag', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $content = (string) $this->get('/ba-panel')->assertOk()->getContent();

    expect(preg_match('/<meta name="csrf-token" content="[^"]+">/', $content))->toBe(1);
});

/**
 * Return the first opening tag that matches the pattern. Return an empty string when no tag matches.
 */
function panelOpeningTag(string $content, string $pattern): string
{
    return preg_match($pattern, $content, $matches) === 1 ? $matches[0] : '';
}

it('renders the theme toggle in the light state', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $content = (string) $this->get('/ba-panel')->assertOk()->getContent();
    $toggle = panelOpeningTag($content, '/<button[^>]*\bdata-theme-toggle\b[^>]*>/');

    expect($content)
        ->toContain('<button type="button" class="button theme-toggle" aria-label="Switch to dark theme" data-theme-toggle>')
        ->toContain('<span data-theme-label>Dark</span>')
        ->and($toggle)->not->toBe('')
        ->and($toggle)->not->toContain('aria-pressed');
});

it('shows the moon icon and hides the sun icon', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $content = (string) $this->get('/ba-panel')->assertOk()->getContent();
    $moon = panelOpeningTag($content, '/<svg[^>]*data-theme-icon="moon"[^>]*>/');
    $sun = panelOpeningTag($content, '/<svg[^>]*data-theme-icon="sun"[^>]*>/');

    expect($moon)->not->toBe('')
        ->and(preg_match('/\shidden[\s>]/', $moon))->toBe(0)
        ->and($sun)->not->toBe('')
        ->and(preg_match('/\shidden[\s>]/', $sun))->toBe(1);
});

it('renders the html tag without a theme attribute', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $content = (string) $this->get('/ba-panel')->assertOk()->getContent();
    $html = panelOpeningTag($content, '/<html\b[^>]*>/');

    expect($html)->not->toBe('')
        ->and($html)->not->toContain('data-theme');
});

it('defines the light and dark theme styles', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $this->get('/ba-panel')
        ->assertOk()
        ->assertSee(':root[data-theme="dark"]', false)
        ->assertSee('color-scheme: light;', false)
        ->assertSee('color-scheme: dark;', false);
});

it('reads the stored theme and the system preference', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $this->get('/ba-panel')
        ->assertOk()
        ->assertSee('ba-panel-theme', false)
        ->assertSee('(prefers-color-scheme: dark)', false);
});
