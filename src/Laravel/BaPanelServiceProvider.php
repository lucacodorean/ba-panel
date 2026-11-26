<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Ai\PromptLoader;
use Lucacodorean\BaPanel\Core\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Core\Config\ConfigValidator;
use Lucacodorean\BaPanel\Core\Graph\FrontMatterParser;
use Lucacodorean\BaPanel\Core\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;

/**
 * The Laravel composition root of the package. Only the binding closures and boot() get services from the container.
 */
final class BaPanelServiceProvider extends ServiceProvider
{
    private const string CONFIG_PATH = __DIR__ . '/../../config/ba-panel.php';

    private const string VIEWS_PATH = __DIR__ . '/../../resources/views';

    private const string PROMPTS_PATH = __DIR__ . '/../../resources/prompts';

    /**
     * The host directory for the published prompt files.
     */
    private const string HOST_PROMPTS_PATH = 'prompts/vendor/ba-panel';

    /**
     * The file name of the feasibility prompt. The published copy has the same name.
     */
    private const string FEASIBILITY_PROMPT_FILE = 'feasibility.md';

    private const string ROUTES_PATH = __DIR__ . '/../../routes/laravel.php';

    /**
     * The host directory for the published templates. A template in this directory overrides the package template.
     */
    private const string HOST_VIEWS_PATH = 'views/vendor/ba-panel';

    private const string NAMESPACE = 'ba-panel';

    private const string CONFIG_ENABLED = 'ba-panel.enabled';

    private const string CONFIG_AI = 'ba-panel.ai';

    private const string CONFIG_DOCS = 'ba-panel.docs';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, self::NAMESPACE);

        // The bindings are lazy. The container builds nothing until a class asks for the agent.
        $this->app->singleton(ProviderFactory::class);
        $this->app->singleton(PromptLoader::class, static function (Application $app): PromptLoader {
            // The published path is the target of the "ba-panel-prompts" publish tag.
            return new PromptLoader(
                $app->basePath(),
                $app->resourcePath(self::HOST_PROMPTS_PATH.'/'.self::FEASIBILITY_PROMPT_FILE),
            );
        });

        // The settings are read from the config on each resolution. A config change applies to the next agent.
        $this->app->bind(AiSettings::class, static function (Application $app): AiSettings {
            $ai = $app->make(Repository::class)->get(self::CONFIG_AI);

            return AiSettings::fromArray(is_array($ai) ? $ai : []);
        });

        // The factory is not a singleton, so each resolution gets the current settings.
        $this->app->bind(FeasibilityAgentFactory::class);

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

        // The host templates come first, so a published template overrides the package template.
        $this->app->singleton(TemplateRenderer::class, static function (Application $app): TemplateRenderer {
            return new TemplateRenderer([$app->resourcePath(self::HOST_VIEWS_PATH), self::VIEWS_PATH]);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            self::CONFIG_PATH => $this->app->configPath('ba-panel.php'),
        ], 'ba-panel-config');

        $this->publishes([
            self::VIEWS_PATH => $this->app->resourcePath(self::HOST_VIEWS_PATH),
        ], 'ba-panel-views');

        $this->publishes([
            self::PROMPTS_PATH => $this->app->resourcePath(self::HOST_PROMPTS_PATH),
        ], 'ba-panel-prompts');

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
