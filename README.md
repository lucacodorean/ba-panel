# ba-panel
A PHP package intended to create a panel for the Business analysts to access the documentation saved at project level.

The package works in Laravel 13 and in Symfony 8.1 applications.

## Package structure

The package has one core and two shells.

| namespace                         | contents                                                                                           |
|-----------------------------------|----------------------------------------------------------------------------------------------------|
| `Lucacodorean\BaPanel\Core`       | The framework-free core: `Ai`, `Config`, `Exceptions`, `Feasibility`, `Graph`, `Markdown`, `Panel`, `Reports`, `Search`, and `View`. |
| `Lucacodorean\BaPanel\Laravel`    | The Laravel shell: `BaPanelServiceProvider` and `Http\Controllers`.                                 |
| `Lucacodorean\BaPanel\Symfony`    | The Symfony shell: `BaPanelBundle`, `Controller`, and `Routing\BaPanelRouteLoader`.                 |

- The core does not use Laravel classes or Symfony framework classes.
- Both shells render the same plain PHP templates with `Core\View\TemplateRenderer`. The package does not use Blade or Twig.
- Composer installs only the core dependencies: `league/commonmark`, `neuron-core/neuron-ai`, and `symfony/yaml`.
- The framework packages are suggestions. Your application already contains the packages of its framework.

| framework | required packages                                         | version |
|-----------|-----------------------------------------------------------|---------|
| Laravel   | `illuminate/routing`, `illuminate/support`                | `^13.5` |
| Symfony   | `symfony/framework-bundle`                                | `^8.1`  |
| Symfony   | `symfony/security-csrf`. Optional. Enables the CSRF check. | `^8.1`  |

Composer refuses `illuminate/support` below 13.5 and `symfony/framework-bundle` below 8.1.

For the reasons behind this structure, see [ADR 0008](docs/adr/0008-extract-framework-agnostic-core-with-laravel-and-symfony-shells.md).

## Installation in Laravel

1. Install the package with Composer.

   ```bash
   composer require lucacodorean/ba-panel
   ```

2. Publish the configuration file.

   ```bash
   php artisan vendor:publish --tag=ba-panel-config
   ```

   This command creates `config/ba-panel.php` in your application.

