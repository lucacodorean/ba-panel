# ba-panel
A PHP package intended to create a panel for the Business analysts to access the documentation saved at project level.

## Installation

1. Install the package with Composer.

   ```bash
   composer require lucacodorean/ba-panel
   ```

2. Publish the configuration file.

   ```bash
   php artisan vendor:publish --tag=ba-panel-config
   ```

   This command creates `config/ba-panel.php` in your application.

3. Optional: publish the views.

   ```bash
   php artisan vendor:publish --tag=ba-panel-views
   ```

   This command copies the views to `resources/views/vendor/ba-panel` in your application.
   Laravel uses the published views before the package views.

Laravel package discovery registers `Lucacodorean\BaPanel\BaPanelServiceProvider`.
You do not need to register the service provider manually.

## Configuration

Set the environment variables in the `.env` file of your application.

| variable          | type   | default | required                     | description                                              |
|-------------------|--------|---------|------------------------------|----------------------------------------------------------|
| `BAP_ENABLED`     | bool   | `false` | no                           | Enables the panel. Enables the configuration validation. |
| `BAP_AI_PROVIDER` | string | none    | yes, when `BAP_ENABLED=true` | The AI provider name. See the supported values below.    |
| `BAP_AI_MODEL`    | string | none    | yes, when `BAP_ENABLED=true` | The AI model name.                                       |
| `BAP_AI_KEY`      | string | none    | yes, when `BAP_ENABLED=true` | The API key for the AI provider.                         |
| `BAP_DOCS_ARRAY`  | string | empty   | yes, when `BAP_ENABLED=true` | A comma-separated list of documentation paths.           |

The package reads `BAP_ENABLED` as a boolean. The values `true`, `1`, `yes`, and `on` enable the panel.

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
- The package resolves a relative path against the application base path.
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

## Routes

| method | URI                          | route name             | middleware | response                                             |
|--------|------------------------------|------------------------|------------|------------------------------------------------------|
| `GET`  | `/ba-panel`                  | `ba-panel`             | `web`      | The panel view `ba-panel::panel`, with the graph.    |
| `GET`  | `/ba-panel/documents/{node}` | `ba-panel.documents`   | `web`      | JSON: the rendered document and its relations.       |
| `GET`  | `/ba-panel/search?q=`        | `ba-panel.search`      | `web`      | JSON: the documents whose name matches the query.    |
| `POST` | `/ba-panel/feasibility`      | `ba-panel.feasibility` | `web`      | JSON: the feasibility report of the request.         |

