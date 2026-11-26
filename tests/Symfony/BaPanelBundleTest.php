<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Core\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;
use Lucacodorean\BaPanel\Symfony\BaPanelBundle;
use Lucacodorean\BaPanel\Symfony\Routing\BaPanelRouteLoader;
use Lucacodorean\BaPanel\Tests\Support\EnvironmentVariables;
use Lucacodorean\BaPanel\Tests\Support\Symfony\SymfonyPanel;
use Lucacodorean\BaPanel\Tests\Support\Symfony\TestKernel;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\OpenAI\OpenAI;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Routing\RouterInterface;

/*
| These tests boot a minimal Symfony kernel with the bundle. They never send a request to an AI provider.
*/

const SYMFONY_BAP_ENV = ['BAP_ENABLED', 'BAP_AI_PROVIDER', 'BAP_AI_MODEL', 'BAP_AI_KEY', 'BAP_DOCS_ARRAY'];

/**
 * Return the environment helper of the current test. Restore the variables and forget the helper when $restore is true.
 */
function symfonyEnvironment(bool $restore = false): ?EnvironmentVariables
{
    static $env = null;

    if ($restore) {
        $env?->restore();
        $env = null;

        return null;
    }

    return $env ??= new EnvironmentVariables;
}

/**
 * Set the BAP_* variables. A missing name is removed. Return the values for the cache key of the kernel.
 *
 * @param  array<string, string>  $values
 * @return array<string, string|null>
 */
function symfonySetEnvironment(array $values): array
{
    $env = symfonyEnvironment() ?? throw new RuntimeException('No environment helper.');
    $set = [];

    foreach (SYMFONY_BAP_ENV as $name) {
        $env->set($name, $values[$name] ?? null);
        $set[$name] = $values[$name] ?? null;
    }

    return $set;
}

/**
 * Return the route names of the booted panel.
 *
 * @return list<string>
 */
function symfonyRouteNames(SymfonyPanel $panel): array
{
    $router = $panel->container()->get('router');

    if (! $router instanceof RouterInterface) {
        throw new RuntimeException('The router is not available.');
    }

    return array_keys($router->getRouteCollection()->all());
}

afterEach(function (): void {
    symfonyEnvironment(restore: true);
});

it('is an abstract bundle with the package root as path', function (): void {
    $bundle = new BaPanelBundle;

    expect($bundle)->toBeInstanceOf(AbstractBundle::class)
        ->and(realpath($bundle->getPath()))->toBe(realpath(dirname(__DIR__, 2)))
        ->and(is_file($bundle->getPath().'/routes/symfony.yaml'))->toBeTrue();
});

it('sets the parameters from the config', function (): void {
    $config = SymfonyPanel::enabledConfig();
    SymfonyPanel::file('docs/a.md', "# A\n");

    $container = SymfonyPanel::create($config)->boot()->container();

    expect($container->getParameter(BaPanelBundle::PARAMETER_ENABLED))->toBeTrue()
        ->and($container->getParameter(BaPanelBundle::PARAMETER_AI))->toBe($config['ai'])
        ->and($container->getParameter(BaPanelBundle::PARAMETER_DOCS))->toBe(['docs'])
        ->and($container->getParameter(BaPanelBundle::PARAMETER_CONFIG))->toBe($config);
});

it('uses the parameter names of the bundle', function (): void {
    expect(BaPanelBundle::PARAMETER_ENABLED)->toBe('ba_panel.enabled')
        ->and(BaPanelBundle::PARAMETER_CONFIG)->toBe('ba_panel.config')
        ->and(BaPanelBundle::PARAMETER_AI)->toBe('ba_panel.ai')
        ->and(BaPanelBundle::PARAMETER_DOCS)->toBe('ba_panel.docs')
        ->and(BaPanelBundle::CSRF_TOKEN_ID)->toBe('ba-panel');
});

