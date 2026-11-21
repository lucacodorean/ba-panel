<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Tests\Support;

/**
 * Build ba-panel config arrays for feature tests.
 */
final class PanelConfig
{
    /**
     * Return a valid enabled config. The AI overrides replace the default AI values.
     *
     * @param  array<string, mixed>  $ai
     * @return array<string, mixed>
     */
    public static function enabled(string $docsPath, array $ai = []): array
    {
        return [
            'enabled' => true,
            'ai' => array_replace(
                ['provider' => 'openai', 'model' => 'gpt-test', 'key' => 'secret-key'],
                $ai,
            ),
            'docs' => [$docsPath],
        ];
    }

    /**
     * Return a disabled config with empty values.
     *
     * @return array<string, mixed>
     */
    public static function disabled(): array
    {
        return [
            'enabled' => false,
            'ai' => ['provider' => null, 'model' => null, 'key' => null],
            'docs' => [],
        ];
    }
}
