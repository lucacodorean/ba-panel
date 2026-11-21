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

Laravel package discovery registers `Lucacodorean\BaPanel\BaPanelServiceProvider`.
You do not need to register the service provider manually.

## Configuration

Set the environment variables in the `.env` file of your application.

| variable          | type   | default | required                     | description                                              |
|-------------------|--------|---------|------------------------------|----------------------------------------------------------|
| `BAP_ENABLED`     | bool   | `false` | no                           | Enables the panel. Enables the configuration validation. |
| `BAP_AI_PROVIDER` | string | none    | yes, when `BAP_ENABLED=true` | The AI provider name.                                    |
| `BAP_AI_MODEL`    | string | none    | yes, when `BAP_ENABLED=true` | The AI model name.                                       |
| `BAP_AI_KEY`      | string | none    | yes, when `BAP_ENABLED=true` | The API key for the AI provider.                         |
| `BAP_DOCS_ARRAY`  | string | empty   | yes, when `BAP_ENABLED=true` | A comma-separated list of documentation paths.           |

The package reads `BAP_ENABLED` as a boolean. The values `true`, `1`, `yes`, and `on` enable the panel.

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

## Validation

- The package validates the configuration at boot.
- The package validates the configuration only when `BAP_ENABLED` is `true`.
- When `BAP_ENABLED` is `false`, the package does no validation. Installation and package discovery work before you set the variables.
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
