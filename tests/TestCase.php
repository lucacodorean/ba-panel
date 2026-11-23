<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Tests;

use Illuminate\Foundation\Application;
use Lucacodorean\BaPanel\BaPanelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

abstract class TestCase extends Orchestra
{
    /**
     * Config values that defineEnvironment() applies under the "ba-panel" key before boot.
     *
     * @var array<string, mixed>
     */
    protected array $baPanelConfig = [];

    /**
     * Set the ba-panel config values for the next application boot.
     *
     * @param  array<string, mixed>  $config
     */
    public function withBaPanelConfig(array $config): static
    {
        $this->baPanelConfig = $config;

        return $this;
    }

    /**
     * Create a new application with the current ba-panel config values.
     */
    public function rebootApplication(): void
    {
        $this->refreshApplication();
    }

    /**
     * Return the container of the current application. Test helpers use it to bind and resolve services.
     */
    public function application(): Application
    {
        return $this->app ?? throw new RuntimeException('The application is not created.');
    }

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [BaPanelServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // The web middleware encrypts cookies. The encrypter needs an application key.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));

        foreach ($this->baPanelConfig as $key => $value) {
            $app['config']->set('ba-panel.'.$key, $value);
        }
    }
}
