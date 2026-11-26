<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Config;

/**
 * The AI provider settings of the panel. A value is null when the host does not set it.
 * ProviderFactory checks the values when it builds the provider.
 */
final readonly class AiSettings
{
    private const string PROVIDER = 'provider';

    private const string MODEL = 'model';

    private const string KEY = 'key';

    private const string INSTRUCTIONS_PATH = 'instructions_path';

    /**
     * The default prompt file of the feasibility agent. The file is in the package. PromptLoader uses it as the last step.
     */
    public const string DEFAULT_INSTRUCTIONS_PATH = __DIR__.'/../../../resources/prompts/feasibility.md';

    public function __construct(
        public ?string $provider = null,
        public ?string $model = null,
        public ?string $key = null,
        public ?string $instructionsPath = null,
    ) {}

    /**
     * Build the settings from the "ai" config array. A value that is not a string becomes null.
     *
     * Expected shape: array{provider?: ?string, model?: ?string, key?: ?string, instructions_path?: ?string}.
     *
     * @param  array<mixed>  $ai
     */
    public static function fromArray(array $ai): self
    {
        return new self(
            self::stringOrNull($ai[self::PROVIDER] ?? null),
            self::stringOrNull($ai[self::MODEL] ?? null),
            self::stringOrNull($ai[self::KEY] ?? null),
            self::stringOrNull($ai[self::INSTRUCTIONS_PATH] ?? null),
        );
    }

    /**
     * Return the configured path of the prompt file. A null or blank value gives null.
     * PromptLoader selects the published or the default prompt file when the value is null.
     */
    public function instructionsPath(): ?string
    {
        $path = $this->instructionsPath;

        return $path === null || trim($path) === '' ? null : $path;
    }

    /**
     * Return the provider settings in the shape of the "ai" config array. ProviderFactory reads this array.
     * The array does not contain the prompt path. Use instructionsPath() for the prompt path.
     *
     * @return array{provider: ?string, model: ?string, key: ?string}
     */
    public function toArray(): array
    {
        return [
            self::PROVIDER => $this->provider,
            self::MODEL => $this->model,
            self::KEY => $this->key,
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
