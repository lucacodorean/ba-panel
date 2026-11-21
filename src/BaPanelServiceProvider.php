<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel;

use Illuminate\Support\ServiceProvider;
use Lucacodorean\BaPanel\Config\ConfigValidator;

final class BaPanelServiceProvider extends ServiceProvider
{
    private const string CONFIG_PATH = __DIR__ . '/../config/ba-panel.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'ba-panel');
    }

    public function boot(): void
    {
        $this->publishes([
            self::CONFIG_PATH => config_path('ba-panel.php'),
        ], 'ba-panel-config');

        if (config('ba-panel.enabled') === true) {
            /** @var array<string, mixed> $config */
            $config = config('ba-panel');

            (new ConfigValidator(base_path()))->validate($config);
        }
    }
}