it('registers the four routes when enabled', function (): void {
    SymfonyPanel::file('docs/a.md', "# A\n");
    $panel = SymfonyPanel::create(SymfonyPanel::enabledConfig())->boot();
    $router = $panel->container()->get('router');

    expect($router)->toBeInstanceOf(RouterInterface::class);

    /** @var RouterInterface $router */
    $routes = $router->getRouteCollection();

    expect(symfonyRouteNames($panel))->toBe(['ba_panel', 'ba_panel_documents', 'ba_panel_search', 'ba_panel_feasibility'])
        ->and($routes->get('ba_panel')?->getPath())->toBe('/ba-panel')
        ->and($routes->get('ba_panel')?->getMethods())->toBe(['GET'])
        ->and($routes->get('ba_panel_documents')?->getPath())->toBe('/ba-panel/documents/{node}')
        ->and($routes->get('ba_panel_documents')?->getRequirement('node'))->toBe('[a-f0-9]{12}')
        ->and($routes->get('ba_panel_documents')?->getMethods())->toBe(['GET'])
        ->and($routes->get('ba_panel_search')?->getPath())->toBe('/ba-panel/search')
        ->and($routes->get('ba_panel_search')?->getMethods())->toBe(['GET'])
        ->and($routes->get('ba_panel_feasibility')?->getPath())->toBe('/ba-panel/feasibility')
        ->and($routes->get('ba_panel_feasibility')?->getMethods())->toBe(['POST'])
        ->and($router->generate('ba_panel_documents', ['node' => 'abcdef012345']))->toBe('/ba-panel/documents/abcdef012345');
});

it('registers no routes when disabled', function (): void {
    $panel = SymfonyPanel::create(['enabled' => false])->boot();

    expect(symfonyRouteNames($panel))->toBe([])
        ->and($panel->get('/ba-panel')->getStatusCode())->toBe(404)
        ->and($panel->get('/ba-panel/search', ['q' => 'a'])->getStatusCode())->toBe(404)
        ->and($panel->postFeasibility(['message' => 'Request.'])->getStatusCode())->toBe(404);
});

it('does not validate the config when disabled', function (): void {
    $panel = SymfonyPanel::create([
        'enabled' => false,
        'ai' => ['provider' => 'ollama', 'model' => null, 'key' => null],
        'docs' => ['missing-docs'],
    ])->boot();

    expect($panel->container()->getParameter(BaPanelBundle::PARAMETER_ENABLED))->toBeFalse();
});

it('throws on boot when enabled with an invalid config', function (array $overrides, string $error): void {
    SymfonyPanel::file('docs/a.md', "# A\n");
    $panel = SymfonyPanel::create(SymfonyPanel::enabledConfig($overrides));

    try {
        $panel->boot();
        throw new LogicException('Expected InvalidConfigurationException.');
    } catch (InvalidConfigurationException $exception) {
        expect($exception->errors())->toContain($error);
    }
})->with([
    'missing docs path' => [['docs' => ['missing-docs']], 'BAP_DOCS_ARRAY path does not exist: missing-docs'],
    'missing AI key' => [['ai' => ['provider' => 'openai', 'model' => 'gpt-test', 'key' => null]], 'BAP_AI_KEY is required when BAP_ENABLED is true.'],
]);

it('resolves a relative docs path against the project directory', function (): void {
    SymfonyPanel::file('docs/guide.md', "# Guide\n");
    $panel = SymfonyPanel::create(SymfonyPanel::enabledConfig())->boot();

    $graph = $panel->container()->get(TestKernel::GRAPH_BUILDER);

    expect($graph)->toBeInstanceOf(GraphBuilder::class);

    /** @var GraphBuilder $graph */
    expect(array_column($graph->build()->toArray()['nodes'], 'path'))->toBe(['docs/guide.md']);
});

it('accepts the docs as one comma-separated string', function (): void {
    SymfonyPanel::file('docs/guide.md', "# Guide\n");
    SymfonyPanel::file('docs/more/extra.md', "# Extra\n");
    $panel = SymfonyPanel::create(SymfonyPanel::enabledConfig(['docs' => ' docs/more , ,docs/guide.md ']))->boot();

    /** @var GraphBuilder $graph */
    $graph = $panel->container()->get(TestKernel::GRAPH_BUILDER);

    expect(array_column($graph->build()->toArray()['nodes'], 'path'))->toBe(['docs/guide.md', 'docs/more/extra.md']);
});

