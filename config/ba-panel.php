<?php

declare(strict_types=1);

return [

    /*
    | Enable the panel. The configuration is validated only when this value is true.
    */
    'enabled' => filter_var(env('BAP_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | The AI provider settings. Each value is required when the panel is enabled.
    */
    'ai' => [
        'provider' => env('BAP_AI_PROVIDER'),
        'model' => env('BAP_AI_MODEL'),
        'key' => env('BAP_AI_KEY'),
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
