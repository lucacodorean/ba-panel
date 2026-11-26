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

    public function __construct(
        public ?string $provider = null,
        public ?string $model = null,
        public ?string $key = null,
    ) {
    }

    /**
     * Build the settings from the "ai" config array. A value that is not a string becomes null.
     *
     * Expected shape: array{provider?: ?string, model?: ?string, key?: ?string}.
     *
     * @param array<mixed> $ai
     */
    public static function fromArray(array $ai): self
    {
        return new self(
            self::stringOrNull($ai[self::PROVIDER] ?? null),
            self::stringOrNull($ai[self::MODEL] ?? null),
            self::stringOrNull($ai[self::KEY] ?? null),
        );
    }

    /**
     * Return the settings in the shape of the "ai" config array.
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