3. Optional: publish the templates.

   ```bash
   php artisan vendor:publish --tag=ba-panel-views
   ```

   This command copies the templates to `resources/views/vendor/ba-panel` in your application.
   See [Override the templates](#override-the-templates).

Laravel package discovery registers `Lucacodorean\BaPanel\Laravel\BaPanelServiceProvider`.
You do not need to register the service provider manually.

## Installation in Symfony

1. Install the package with Composer.

   ```bash
   composer require lucacodorean/ba-panel
   ```

2. Register the bundle in `config/bundles.php`.

   ```php
   return [
       // ...
       Lucacodorean\BaPanel\Symfony\BaPanelBundle::class => ['all' => true],
   ];
   ```

3. Import the routes, for example in `config/routes/ba_panel.yaml`.

   ```yaml
   ba_panel:
       resource: '@BaPanelBundle/routes/symfony.yaml'
   ```

   You can also import the route loader directly:

   ```yaml
   ba_panel:
       resource: .
       type: ba_panel
   ```

4. Optional: install `symfony/security-csrf` to enable the CSRF check. See [CSRF protection](#csrf-protection).

   ```bash
   composer require symfony/security-csrf
   ```

## Configuration

Set the environment variables in the `.env` file of your application. Both shells read the same variables.

| variable          | type   | default | required                     | description                                              |
|-------------------|--------|---------|------------------------------|----------------------------------------------------------|
| `BAP_ENABLED`     | bool   | `false` | no                           | Enables the panel. Enables the configuration validation. |
| `BAP_AI_PROVIDER` | string | none    | yes, when `BAP_ENABLED=true` | The AI provider name. See the supported values below.    |
| `BAP_AI_MODEL`    | string | none    | yes, when `BAP_ENABLED=true` | The AI model name.                                       |
| `BAP_AI_KEY`      | string | none    | yes, when `BAP_ENABLED=true` | The API key for the AI provider.                         |
| `BAP_DOCS_ARRAY`  | string | empty   | yes, when `BAP_ENABLED=true` | A comma-separated list of documentation paths.           |

The package reads `BAP_ENABLED` as a boolean. The values `true`, `1`, `yes`, and `on` enable the panel.

Only the configuration reads the environment variables. The package classes get the values from the configuration.

### `BAP_AI_PROVIDER` values

The package uses [neuron-ai](https://github.com/neuron-core/neuron-ai) to connect to the AI provider.

| value       | provider  |
|-------------|-----------|
| `anthropic` | Anthropic |
| `openai`    | OpenAI    |
| `gemini`    | Gemini    |
| `mistral`   | Mistral   |
| `deepseek`  | DeepSeek  |
| `xai`       | xAI Grok  |

- The value ignores case and outer whitespace.
- Each provider needs an API key in `BAP_AI_KEY`.
- The package does not support providers without an API key, for example Ollama, Bedrock, Vertex, and Azure.

### `BAP_DOCS_ARRAY` rules

- Separate the paths with commas.
- Each path can be a file or a directory.
- The package trims the spaces around each path. It ignores empty entries.
- The package resolves a relative path against the application base path. In Symfony, the base path is `%kernel.project_dir%`.
- The package keeps an absolute path as given.
- Each path must exist.
- When `BAP_ENABLED=true`, the list must contain at least one path.

### Sample `.env` block

```dotenv
BAP_ENABLED=true
BAP_AI_PROVIDER=anthropic
BAP_AI_MODEL=your-model-name
BAP_AI_KEY=your-api-key
BAP_DOCS_ARRAY="docs, README.md"
```

### Laravel configuration file

`config/ba-panel.php` reads the environment variables. The keys are `enabled`, `ai.provider`, `ai.model`, `ai.key`, and `docs`.

### Symfony configuration file

The bundle reads the environment variables by default. To set other values, create `config/packages/ba_panel.yaml`.

```yaml
ba_panel:
    enabled: true
    ai:
        provider: anthropic
        model: your-model-name
        key: '%env(BAP_AI_KEY)%'
    docs:
        - docs
        - README.md
```

| key           | default                         | description                                                        |
|---------------|---------------------------------|--------------------------------------------------------------------|
| `enabled`     | `%env(bool:default::BAP_ENABLED)%` | Enables the panel. Enables the configuration validation.        |
| `ai.provider` | `%env(default::BAP_AI_PROVIDER)%` | The AI provider name.                                           |
| `ai.model`    | `%env(default::BAP_AI_MODEL)%`  | The AI model name.                                                 |
| `ai.key`      | `%env(default::BAP_AI_KEY)%`    | The API key for the AI provider.                                   |
| `docs`        | `%env(default::BAP_DOCS_ARRAY)%` | A list of paths, or one comma-separated string.                   |

- Keep the API key in an environment variable. Do not write the key in the YAML file.
- Symfony reads `enabled` when it builds the container. After a change of `BAP_ENABLED`, run `php bin/console cache:clear`.

## Routes

The two shells have the same paths. The route names are different.

| method | URI                          | Laravel route name     | Symfony route name     | response                                          |
|--------|------------------------------|------------------------|------------------------|---------------------------------------------------|
| `GET`  | `/ba-panel`                  | `ba-panel`             | `ba_panel`             | The panel page, with the graph.                   |
| `GET`  | `/ba-panel/documents/{node}` | `ba-panel.documents`   | `ba_panel_documents`   | JSON: the rendered document and its relations.    |
| `GET`  | `/ba-panel/search?q=`        | `ba-panel.search`      | `ba_panel_search`      | JSON: the documents whose name matches the query. |
| `POST` | `/ba-panel/feasibility`      | `ba-panel.feasibility` | `ba_panel_feasibility` | JSON: the feasibility report of the request.      |

- In Laravel, the routes use the `web` middleware. The routes are in `routes/laravel.php`.
- In Symfony, the route loader `Lucacodorean\BaPanel\Symfony\Routing\BaPanelRouteLoader` gives the routes. `routes/symfony.yaml` imports it.
- The package registers the routes only when the panel is enabled.
- When the panel is not enabled, the routes do not exist. A request gets a 404 response.
- `{node}` is the node id: 12 lowercase hexadecimal characters. Another value or an unknown id gets a 404 response.
- The documents route returns `{id, name, title, path, html, relations: [{id, name, path}], subgraph: [id]}`.
- `relations` lists the documents that the links lead to from the opened document. These are the documents that the opened document declares. A bidirectional link counts from both ends. A document that only declares the opened document is not listed. Each document is listed once, sorted by path.
- `subgraph` lists the ids of the opened document and of its direct neighbours. A neighbour has a link with the opened document in either direction: outgoing, incoming, or bidirectional. Links are not followed further. Each document is listed once, sorted by path.
- The search route returns `{query, threshold, results: [{id, name, path, score}]}`.
- The search trims `q` and cuts it to 200 characters. A blank `q` returns no results.
- The panel page uses relative URLs for the document, search, and feasibility requests. The panel works behind an HTTPS reverse proxy, for example the ddev router.
- For the feasibility route, see [Feasibility agent](#feasibility-agent).

Use the route name to create a link.

```php
// Laravel
route('ba-panel');
```

```twig
{# Symfony #}
{{ path('ba_panel') }}
```

## Write documentation for the panel

The panel shows the documentation as a graph. Each node is one Markdown file. Each edge is one relation between two files. An arrow shows the direction of the relation.

### Scanned files

- The package reads the paths from `BAP_DOCS_ARRAY`.
- A configured file is used when its extension is `.md`. The extension ignores case.
- A configured directory is scanned recursively for `*.md` files.
- Inside a scanned directory, the package skips hidden files and hidden directories. A hidden name starts with `.`.
- A file that two configured paths contain gives one node.
- A configured path that does not exist is skipped.

### Front matter

Start the file with YAML front matter between two `---` lines. The front matter is optional.

| key         | type             | description                                                           |
|-------------|------------------|-----------------------------------------------------------------------|
| `title`     | string           | The title of the document. Optional.                                  |
| `relations` | list of strings  | The paths of the related documents. Optional.                         |

Example file `docs/architecture/overview.md`:

```markdown
---
title: Architecture overview
relations:
  - ../api/endpoints.md
  - getting-started.md
---
# Architecture overview

The text of the document.
```

The panel does not show the front matter in the reader.

### Title rules

The package uses the first value that is available:

1. The `title` value from the front matter.
2. The text of the first `# ` heading in the body.
3. The file name without the extension.

Invalid YAML gives no metadata. The package does not throw an exception. The title then comes from rule 2 or rule 3.

### Relation rules

- Write each relation path relative to the directory of the declaring file.
- In the example, `../api/endpoints.md` points to `docs/api/endpoints.md`.
- An absolute path is also accepted.
- A relation is directed. When file A declares file B, the edge goes from A to B.
- To link two files in both directions, declare the relation in both files. The edge is then bidirectional.
- The package skips a relation without an error in these conditions:
  - The target file does not exist.
  - The target file is not a scanned file.
  - The target file is the declaring file.
  - The value is not a string.
- Each pair of files gives one edge. This is true when A declares B, B declares A, or both.
- For a one-way edge, the direction is the direction of the declaration.
- A duplicate relation in one file also gives one edge.

### Panel features

- **Graph**: The panel shows the nodes and the edges. The graph floats. You can drag a node. The section header shows the number of files and links. The links count is the number of unique pairs of linked files. A bidirectional pair counts one time.
- **Arrows**: A one-way edge has an arrow at the target end. A bidirectional edge has an arrow at each end. The arrows of the edges of the selected node use the accent color.
- **Reader**: The reader is hidden by default. The graph then uses the full width of the documentation section. Click a node to open the document. The reader then shows the document next to the graph. The reader shows the path, the rendered Markdown, and the linked files. The linked files are the files that the links lead to from the document. A bidirectional link counts from both ends. Click a linked file to open it. A relative `.md` link in the text also opens that document.
- **Subgraph view**: When you open a document, the graph shows only the subgraph of that document. The subgraph contains the document and the files with a direct link to it. The direction of the link is not important: outgoing, incoming, and bidirectional links are included. The panel does not follow the links further. The panel shows an edge only when the two ends are visible. The panel hides the other nodes and their edges.
  - A file that only declares the opened document is visible in the subgraph. The reader does not list it in the linked files.
  - This is true for a click on a node, a linked file, a link in the document, or a filter result.
  - Click a node in the subgraph to open that document and show its subgraph.
  - Click **Show all files** in the graph area to show the full graph again.
  - Click the reader close button to close the document. The panel hides the reader. The graph shows the full graph and uses the full width again.
  - The section header always shows the counts of the full graph.
- **Filter**: Type a query in the search field. The panel shows a list of files whose name matches the query. Click a result to open it.
  - The filter compares the query with the file names only.
  - A name word that starts with a query word is a full match. For example, `end` finds `endpoints`.
  - Other words are compared with character trigrams. The match threshold is 0.8.
  - The typo tolerance is limited. For example, `endpionts` does not find `endpoints`.
  - The filter does not change the graph. Only an opened document changes the graph to the subgraph view.
- **Feasibility agent panel**: The agent panel is closed by default. A narrow menu bar is at the right end of the page.
  - The button at the bottom of the menu bar (**Feasibility agent**) is the only control for the panel.
  - Click the button to open the panel. The focus moves to the message field.
  - Click the button again to close the panel. The focus stays on the button.
  - When the panel is closed, the documentation section uses the free width.
  - The browser stores the state in `localStorage`, with the key `ba-panel-agent` and the value `open` or `closed`. A missing or invalid value gives the closed state. The panel applies the stored state before the first paint.
  - For the agent context, the request, and the report export, see [Feasibility agent](#feasibility-agent).

The rendered Markdown escapes raw HTML. It removes unsafe links, for example `javascript:` links.

For the reasons behind the graph, see [ADR 0003](docs/adr/0003-build-documentation-graph-from-front-matter-relations.md).
For the reasons behind the filter, see [ADR 0004](docs/adr/0004-filter-documents-with-local-name-search.md).


## Feasibility agent

The feasibility agent assesses one change request or one feature request against the documentation files of the context. The result is a feasibility report in Markdown.

- The agent class is `Lucacodorean\BaPanel\Core\Ai\FeasibilityAgent`.
- `FeasibilityAgentFactory::make()` builds a new agent on each call. It uses the AI settings (`BAP_AI_PROVIDER`, `BAP_AI_MODEL`, `BAP_AI_KEY`) through `ProviderFactory`.
- In Laravel, the container binds `FeasibilityAgentFactory` for each resolution. Thus each resolution gets the current settings.
- The context window of the agent is 150 000 tokens.
- The agent uses only the documentation files of the request. When the files do not give a piece of information, the report says "Not specified in the documentation". The report then adds an open question.
- The agent is stateless. One request gives one report. The package has no chat persistence, no threads, no streaming, and no database migrations.

### Use the agent in the panel

1. Click a node to open a document. The agent context is then that document and all files with a direct link to it. The direction of the link is not important.
2. The panel shows each context file as a chip above the message field. Click the remove button of a chip to remove that file from the context.
3. Type the request in the message field. Press Enter to send it. Press Shift+Enter for a new line.
4. The panel shows the report in the conversation.
5. Click the `.md` export button to download the latest report. The file name is `feasibility-<report_id>.md`.

- When you open another document, the panel replaces the context with the files of that document.
- When you close the reader, the panel clears the context.
- The context can contain at most 50 files. The message can contain at most 5000 characters.
- The `.md` export button is disabled until the panel receives a report.
- The panel has no PDF export.
- The agent keeps no history. Each request is a new conversation. A follow-up request must repeat the details of the earlier request.

### Use the agent in PHP

The package classes get their services through dependency injection. Get them from the container, for example as constructor parameters of your class. The example works in Laravel and in Symfony.

`Core\Feasibility\FeasibilityService::assess()` does the full flow of the endpoint. It validates the input, builds the agent, and returns `{report, markdown, html}`.

```php
use Lucacodorean\BaPanel\Core\Feasibility\FeasibilityService;

final readonly class AssessRequest
{
    public function __construct(private FeasibilityService $feasibility)
    {
    }

    /**
     * @return array{report: array<string, mixed>, markdown: string, html: string}
     */
    public function __invoke(string $message): array
    {
        return $this->feasibility->assess($message, ['0123456789ab']);
    }
}
```

- `assess()` throws `InvalidFeasibilityRequestException` when the input is not valid. The message is safe to show to the user.
- `assess()` throws `InvalidConfigurationException` when the AI provider is not configured.
- `assess()` throws another `Throwable` when the agent fails.

For more control, use `FeasibilityAssessor::assess()`. It runs the agent and returns a `FeasibilityReport`. The files are graph nodes.

```php
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAssessor;
use Lucacodorean\BaPanel\Core\Graph\GraphBuilder;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;

final readonly class AssessWithAssessor
{
    public function __construct(
        private GraphBuilder $builder,
        private FeasibilityAgentFactory $agents,
        private FeasibilityAssessor $assessor,
        private TemplateRenderer $renderer,
    ) {
    }

    public function __invoke(string $message): string
    {
        $graph = $this->builder->build();
        $files = array_values(array_filter([$graph->node('0123456789ab')]));

        $report = $this->assessor->assess($this->agents->make(), $message, $files);

        return $report->toMarkdown($this->renderer);
    }
}
```

- `assess()` sets a new random thread id on the agent. The model does not see earlier requests.
- The user message has one block for each file, without the front matter. The request is the last block.
- `FeasibilityAgentFactory::make()` throws `InvalidConfigurationException` when the AI provider is not configured.
- `toMarkdown()` needs the `TemplateRenderer` as its argument. Neuron AI creates the report, so the report cannot get the renderer in its constructor.
- `toArray()` returns the report as an array with snake_case keys.
- `assess()` attaches the metadata to the report. `FeasibilityReport::metadata()` throws `LogicException` when no metadata is attached.
- To use another AI provider in a test, extend `FeasibilityAgentFactory`. Register your class as the `FeasibilityAgentFactory` service in the container. The class is not final.
- `FeasibilityAgentFactory` is a `readonly class`. Declare your subclass as a `readonly class` too, for example `final readonly class MyAgentFactory extends FeasibilityAgentFactory`.

### Endpoint `POST /ba-panel/feasibility`

The request and the response are JSON.

Request:

```json
{
    "message": "Add single sign-on to the client portal.",
    "context": ["0123456789ab", "ba9876543210"]
}
```

| field     | type             | rules                                                                                |
|-----------|------------------|--------------------------------------------------------------------------------------|
| `message` | string           | Required. The server trims it. At most 5000 characters.                              |
| `context` | list of strings  | Optional. Default: empty list. At most 50 items. Each item is a node id: 12 lowercase hexadecimal characters. |

- The server finds each node id only in the documentation graph. It does not read a path from the request.
- The server uses a repeated id one time. It keeps the order of the ids.
- An empty context is valid. The report then says that the documentation gives no information.

Responses:

| status | body                             | cause                                                                                  |
|--------|----------------------------------|----------------------------------------------------------------------------------------|
| `200`  | `{report, markdown, html}`       | The agent created the report.                                                          |
| `403`  | `{message}`                      | Symfony only. The CSRF check is enabled and the CSRF token is missing or not valid.    |
| `422`  | `{message}`                      | The message or the context is not valid. An id is unknown. In Symfony, the body is not a JSON object. |
| `503`  | `{message}`                      | The AI provider is not configured.                                                     |
| `502`  | `{message}`                      | The agent or the AI provider failed. The graph build or the report template failed.    |

- `report` is the report as an array with snake_case keys. `report.metadata.report_id` is the report id.
- `markdown` is the report as Markdown.
- `html` is the Markdown as HTML. The converter escapes raw HTML. It removes unsafe links.
- The `422` message tells the user what to correct.
- The `502` and `503` messages do not contain the exception details.
- Laravel logs the `502` and `503` exceptions through the exception handler. Symfony logs them through `Psr\Log\LoggerInterface`.

### CSRF protection

The panel page sends the CSRF token in a request header. Each shell gives its own header name.

| shell   | header         | rule                                                                                                   |
|---------|----------------|--------------------------------------------------------------------------------------------------------|
| Laravel | `X-CSRF-TOKEN` | The `web` middleware checks the token. The page reads the token from the session.                     |
| Symfony | `X-CSRF-Token` | The check is on when the `security.csrf.token_manager` service exists. The token id is `ba-panel`.     |

- In Symfony, install `symfony/security-csrf` and enable `framework.csrf_protection` to enable the check.
- When the Symfony check is on, a request without a valid token gets a `403` response.
- When the Symfony check is off, the page renders no token and the endpoint does no CSRF check.

### Report contents

`Lucacodorean\BaPanel\Core\Reports\FeasibilityReport` is the structured output of the agent. Its fields follow the stub `prompts/feasibility.md`.

- The report has 11 sections: metadata, request summary, scope, technical analysis, non-functional impact, effort estimation, risk assessment, technical debt, feasibility verdict, open questions, and references.
- The fixed values are backed enums in `src/Core/Reports/Enums`, for example `OverallVerdict` and `Priority`.
- PHP fills the system fields in `ReportMetadata`. The model does not see these fields. `ReportMetadataFactory::create()` creates them.
- `FeasibilityAssessor` calls `create()`. The report dates are then the current system time.
- To give a fixed time, for example in a test, call `create($now)` with a `DateTimeImmutable`.

```php
use Lucacodorean\BaPanel\Core\Reports\ReportMetadataFactory;

// $factory is a ReportMetadataFactory from the container, for example a constructor parameter.

// The current time.
$metadata = $factory->create();

// A fixed time.
$metadata = $factory->create(new DateTimeImmutable('2026-11-23T10:15:30+00:00'));
```

| field               | value                                                        |
|---------------------|--------------------------------------------------------------|
| `report_id`         | `FR-YYYYMMDD-xxxxxx`. `xxxxxx` is 6 random hexadecimal characters. |
| `report_version`    | `1.0`                                                        |
| `report_status`     | `draft`                                                      |
| `author`            | `Feasibility agent`                                          |
| `created_at`, `updated_at`, `generated_at` | The time of the report, in ISO-8601 format.    |
| `generator_name`    | `ba-panel`                                                   |
| `generator_version` | The installed package version, or `dev`.                     |

- PHP calculates the severity of each risk: probability multiplied by impact. The result is from 1 to 25.
- PHP calculates the total effort: the sum of the task estimates, in hours.

For the reasons behind the agent integration, see [ADR 0005](docs/adr/0005-integrate-feasibility-agent-through-json-endpoint-with-structured-report.md).
For the reasons behind the dependency injection, see [ADR 0006](docs/adr/0006-inject-services-through-constructors-and-methods.md).
For the reasons behind the `final` and `readonly` classes, see [ADR 0007](docs/adr/0007-declare-immutable-classes-readonly-and-leaf-classes-final.md).

## Override the templates

The package has two plain PHP templates. Both shells use them.

| template name         | package file                              | output                       |
|-----------------------|-------------------------------------------|------------------------------|
| `panel`               | `resources/views/panel.php`               | The HTML of the panel page.  |
| `reports/feasibility` | `resources/views/reports/feasibility.php` | The Markdown of the report.  |

`Core\View\TemplateRenderer` searches the template directories in order. The first directory that contains the template wins. The host directory comes before the package directory.

| shell   | host directory                       | example override                                         |
|---------|--------------------------------------|----------------------------------------------------------|
| Laravel | `resources/views/vendor/ba-panel`    | `resources/views/vendor/ba-panel/reports/feasibility.php` |
| Symfony | `templates/bundles/BaPanelBundle`    | `templates/bundles/BaPanelBundle/reports/feasibility.php` |

1. Copy the package template to the host directory. Keep the relative path and the `.php` extension. In Laravel, you can use `php artisan vendor:publish --tag=ba-panel-views`.
2. Edit the copy.

- You can override one template and keep the package version of the other template.
- A template name contains only letters, digits, `_`, `-`, and `/`. The renderer refuses `..`, absolute names, and file extensions.
- Each template gets two helpers: `$e` escapes a value for HTML, and `$js` encodes a value as JSON for a `<script>` block.
- Plain PHP does not escape a value automatically. Escape each value yourself.
- When the template fields change in a later version, update your copy.

### Panel template

- The template receives the keys of `Core\View\PanelPage::toArray()`: `graph`, `documentUrlTemplate`, `searchUrl`, `feasibilityUrl`, `csrfToken`, `csrfHeader`, `locale`, `maxMessageLength`, and `maxContextFiles`.
- Send each HTML value through `$e`. Send each script value through `$js`.
- `csrfToken` is `null` when the host has no CSRF protection.

### Report template

`FeasibilityReport::toMarkdown(TemplateRenderer $renderer)` renders the template `reports/feasibility`. The template makes Markdown, not HTML.

- The template receives `$report` (`FeasibilityReport`) and `$metadata` (`ReportMetadata`).
- Send each value through `Lucacodorean\BaPanel\Core\Reports\MarkdownText`. Use `cell()` in a table cell, `cellList()` for a list in a table cell, `inline()` on one line, `text()` for a block, and `bullets()` for a list.
- Use short echoes (`<?= ?>`). The `MarkdownText` methods prepare the value for Markdown.
- Do not use `$e` in the report template. `$e` escapes for HTML, not for Markdown.
- PHP removes the line break directly after `?>`. At the end of a line, echo `"\n"` to keep the line break, for example `<?= Md::inline($value), "\n" ?>`.

## Validation

- The package validates the configuration at boot. Laravel validates in `BaPanelServiceProvider::boot()`. Symfony validates in `BaPanelBundle::boot()`.
- The package validates the configuration only when the panel is enabled.
- When the panel is not enabled, the package does no validation. Installation and package discovery work before you set the variables.
- The value of `BAP_AI_PROVIDER` must be a supported value.
- The package checks all rules before it reports an error.
- The package throws one `Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException` with all errors.
- The exception message lists each error on a separate line.
- `InvalidConfigurationException::errors()` returns the errors as a list of strings.

Example:

```php
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;

try {
    // Boot the application.
} catch (InvalidConfigurationException $exception) {
    foreach ($exception->errors() as $error) {
        echo $error, PHP_EOL;
    }
}
```

For the reasons behind this behavior, see [ADR 0001](docs/adr/0001-validate-env-contract-at-boot.md).
For the choice of the AI provider integration, see [ADR 0002](docs/adr/0002-adopt-neuron-ai-as-agent-development-kit.md).

## Upgrade notes

This version has breaking changes for Laravel hosts.

### New namespaces

The package classes moved to the `Core` namespace and to the `Laravel` namespace. Update your `use` statements.

| old FQCN                                                        | new FQCN                                                                |
|-----------------------------------------------------------------|-------------------------------------------------------------------------|
| `Lucacodorean\BaPanel\BaPanelServiceProvider`                   | `Lucacodorean\BaPanel\Laravel\BaPanelServiceProvider`                   |
| `Lucacodorean\BaPanel\Http\Controllers\PanelController`         | `Lucacodorean\BaPanel\Laravel\Http\Controllers\PanelController`         |
| `Lucacodorean\BaPanel\Http\Controllers\FeasibilityController`   | `Lucacodorean\BaPanel\Laravel\Http\Controllers\FeasibilityController`   |
| `Lucacodorean\BaPanel\Ai\FeasibilityAgent`                      | `Lucacodorean\BaPanel\Core\Ai\FeasibilityAgent`                         |
| `Lucacodorean\BaPanel\Ai\FeasibilityAgentFactory`               | `Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory`                  |
| `Lucacodorean\BaPanel\Ai\FeasibilityAssessor`                   | `Lucacodorean\BaPanel\Core\Ai\FeasibilityAssessor`                      |
| `Lucacodorean\BaPanel\Ai\ProviderFactory`                       | `Lucacodorean\BaPanel\Core\Ai\ProviderFactory`                          |
| `Lucacodorean\BaPanel\Config\ConfigValidator`                   | `Lucacodorean\BaPanel\Core\Config\ConfigValidator`                      |
| `Lucacodorean\BaPanel\Exceptions\InvalidConfigurationException` | `Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException`    |
| `Lucacodorean\BaPanel\Exceptions\InvalidFeasibilityRequestException` | `Lucacodorean\BaPanel\Core\Exceptions\InvalidFeasibilityRequestException` |
| `Lucacodorean\BaPanel\Graph\GraphBuilder`                       | `Lucacodorean\BaPanel\Core\Graph\GraphBuilder`                          |
| `Lucacodorean\BaPanel\Reports\FeasibilityReport`                | `Lucacodorean\BaPanel\Core\Reports\FeasibilityReport`                   |
| `Lucacodorean\BaPanel\Reports\MarkdownText`                     | `Lucacodorean\BaPanel\Core\Reports\MarkdownText`                        |
| `Lucacodorean\BaPanel\Reports\ReportMetadataFactory`            | `Lucacodorean\BaPanel\Core\Reports\ReportMetadataFactory`               |

The rule for all other classes: `Lucacodorean\BaPanel\<Group>\<Class>` becomes `Lucacodorean\BaPanel\Core\<Group>\<Class>`. The groups are `Ai`, `Config`, `Exceptions`, `Graph`, `Markdown`, `Reports`, and `Search`.

### Blade views removed

- The package does not use Blade. The templates are plain PHP files with the `.php` extension.
- Published Blade views from an earlier version no longer apply. The package ignores `panel.blade.php` and `reports/feasibility.blade.php`.
- To keep your changes, publish the templates again with `php artisan vendor:publish --tag=ba-panel-views`. Copy your changes into the `.php` templates. Then delete the old `.blade.php` files.
- Replace the Blade echoes with PHP echoes. In the report template, `{!! Md::cell($value) !!}` becomes `<?= Md::cell($value) ?>`.
- `FeasibilityReport::toMarkdown()` now needs a `Lucacodorean\BaPanel\Core\View\TemplateRenderer`. It does not accept the Laravel view factory.

### Other changes

- The Laravel routes moved from `routes/web.php` to `routes/laravel.php`. The paths and the route names do not change.
- Composer no longer installs `illuminate/view`. A Laravel application already contains it.
- When the graph build or the report template fails, the feasibility endpoint now returns `502`, not `500`.
- In Laravel, the container binds `FeasibilityAgentFactory` for each resolution, not one time. A change of the AI settings applies to the next agent.