- The package registers the routes only when `BAP_ENABLED` is `true`.
- When `BAP_ENABLED` is `false`, the routes do not exist. A request gets a 404 response.
- `{node}` is the node id: 12 lowercase hexadecimal characters. Another value or an unknown id gets a 404 response.
- `ba-panel.documents` returns `{id, name, title, path, html, relations: [{id, name, path}], subgraph: [id]}`.
- `relations` lists the documents that the links lead to from the opened document. These are the documents that the opened document declares. A bidirectional link counts from both ends. A document that only declares the opened document is not listed. Each document is listed once, sorted by path.
- `subgraph` lists the ids of the opened document and of its direct neighbours. A neighbour has a link with the opened document in either direction: outgoing, incoming, or bidirectional. Links are not followed further. Each document is listed once, sorted by path.
- `ba-panel.search` returns `{query, threshold, results: [{id, name, path, score}]}`.
- The search trims `q` and cuts it to 200 characters. A blank `q` returns no results.
- The panel page uses relative URLs for the document, search, and feasibility requests. The panel works behind an HTTPS reverse proxy, for example the ddev router.
- For `ba-panel.feasibility`, see [Feasibility agent](#feasibility-agent).

Use the route name to create a link:

```php
route('ba-panel');
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

- The agent class is `Lucacodorean\BaPanel\Ai\FeasibilityAgent`. It replaces `BaPanelAgent`.
- The container builds a new agent on each resolve. `FeasibilityAgentFactory::make()` builds it from the `ba-panel.ai` configuration (`BAP_AI_PROVIDER`, `BAP_AI_MODEL`, `BAP_AI_KEY`) through `ProviderFactory`.
- The context window of the agent is 150 000 tokens.
- The agent uses only the documentation files of the request. When the files do not give a piece of information, the report says "Not specified in the documentation". The report then adds an open question.

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

`FeasibilityAssessor::assess()` runs the agent and returns a `FeasibilityReport`. The files are graph nodes.

The package classes get their services through dependency injection. Get them from the container, for example as constructor parameters of your class.

```php
use Illuminate\Contracts\View\Factory as ViewFactory;
use Lucacodorean\BaPanel\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Ai\FeasibilityAssessor;
use Lucacodorean\BaPanel\Graph\GraphBuilder;

final class AssessRequest
{
    public function __construct(
        private readonly GraphBuilder $builder,
        private readonly FeasibilityAgentFactory $agents,
        private readonly FeasibilityAssessor $assessor,
        private readonly ViewFactory $views,
    ) {
    }

    public function __invoke(string $message): string
    {
        $graph = $this->builder->build();
        $files = array_values(array_filter([$graph->node('0123456789ab')]));

        $report = $this->assessor->assess($this->agents->make(), $message, $files);

        return $report->toMarkdown($this->views);
    }
}
```

- `assess()` sets a new random thread id on the agent. The model does not see earlier requests.
- The user message has one block for each file, without the front matter. The request is the last block.
- `FeasibilityAgentFactory::make()` throws `InvalidConfigurationException` when the AI provider is not configured.
- `toMarkdown()` needs the view factory as its argument. Neuron AI creates the report, so the report cannot get the view factory in its constructor.
- `toArray()` returns the report as an array with snake_case keys.
- `assess()` attaches the metadata to the report. `FeasibilityReport::metadata()` throws `LogicException` when no metadata is attached.
- To use another AI provider in a test, extend `FeasibilityAgentFactory` and bind your class in the container. The class is not final.
- `FeasibilityAgentFactory` is a `readonly class`. Declare your subclass as a `readonly class` too, for example `final readonly class MyAgentFactory extends FeasibilityAgentFactory`.

### Endpoint `POST /ba-panel/feasibility`

The request and the response are JSON. The request needs the CSRF token of the `web` middleware, for example in the `X-CSRF-TOKEN` header.

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

| status | body                             | cause                                                         |
|--------|----------------------------------|---------------------------------------------------------------|
| `200`  | `{report, markdown, html}`       | The agent created the report.                                 |
| `422`  | `{message}`                      | The message or the context is not valid. An id is unknown.    |
| `503`  | `{message}`                      | The AI provider is not configured.                            |
| `502`  | `{message}`                      | The agent or the AI provider failed.                          |

- `report` is the report as an array with snake_case keys. `report.metadata.report_id` is the report id.
- `markdown` is the report as Markdown.
- `html` is the Markdown as HTML. The converter escapes raw HTML. It removes unsafe links.
- The `422` message tells the user what to correct.
- The `502` and `503` messages do not contain the exception details. The package logs the exception with `report()`.

### Report contents

`Lucacodorean\BaPanel\Reports\FeasibilityReport` is the structured output of the agent. Its fields follow the stub `prompts/feasibility.md`.

- The report has 11 sections: metadata, request summary, scope, technical analysis, non-functional impact, effort estimation, risk assessment, technical debt, feasibility verdict, open questions, and references.
- The fixed values are backed enums in `src/Reports/Enums`, for example `OverallVerdict` and `Priority`.
- PHP fills the system fields in `ReportMetadata`. The model does not see these fields. `ReportMetadataFactory::create()` creates them.
- `FeasibilityAssessor` calls `create()`. The report dates are then the current system time.
- To give a fixed time, for example in a test, call `create($now)` with a `DateTimeImmutable`.

```php
use Lucacodorean\BaPanel\Reports\ReportMetadataFactory;

$factory = app(ReportMetadataFactory::class);

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

### Override the report view

`FeasibilityReport::toMarkdown(ViewFactory $views)` renders the Blade view `ba-panel::reports.feasibility`. The view makes Markdown, not HTML.

1. Publish the views.

   ```bash
   php artisan vendor:publish --tag=ba-panel-views
   ```

2. Edit `resources/views/vendor/ba-panel/reports/feasibility.blade.php` in your application.

- The view receives `$report` (`FeasibilityReport`) and `$metadata` (`ReportMetadata`).
- Send each value through `Lucacodorean\BaPanel\Reports\MarkdownText`. Use `cell()` in a table cell, `cellList()` for a list in a table cell, `inline()` on one line, `text()` for a block, and `bullets()` for a list.
- Use raw echoes (`{!! !!}`). The `MarkdownText` methods prepare the value for Markdown.
- When the report fields change in a later version, update your published view.

For the reasons behind the agent integration, see [ADR 0005](docs/adr/0005-integrate-feasibility-agent-through-json-endpoint-with-structured-report.md).
For the reasons behind the dependency injection, see [ADR 0006](docs/adr/0006-inject-services-through-constructors-and-methods.md).
For the reasons behind the `final` and `readonly` classes, see [ADR 0007](docs/adr/0007-declare-immutable-classes-readonly-and-leaf-classes-final.md).

## Validation

- The package validates the configuration at boot.
- The package validates the configuration only when `BAP_ENABLED` is `true`.
- When `BAP_ENABLED` is `false`, the package does no validation. Installation and package discovery work before you set the variables.
- The value of `BAP_AI_PROVIDER` must be a supported value.
- The package checks all rules before it reports an error.
- The package throws one `Lucacodorean\BaPanel\Exceptions\InvalidConfigurationException` with all errors.
- The exception message lists each error on a separate line.
- `InvalidConfigurationException::errors()` returns the errors as a list of strings.

Example:

```php
use Lucacodorean\BaPanel\Exceptions\InvalidConfigurationException;

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
