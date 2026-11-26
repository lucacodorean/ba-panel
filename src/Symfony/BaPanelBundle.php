<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Symfony;

use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAssessor;
use Lucacodorean\BaPanel\Core\Ai\PromptLoader;
use Lucacodorean\BaPanel\Core\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Core\Config\ConfigValidator;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Core\Feasibility\FeasibilityService;
use Lucacodorean\BaPanel\Core\Graph\DocumentRenderer;
use Lucacodorean\BaPanel\Core\Graph\FrontMatterParser;
use Lucacodorean\BaPanel\Core\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Core\Markdown\SafeMarkdownConverter;
use Lucacodorean\BaPanel\Core\Panel\PanelService;
use Lucacodorean\BaPanel\Core\Reports\PackageVersion;
use Lucacodorean\BaPanel\Core\Reports\ReportMetadataFactory;
use Lucacodorean\BaPanel\Core\Search\NameSearch;
use Lucacodorean\BaPanel\Core\Search\Tokenizer;
use Lucacodorean\BaPanel\Core\Search\Vectorizer;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;
use Lucacodorean\BaPanel\Symfony\Controller\FeasibilityController;
use Lucacodorean\BaPanel\Symfony\Controller\PanelController;
use Lucacodorean\BaPanel\Symfony\Routing\BaPanelRouteLoader;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * The Symfony composition root of the package. Only loadExtension() and boot() wire services.
 *
 * The defaults read the same environment variables as the Laravel shell: BAP_ENABLED, BAP_AI_PROVIDER, BAP_AI_MODEL,
 * BAP_AI_KEY, BAP_AI_INSTRUCTIONS_PATH and BAP_DOCS_ARRAY. The host can set other values in config/packages/ba_panel.yaml.
 * The host imports the routes with "resource: '@BaPanelBundle/routes/symfony.yaml'".
 */
final class BaPanelBundle extends AbstractBundle
{
    public const string PARAMETER_ENABLED = 'ba_panel.enabled';

    public const string PARAMETER_CONFIG = 'ba_panel.config';

    public const string PARAMETER_AI = 'ba_panel.ai';

    public const string PARAMETER_DOCS = 'ba_panel.docs';

    /**
     * The CSRF token id of the panel. The panel page renders a token for this id. The feasibility endpoint checks it.
     */
    public const string CSRF_TOKEN_ID = 'ba-panel';

    private const string VIEWS_PATH = '/resources/views';

    /**
     * The host directory for template overrides. A template in this directory overrides the package template.
     */
    private const string HOST_VIEWS_PATH = '%kernel.project_dir%/templates/bundles/BaPanelBundle';

    private const string PROJECT_DIR = '%kernel.project_dir%';

    private const string DOCS_SEPARATOR = ',';

