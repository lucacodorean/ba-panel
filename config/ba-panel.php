<?php

declare(strict_types=1);

return [

    /*
    | Enable the panel. The configuration is validated only when this value is true.
    */
    'enabled' => filter_var(env('BAP_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | The AI provider settings. The provider, the model and the key are required when the panel is enabled.
    | The instructions path is the prompt file of the feasibility agent. The panel selects the prompt file in this order:
    | 1. The instructions path, when it is set. A missing file is an error.
    | 2. The published prompt file "resources/prompts/vendor/ba-panel/feasibility.md", when it exists.
    |    Publish the prompt file with the "ba-panel-prompts" tag.
    | 3. The package prompt file.
    | A relative path resolves against the base path of the application. An absolute path is used as given.
    */
    'ai' => [
        'provider' => env('BAP_AI_PROVIDER'),
        'model' => env('BAP_AI_MODEL'),
        'key' => env('BAP_AI_KEY'),
        'instructions_path' => env('BAP_AI_INSTRUCTIONS_PATH'),
    ],

    /*
    | The documentation paths, as a comma-separated string.
    | Relative paths are resolved against the application base path.
    */
    'docs' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('BAP_DOCS_ARRAY', ''))),
        static fn (string $path): bool => $path !== '',
    )),

];
