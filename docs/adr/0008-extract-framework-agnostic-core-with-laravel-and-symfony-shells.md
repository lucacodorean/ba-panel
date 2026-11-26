# 0008. Extract a framework-agnostic core with Laravel and Symfony shells

- **Status**: Accepted
- **Date**: 2026-11-25
- **Supersedes**: 0005. Integrate the feasibility agent through a JSON endpoint with a structured-output report. Only the part about the Blade view of the report is superseded. The JSON endpoint and the structured output do not change.

## Context

The package must work in Laravel applications and in Symfony applications.

Before this decision, the package was a Laravel package:

- The `require` list of `composer.json` contained `illuminate/routing`, `illuminate/support`, and `illuminate/view`.
- A Symfony host that installed the package also installed these Laravel components.
- All classes were in one namespace, `Lucacodorean\BaPanel`. The graph, the search, the agent, and the reports were in the same namespace as the service provider and the controllers.
- The controllers validated the feasibility request. Thus the validation rules and the error messages were Laravel code.
- The panel page and the feasibility report were Blade views. Blade needs `illuminate/view`.

Some constraints apply:

- The configuration contract does not change. The environment variables are `BAP_ENABLED`, `BAP_AI_PROVIDER`, `BAP_AI_MODEL`, `BAP_AI_KEY`, and `BAP_DOCS_ARRAY`.
- The package validates the configuration at boot when the panel is enabled. See ADR 0001.
- The package classes get their services through dependency injection. See ADR 0006.
- The package classes are `final` and `readonly` by default. See ADR 0007.
- The Neuron AI integration skills for Laravel and Symfony describe chat persistence, threads, streaming, and database migrations. The feasibility agent of ADR 0005 has no history: one JSON request gives one report.
- A host must be able to override the templates.

## Decision

We will keep one package with three namespaces: a framework-free core and two thin shells for Laravel and Symfony, and we will render the templates of both shells with plain PHP templates from the core.

- **One package**: The Composer package stays `lucacodorean/ba-panel`. The `autoload` key does not change.
- **`Lucacodorean\BaPanel\Core`**: The core contains `Ai`, `Config`, `Exceptions`, `Feasibility`, `Graph`, `Markdown`, `Panel`, `Reports`, `Search`, and `View`.
  - The core uses only PHP, `league/commonmark`, `neuron-core/neuron-ai`, and `symfony/yaml`.
  - The core does not use `Illuminate` classes or the Symfony framework classes.
  - `Core\Panel\PanelService` gives the graph, the documents, and the name search.
  - `Core\Feasibility\FeasibilityRequest` validates the feasibility request. It owns the limits and the error messages.
  - `Core\Feasibility\FeasibilityService` runs the assessment. It documents the exception-to-status contract: `InvalidFeasibilityRequestException` gives 422, `InvalidConfigurationException` gives 503, and each other `Throwable` gives 502.
  - `Core\Config\AiSettings` holds the AI settings. `FeasibilityAgentFactory` gets them through its constructor.
- **`Lucacodorean\BaPanel\Laravel`**: The Laravel shell contains `BaPanelServiceProvider` and `Http\Controllers`. The routes are in `routes/laravel.php`.
- **`Lucacodorean\BaPanel\Symfony`**: The Symfony shell contains `BaPanelBundle`, `Controller`, and `Routing\BaPanelRouteLoader`. `BaPanelBundle` extends `AbstractBundle`. The routes are in `routes/symfony.yaml`.
- **Thin shells**: A shell reads the HTTP request, calls a core service, and returns the HTTP response. A shell maps the core exceptions to the HTTP statuses. A shell reports each 5xx exception and does not show the details.
- **Composer**: The `require` list contains only `php`, `composer-runtime-api`, `league/commonmark`, `neuron-core/neuron-ai`, and `symfony/yaml`.
  - `illuminate/routing` and `illuminate/support` `^13.5` are in `suggest` for the Laravel shell.
  - `symfony/framework-bundle` `^8.1` is in `suggest` for the Symfony shell. `symfony/security-csrf` `^8.1` is in `suggest` for the CSRF check of the Symfony shell.
  - The `conflict` key refuses `illuminate/support` below 13.5 and `symfony/framework-bundle` below 8.1.
  - `illuminate/view` is removed.
- **Templates**: `Core\View\TemplateRenderer` renders plain PHP templates: `resources/views/panel.php` and `resources/views/reports/feasibility.php`. Both shells use the same templates.
  - The renderer searches a list of directories. The first directory that contains the template wins.
  - Each shell puts the host directory before the package directory. Thus a host template overrides the package template.
  - The Laravel host directory is `resources/views/vendor/ba-panel`. The Symfony host directory is `templates/bundles/BaPanelBundle`.
  - Each template gets the `$e` helper to escape HTML and the `$js` helper to encode JSON for a `<script>` block.
  - The renderer refuses a template name with `..`, an absolute name, or a file extension.
  - `Core\View\PanelPage` holds the data of the panel page. Each shell gives its own URLs, CSRF token, and CSRF header.
