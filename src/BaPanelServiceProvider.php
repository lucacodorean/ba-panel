<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Lucacodorean\BaPanel\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Config\ConfigValidator;
use Lucacodorean\BaPanel\Graph\FrontMatterParser;
use Lucacodorean\BaPanel\Graph\GraphBuilder;

/**
 * The composition root of the package. Only the binding closures and boot() get services from the container.
 */
final class BaPanelServiceProvider extends ServiceProvider
{
    private const string CONFIG_PATH = __DIR__ . '/../config/ba-panel.php';

    private const string VIEWS_PATH = __DIR__ . '/../resources/views';

    private const string ROUTES_PATH = __DIR__ . '/../routes/web.php';

    private const string NAMESPACE = 'ba-panel';

    private const string CONFIG_ENABLED = 'ba-panel.enabled';

    private const string CONFIG_DOCS = 'ba-panel.docs';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'ba-panel');

        // The bindings are lazy. The container builds nothing until a class asks for the agent.
        $this->app->singleton(ProviderFactory::class);
        $this->app->singleton(FeasibilityAgentFactory::class);

        // Each request gets a new agent. The agent keeps no history between requests.
        $this->app->bind(FeasibilityAgent::class, static function (Application $app): FeasibilityAgent {
            return $app->make(FeasibilityAgentFactory::class)->make();
        });

        // The builder reads the docs paths when a class asks for it. The scan runs only on build().
        $this->app->bind(GraphBuilder::class, static function (Application $app): GraphBuilder {
            $docs = $app->make(Repository::class)->get(self::CONFIG_DOCS);
            $paths = is_array($docs) ? array_values(array_filter($docs, is_string(...))) : [];

            return new GraphBuilder($paths, $app->basePath(), $app->make(FrontMatterParser::class));
        });

        // The validator resolves relative docs paths against the application base path.
        $this->app->bind(ConfigValidator::class, static function (Application $app): ConfigValidator {
            return new ConfigValidator($app->basePath());
        });
    }

    public function boot(): void
    {
        $this->publishes([
            self::CONFIG_PATH => $this->app->configPath('ba-panel.php'),
        ], 'ba-panel-config');

        $this->publishes([
            self::VIEWS_PATH => $this->app->resourcePath('views/vendor/ba-panel'),
        ], 'ba-panel-views');

        $this->loadViewsFrom(self::VIEWS_PATH, self::NAMESPACE);

        $config = $this->app->make(Repository::class);

        if ($config->get(self::CONFIG_ENABLED) === true) {
            /** @var array<string, mixed> $settings */
            $settings = $config->get(self::NAMESPACE);

            $this->app->make(ConfigValidator::class)->validate($settings);

            // The feature flag controls the route. The panel is not available when the flag is false.
            $this->loadRoutesFrom(self::ROUTES_PATH);
        }
    }
}
