<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Tests\Support\Symfony;

use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Ai\PromptLoader;
use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Core\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;
use Lucacodorean\BaPanel\Symfony\BaPanelBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * A minimal Symfony application with FrameworkBundle and BaPanelBundle.
 *
 * Each configuration gets its own cache directory and container class. Thus two configurations do not share a container.
 * The logger is synthetic. By default the agent factory is also synthetic. The test sets them after boot.
 * Thus no request reaches a real AI provider. With $fakeAgents false the bundle builds the real factory.
 * The test aliases are public, so a test can get the package services that the container would inline.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string AGENTS = 'test.ba_panel.agents';

    public const string AI_SETTINGS = 'test.ba_panel.ai_settings';

    public const string GRAPH_BUILDER = 'test.ba_panel.graph_builder';

    public const string PROMPTS = 'test.ba_panel.prompts';

    public const string RENDERER = 'test.ba_panel.renderer';

    /**
     * @param  array<string, mixed>|null  $panelConfig  The "ba_panel" config. Null to use the env defaults of the bundle.
     * @param  array<string, string|null>  $env  The BAP_* environment values. They are part of the cache key only.
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly string $cacheRoot,
        private readonly ?array $panelConfig,
        private readonly bool $csrf,
        private readonly array $env = [],
        private readonly bool $fakeAgents = true,
    ) {
        parent::__construct('test', false);
    }

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle, new BaPanelBundle];
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getCacheDir(): string
    {
        return $this->cacheRoot.'/'.$this->key().'/cache';
    }

    public function getLogDir(): string
    {
        return $this->cacheRoot.'/'.$this->key().'/log';
    }

    /**
     * Return the cache key of the configuration.
     */
    public function key(): string
    {
        return substr(sha1(serialize([$this->projectDir, $this->panelConfig, $this->csrf, $this->env, $this->fakeAgents])), 0, 16);
    }

    protected function getContainerClass(): string
    {
        return 'BaPanelTestKernel'.$this->key().'Container';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'ba-panel-test-secret',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'csrf_protection' => $this->csrf,
            'session' => $this->csrf ? ['storage_factory_id' => 'session.storage.factory.mock_file'] : false,
            'php_errors' => ['log' => false],
            'router' => ['utf8' => true],
        ]);

        if ($this->panelConfig !== null) {
            $container->extension('ba_panel', $this->panelConfig);
        }

        // The test sets these services after boot. A synthetic public service is never inlined.
        $services = $container->services();
        $services->set('logger')->synthetic()->public();

        if ($this->fakeAgents) {
            $services->set(FeasibilityAgentFactory::class)->synthetic()->public();
        }

        $services->alias(self::AGENTS, FeasibilityAgentFactory::class)->public();
        $services->alias(self::AI_SETTINGS, AiSettings::class)->public();
        $services->alias(self::GRAPH_BUILDER, GraphBuilder::class)->public();
        $services->alias(self::PROMPTS, PromptLoader::class)->public();
        $services->alias(self::RENDERER, TemplateRenderer::class)->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@BaPanelBundle/routes/symfony.yaml');
    }
}
