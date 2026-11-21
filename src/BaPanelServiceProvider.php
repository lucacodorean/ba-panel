<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Lucacodorean\BaPanel\Ai\BaPanelAgent;
use Lucacodorean\BaPanel\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Config\ConfigValidator;

final class BaPanelServiceProvider extends ServiceProvider
{
    private const string CONFIG_PATH = __DIR__ . '/../config/ba-panel.php';

    private const string VIEWS_PATH = __DIR__ . '/../resources/views';

    private const string ROUTES_PATH = __DIR__ . '/../routes/web.php';

    private const string NAMESPACE = 'ba-panel';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'ba-panel');

        // The bindings are lazy. The container builds nothing until a class asks for the agent.
        $this->app->singleton(ProviderFactory::class);

        $this->app->bind(BaPanelAgent::class, static function (Container $app): BaPanelAgent {
            $ai = config('ba-panel.ai');

            return new BaPanelAgent($app->make(ProviderFactory::class)->fromConfig(is_array($ai) ? $ai : []));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            self::CONFIG_PATH => config_path('ba-panel.php'),
        ], 'ba-panel-config');

        $this->publishes([
            self::VIEWS_PATH => resource_path('views/vendor/ba-panel'),
        ], 'ba-panel-views');

        $this->loadViewsFrom(self::VIEWS_PATH, self::NAMESPACE);

        if (config('ba-panel.enabled') === true) {
            /** @var array<string, mixed> $config */
            $config = config('ba-panel');

            (new ConfigValidator(base_path()))->validate($config);

            // The feature flag controls the route. The panel is not available when the flag is false.
            $this->loadRoutesFrom(self::ROUTES_PATH);
        }
    }
}
