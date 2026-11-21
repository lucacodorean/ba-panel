<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
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

it('registers the view namespace even when disabled', function (): void {
    /** @var TestCase $this */
    $this->withBaPanelConfig(PanelConfig::disabled())->rebootApplication();

    expect(view()->exists('ba-panel::panel'))->toBeTrue();
});

it('returns the panel view', function (): void {
    /** @var TestCase $this */
    bootEnabledPanel($this);

    $this->get('/ba-panel')
        ->assertOk()
        ->assertViewIs('ba-panel::panel');
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
        ->and(preg_match_all('/<button[^>]*class="[^"]*export-button[^"]*"[^>]*\sdisabled[\s>]/', $content))->toBe(2)
        ->and(preg_match_all('/<button[^>]*class="[^"]*export-button[^"]*"/', $content))->toBe(2);
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