it('builds the real agent factory from the AI config', function (): void {
    SymfonyPanel::file('docs/a.md', "# A\n");
    $panel = SymfonyPanel::create(
        SymfonyPanel::enabledConfig(['ai' => ['provider' => 'anthropic', 'model' => 'claude-test', 'key' => 'secret-key']]),
        fakeAgents: false,
    )->boot();

    $factory = $panel->container()->get(TestKernel::AGENTS);
    $settings = $panel->container()->get(TestKernel::AI_SETTINGS);

    expect($factory)->toBeInstanceOf(FeasibilityAgentFactory::class)
        ->and($settings)->toBeInstanceOf(AiSettings::class);

    /** @var FeasibilityAgentFactory $factory */
    /** @var AiSettings $settings */
    $agent = $factory->make();

    expect($settings->toArray())->toBe(['provider' => 'anthropic', 'model' => 'claude-test', 'key' => 'secret-key'])
        ->and($agent->getProvider())->toBeInstanceOf(Anthropic::class)
        ->and($agent->getProvider()->getModel())->toBe('claude-test')
        ->and($factory->make())->not->toBe($agent);
});

it('searches the host template directory before the package directory', function (): void {
    SymfonyPanel::file('docs/a.md', "# A\n");
    $panel = SymfonyPanel::create(SymfonyPanel::enabledConfig())->boot();

    /** @var TemplateRenderer $renderer */
    $renderer = $panel->container()->get(TestKernel::RENDERER);

    expect(realpath($renderer->resolve('panel')))->toBe(realpath(dirname(__DIR__, 2).'/resources/views/panel.php'));

    $host = SymfonyPanel::file('templates/bundles/BaPanelBundle/panel.php', 'Host');

    expect($renderer->resolve('panel'))->toBe($host)
        ->and(realpath($renderer->resolve('reports/feasibility')))->toBe(realpath(dirname(__DIR__, 2).'/resources/views/reports/feasibility.php'));
});

it('reads the env defaults when the host has no config', function (): void {
    SymfonyPanel::file('docs/guide.md', "# Guide\n");
    $env = symfonySetEnvironment([
        'BAP_ENABLED' => 'true',
        'BAP_AI_PROVIDER' => 'openai',
        'BAP_AI_MODEL' => 'gpt-env',
        'BAP_AI_KEY' => 'env-key',
        'BAP_DOCS_ARRAY' => ' docs , ,',
    ]);

    $panel = SymfonyPanel::create(null, env: $env, fakeAgents: false)->boot();
    $container = $panel->container();

    /** @var FeasibilityAgentFactory $factory */
    $factory = $container->get(TestKernel::AGENTS);
    /** @var GraphBuilder $graph */
    $graph = $container->get(TestKernel::GRAPH_BUILDER);

    expect($container->getParameter(BaPanelBundle::PARAMETER_ENABLED))->toBeTrue()
        ->and($container->getParameter(BaPanelBundle::PARAMETER_AI))->toBe(['provider' => 'openai', 'model' => 'gpt-env', 'key' => 'env-key'])
        ->and(BaPanelBundle::docsPaths($container->getParameter(BaPanelBundle::PARAMETER_DOCS)))->toBe(['docs'])
        ->and($factory->make()->getProvider())->toBeInstanceOf(OpenAI::class)
        ->and($factory->make()->getProvider()->getModel())->toBe('gpt-env')
        ->and(array_column($graph->build()->toArray()['nodes'], 'path'))->toBe(['docs/guide.md'])
        ->and(symfonyRouteNames($panel))->toHaveCount(4)
        ->and($panel->get('/ba-panel')->getStatusCode())->toBe(200);
});

it('is disabled when no env variable is set', function (): void {
    $env = symfonySetEnvironment([]);

    $panel = SymfonyPanel::create(null, env: $env, fakeAgents: false)->boot();
    $container = $panel->container();

    expect($container->getParameter(BaPanelBundle::PARAMETER_ENABLED))->toBeFalse()
        ->and($container->getParameter(BaPanelBundle::PARAMETER_AI))->toBe(['provider' => null, 'model' => null, 'key' => null])
        ->and(BaPanelBundle::docsPaths($container->getParameter(BaPanelBundle::PARAMETER_DOCS)))->toBe([])
        ->and(symfonyRouteNames($panel))->toBe([])
        ->and($panel->get('/ba-panel')->getStatusCode())->toBe(404);
});