- **Stateless agent**: The feasibility agent keeps no history. Each request builds a new agent. From the Neuron AI integration skills, only these rules apply:
  - Each request builds the agent. The Laravel shell binds `FeasibilityAgentFactory` and `AiSettings` for each resolution.
  - Only the configuration reads the environment variables.
  - The shell maps the exceptions to the HTTP statuses.
- **Boot validation**: Both shells keep the boot validation of ADR 0001. The Laravel shell validates in `BaPanelServiceProvider::boot()`. The Symfony shell validates in `BaPanelBundle::boot()`.
- **Enforcement**: The Pest architecture test `tests/Unit/ArchitectureTest.php` guards the boundaries.
  - The core does not use `Illuminate`, `Psr\Log`, the Symfony framework components, or `Symfony\Bundle`. The core gets `symfony/yaml` only through `league/commonmark`.
  - The core does not use the Laravel shell or the Symfony shell.
  - The Laravel shell does not use the Symfony shell. The Symfony shell does not use Laravel or the Laravel shell.
  - The services in `Core\Feasibility`, `Core\Panel`, `Core\View`, and `Core\Config` are `final readonly`.
  - `BaPanelBundle`, `BaPanelRouteLoader`, and `InvalidTemplateException` are not `readonly`, because their parent classes are not `readonly`. This adds three exceptions to the list of ADR 0007.

## Alternatives

- **Split the package into a core package and one package for each framework**: The team rejected this option. Three packages need three release cycles and three version contracts. The `suggest` and `conflict` keys give the same isolation in one package.
- **Keep Blade for Laravel and add Twig for Symfony**: The team rejected this option. Two template languages need two copies of the panel page and of the report. A change must then be made two times. The two copies can become different.
- **Add a renderer interface with one adapter for each framework**: The team rejected this option. The interface adds an abstraction with no use. Plain PHP templates work in both frameworks without an adapter.
- **Add chat persistence, threads, and streaming from the Neuron AI integration skills**: The team rejected this option. The agent gives one report for one request. Persistence needs database migrations in the host. Streaming needs a UI protocol. ADR 0005 did not select either.
- **Keep one namespace and move only the Laravel code to optional dependencies**: The team rejected this option. Without a namespace boundary, no test can find a framework class in the core.

## Consequences

### Positive

- A Symfony host does not install Laravel components. A Laravel host does not install the Symfony framework bundle.
- The core has the business rules one time. The two shells have the same validation, the same limits, and the same error messages.
- The two shells render the same templates. A template change applies to both shells.
- The architecture test finds a framework class in the core at once.
- A host overrides a template with a file in its own directory. The host does not change the package.

### Negative

- This is a breaking change. Each FQCN changes, for example `Lucacodorean\BaPanel\BaPanelServiceProvider` becomes `Lucacodorean\BaPanel\Laravel\BaPanelServiceProvider`. A host that imports a package class must update the `use` statements.
- Published Blade views from earlier versions no longer apply. A host must publish the views again and copy its changes into the `.php` templates.
- Plain PHP templates do not escape values automatically. Each template must escape each value with `$e`, or send each value through `MarkdownText` in the report.
- `FeasibilityReport::toMarkdown()` now needs a `TemplateRenderer`. It does not accept the Laravel view factory.
- The feasibility endpoint now returns 502, not 500, when the graph build or the template render fails.
- The Symfony shell reads `BAP_ENABLED` when the container is built. A change of `BAP_ENABLED` needs `cache:clear`.
- The Symfony CSRF header is `X-CSRF-Token`. The Laravel CSRF header is `X-CSRF-TOKEN`. The panel page sends the header that its shell gives.
- The package must test two shells. The development dependencies contain both frameworks.

## References

- Ticket BAP-008.
- [ADR 0001](0001-validate-env-contract-at-boot.md)
- [ADR 0005](0005-integrate-feasibility-agent-through-json-endpoint-with-structured-report.md)
- [ADR 0006](0006-inject-services-through-constructors-and-methods.md)
- [ADR 0007](0007-declare-immutable-classes-readonly-and-leaf-classes-final.md)
- [Symfony: AbstractBundle](https://symfony.com/doc/current/bundles/configuration.html)
- `composer.json`
- `src/Core/View/TemplateRenderer.php`
- `src/Core/View/PanelPage.php`
- `src/Core/Panel/PanelService.php`
- `src/Core/Feasibility/FeasibilityRequest.php`
- `src/Core/Feasibility/FeasibilityService.php`
- `src/Core/Config/AiSettings.php`
- `src/Laravel/BaPanelServiceProvider.php`
- `src/Laravel/Http/Controllers/PanelController.php`
- `src/Laravel/Http/Controllers/FeasibilityController.php`
- `src/Symfony/BaPanelBundle.php`
- `src/Symfony/Controller/PanelController.php`
- `src/Symfony/Controller/FeasibilityController.php`
- `src/Symfony/Routing/BaPanelRouteLoader.php`
- `resources/views/panel.php`
- `resources/views/reports/feasibility.php`
- `routes/laravel.php`
- `routes/symfony.yaml`
- `tests/Unit/ArchitectureTest.php`