    /**
     * The bundle path is the package root. "@BaPanelBundle/routes/symfony.yaml" resolves against it.
     */
    public function getPath(): string
    {
        return dirname(__DIR__, 2);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        // The env placeholders are resolved at runtime. "default::" gives null when the variable is not set.
        $definition->rootNode()
            ->children()
                ->booleanNode('enabled')
                    ->info('Enable the panel. The configuration is validated only when this value is true.')
                    ->defaultValue('%env(bool:default::BAP_ENABLED)%')
                ->end()
                ->arrayNode('ai')
                    ->info('The AI provider settings. The provider, the model and the key are required when the panel is enabled.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('provider')->defaultValue('%env(default::BAP_AI_PROVIDER)%')->end()
                        ->scalarNode('model')->defaultValue('%env(default::BAP_AI_MODEL)%')->end()
                        ->scalarNode('key')->defaultValue('%env(default::BAP_AI_KEY)%')->end()
                        // A null value gives the package prompt file. Symfony has no published prompt path. See PromptLoader.
                        ->scalarNode('instructions_path')
                            ->info('The prompt file of the feasibility agent. The default is the package prompt file. A relative path resolves against the project directory.')
                            ->defaultValue('%env(default::BAP_AI_INSTRUCTIONS_PATH)%')
                        ->end()
                    ->end()
                ->end()
                // A variable node, because an array node refuses an env placeholder.
                ->variableNode('docs')
                    ->info('The documentation paths: a list of strings, or one comma-separated string. Relative paths are resolved against the project directory.')
                    ->defaultValue('%env(default::BAP_DOCS_ARRAY)%')
                ->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set(self::PARAMETER_ENABLED, $config['enabled'])
            ->set(self::PARAMETER_CONFIG, $config)
            ->set(self::PARAMETER_AI, $config['ai'])
            ->set(self::PARAMETER_DOCS, $config['docs']);

        $services = $container->services()
            ->defaults()
                ->autowire()
                ->private();

        // Stateless core services. The container builds each service once. The factory builds a new agent on each call.
        $services->set(ProviderFactory::class);
        $services->set(FrontMatterParser::class);
        $services->set(SafeMarkdownConverter::class);
        $services->set(DocumentRenderer::class);
        $services->set(Tokenizer::class);
        $services->set(Vectorizer::class);
        $services->set(NameSearch::class);
        $services->set(PackageVersion::class);
        $services->set(ReportMetadataFactory::class);
        $services->set(FeasibilityAssessor::class);
        $services->set(PromptLoader::class)
            ->args([self::PROJECT_DIR]);
        $services->set(FeasibilityAgentFactory::class);
        $services->set(PanelService::class);
        $services->set(FeasibilityService::class);

        $services->set(AiSettings::class)
            ->factory([AiSettings::class, 'fromArray'])
            ->args([param(self::PARAMETER_AI)]);

        $services->set(GraphBuilder::class)
            ->factory([self::class, 'graphBuilder'])
            ->args([param(self::PARAMETER_DOCS), self::PROJECT_DIR, service(FrontMatterParser::class)]);

        // Public, because boot() gets the validator from the container.
        $services->set(ConfigValidator::class)
            ->args([self::PROJECT_DIR])
            ->public();

        // The host templates come first, so a host template overrides the package template.
        $services->set(TemplateRenderer::class)
            ->args([[self::HOST_VIEWS_PATH, $this->getPath().self::VIEWS_PATH]]);

        $services->set(PanelController::class)
            ->arg('$csrf', service('security.csrf.token_manager')->nullOnInvalid())
            ->public()
            ->tag('controller.service_arguments');

        $services->set(FeasibilityController::class)
            ->arg('$csrf', service('security.csrf.token_manager')->nullOnInvalid())
            ->public()
            ->tag('controller.service_arguments');

        $services->set(BaPanelRouteLoader::class)
            ->args([param(self::PARAMETER_ENABLED)])
            ->tag('routing.loader');
    }

    /**
     * Validate the configuration when the panel is enabled, as the Laravel shell does at boot.
     *
     * @throws InvalidConfigurationException When the configuration is not valid.
     */
    public function boot(): void
    {
        $container = $this->container;

        if ($container === null || $container->getParameter(self::PARAMETER_ENABLED) !== true) {
            return;
        }

        $config = $container->getParameter(self::PARAMETER_CONFIG);
        $validator = $container->get(ConfigValidator::class);

        if (! is_array($config) || ! $validator instanceof ConfigValidator) {
            return;
        }

        $validator->validate([...$config, 'docs' => self::docsPaths($config['docs'] ?? null)]);
    }

    /**
     * Build the graph builder. The docs value is normalized as in the Laravel config file.
     */
    public static function graphBuilder(mixed $docs, string $basePath, FrontMatterParser $frontMatter): GraphBuilder
    {
        return new GraphBuilder(self::docsPaths($docs), $basePath, $frontMatter);
    }

    /**
     * Return the docs paths as a list. A string is split at each comma. Each path is trimmed. Blank paths are removed.
     *
     * @return list<string>
     */
    public static function docsPaths(mixed $docs): array
    {
        $items = match (true) {
            is_string($docs) => explode(self::DOCS_SEPARATOR, $docs),
            is_array($docs) => $docs,
            default => [],
        };

        $paths = array_map(trim(...), array_filter($items, is_string(...)));

        return array_values(array_filter($paths, static fn (string $path): bool => $path !== ''));
    }
}