it('is disabled when BAP_ENABLED is not a true value', function (string $value): void {
    $env = symfonySetEnvironment(['BAP_ENABLED' => $value]);

    $panel = SymfonyPanel::create(null, env: $env, fakeAgents: false)->boot();

    expect($panel->container()->getParameter(BaPanelBundle::PARAMETER_ENABLED))->toBeFalse()
        ->and(symfonyRouteNames($panel))->toBe([]);
})->with([
    'false' => ['false'],
    'zero' => ['0'],
    'empty' => [''],
]);

it('throws on boot when the env enables the panel with missing values', function (): void {
    $env = symfonySetEnvironment(['BAP_ENABLED' => 'true']);
    $panel = SymfonyPanel::create(null, env: $env, fakeAgents: false);

    try {
        $panel->boot();
        throw new LogicException('Expected InvalidConfigurationException.');
    } catch (InvalidConfigurationException $exception) {
        expect($exception->errors())->toContain(
            'BAP_AI_PROVIDER is required when BAP_ENABLED is true.',
            'BAP_AI_MODEL is required when BAP_ENABLED is true.',
            'BAP_AI_KEY is required when BAP_ENABLED is true.',
        );
    }
});

it('normalizes the docs paths', function (mixed $docs, array $expected): void {
    expect(BaPanelBundle::docsPaths($docs))->toBe($expected);
})->with([
    'list' => [['docs', 'more'], ['docs', 'more']],
    'list with blanks' => [[' docs ', '', '  ', 'more'], ['docs', 'more']],
    'list with values that are not strings' => [['docs', 1, null, ['x'], 'more'], ['docs', 'more']],
    'map' => [['a' => 'docs', 'b' => 'more'], ['docs', 'more']],
    'comma string' => ['docs,more', ['docs', 'more']],
    'comma string with blanks' => [' docs , , more ,', ['docs', 'more']],
    'one path' => ['docs', ['docs']],
    'empty string' => ['', []],
    'null' => [null, []],
    'integer' => [1, []],
    'true' => [true, []],
]);

it('returns no routes from the loader when not enabled', function (?bool $enabled): void {
    expect((new BaPanelRouteLoader($enabled))->load('.', BaPanelRouteLoader::TYPE)->all())->toBe([]);
})->with([
    'false' => [false],
    'null' => [null],
]);

it('returns the four routes from the loader when enabled', function (): void {
    $routes = (new BaPanelRouteLoader(true))->load('.', BaPanelRouteLoader::TYPE);

    expect(array_keys($routes->all()))->toBe([
        BaPanelRouteLoader::ROUTE_INDEX,
        BaPanelRouteLoader::ROUTE_DOCUMENTS,
        BaPanelRouteLoader::ROUTE_SEARCH,
        BaPanelRouteLoader::ROUTE_FEASIBILITY,
    ]);
});

it('supports only the ba_panel resource type', function (?string $type, bool $supported): void {
    expect((new BaPanelRouteLoader(true))->supports('.', $type))->toBe($supported);
})->with([
    'ba_panel' => ['ba_panel', true],
    'no type' => [null, false],
    'yaml' => ['yaml', false],
    'attribute' => ['attribute', false],
    'other case' => ['BA_PANEL', false],
]);

it('uses the route names and the type of the loader', function (): void {
    expect(BaPanelRouteLoader::TYPE)->toBe('ba_panel')
        ->and(BaPanelRouteLoader::ROUTE_INDEX)->toBe('ba_panel')
        ->and(BaPanelRouteLoader::ROUTE_DOCUMENTS)->toBe('ba_panel_documents')
        ->and(BaPanelRouteLoader::ROUTE_SEARCH)->toBe('ba_panel_search')
        ->and(BaPanelRouteLoader::ROUTE_FEASIBILITY)->toBe('ba_panel_feasibility');
});
