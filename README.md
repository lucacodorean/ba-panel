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

| method | URI                          | route name           | middleware | response                                             |
|--------|------------------------------|----------------------|------------|------------------------------------------------------|
| `GET`  | `/ba-panel`                  | `ba-panel`           | `web`      | The panel view `ba-panel::panel`, with the graph.    |
| `GET`  | `/ba-panel/documents/{node}` | `ba-panel.documents` | `web`      | JSON: the rendered document and its relations.       |
| `GET`  | `/ba-panel/search?q=`        | `ba-panel.search`    | `web`      | JSON: the documents whose name matches the query.    |

- The package registers the routes only when `BAP_ENABLED` is `true`.
- When `BAP_ENABLED` is `false`, the routes do not exist. A request gets a 404 response.
- `{node}` is the node id: 12 lowercase hexadecimal characters. Another value or an unknown id gets a 404 response.
- `ba-panel.documents` returns `{id, name, title, path, html, relations: [{id, name, path}], subgraph: [id]}`.
- `relations` lists the documents that the links lead to from the opened document. These are the documents that the opened document declares. A bidirectional link counts from both ends. A document that only declares the opened document is not listed. Each document is listed once, sorted by path.
- `subgraph` lists the ids of the opened document and of its direct neighbours. A neighbour has a link with the opened document in either direction: outgoing, incoming, or bidirectional. Links are not followed further. Each document is listed once, sorted by path.
- `ba-panel.search` returns `{query, threshold, results: [{id, name, path, score}]}`.
- The search trims `q` and cuts it to 200 characters. A blank `q` returns no results.
- The panel page uses relative URLs for the document and search requests. The panel works behind an HTTPS reverse proxy, for example the ddev router.

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

The rendered Markdown escapes raw HTML. It removes unsafe links, for example `javascript:` links.

For the reasons behind the graph, see [ADR 0003](docs/adr/0003-build-documentation-graph-from-front-matter-relations.md).
For the reasons behind the filter, see [ADR 0004](docs/adr/0004-filter-documents-with-local-name-search.md).

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
