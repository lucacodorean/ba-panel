<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Config;

use Lucacodorean\BaPanel\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Exceptions\InvalidConfigurationException;

final class ConfigValidator
{
    private const string CONFIG_AI = 'ai';

    private const string CONFIG_PROVIDER = 'provider';

    private const string CONFIG_MODEL = 'model';

    private const string CONFIG_KEY = 'key';

    private const string CONFIG_DOCS = 'docs';

    private const string ENV_AI_PROVIDER = 'BAP_AI_PROVIDER';

    private const string ENV_AI_MODEL = 'BAP_AI_MODEL';

    private const string ENV_AI_KEY = 'BAP_AI_KEY';

    private const string ENV_DOCS_ARRAY = 'BAP_DOCS_ARRAY';

    /**
     * Template for a missing AI value. The argument is the environment variable name.
     */
    private const string MESSAGE_REQUIRED = '%s is required when BAP_ENABLED is true.';

    /**
     * Template for an empty docs list. The argument is the environment variable name.
     */
    private const string MESSAGE_DOCS_EMPTY = '%s must contain at least one path when BAP_ENABLED is true.';

    /**
     * Template for a missing docs path. The arguments are the environment variable name and the path.
     */
    private const string MESSAGE_PATH_MISSING = '%s path does not exist: %s';

    /**
     * Match a Windows absolute path, for example "C:\" or "C:/".
     */
    private const string WINDOWS_ABSOLUTE_PATH_PATTERN = '/^[A-Za-z]:[\\\\\/]/';

    private const string UNIX_ROOT = '/';

    private const string TRAILING_SEPARATORS = '/\\';

    /**
     * Map each required AI key to its environment variable name.
     *
     * @var array<string, string>
     */
    private const array AI_KEYS = [
        self::CONFIG_PROVIDER => self::ENV_AI_PROVIDER,
        self::CONFIG_MODEL => self::ENV_AI_MODEL,
        self::CONFIG_KEY => self::ENV_AI_KEY,
    ];

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * Validate the configuration. Collect all errors, then throw one exception.
     *
     * Expected shape: array{enabled?: bool, ai?: array{provider?: ?string, model?: ?string, key?: ?string}, docs?: list<string>}.
     * Values are checked at runtime, so the type is kept loose.
     *
     * @param array<string, mixed> $config
     *
     * @throws InvalidConfigurationException
     */
    public function validate(array $config): void
    {
        $errors = [
            ...$this->validateAi($config[self::CONFIG_AI] ?? []),
            ...$this->validateDocs($config[self::CONFIG_DOCS] ?? []),
        ];

        if ($errors !== []) {
            throw new InvalidConfigurationException($errors);
        }
    }

    /**
     * @return list<string>
     */
    private function validateAi(mixed $ai): array
    {
        $ai = is_array($ai) ? $ai : [];
        $errors = [];

        foreach (self::AI_KEYS as $key => $envName) {
            $value = $ai[$key] ?? null;

            if (! is_string($value) || trim($value) === '') {
                $errors[] = sprintf(self::MESSAGE_REQUIRED, $envName);
            }
        }

        // Check the provider name only when it is given. A missing value already has an error.
        $provider = $ai[self::CONFIG_PROVIDER] ?? null;

        if (is_string($provider) && trim($provider) !== '' && ! ProviderFactory::supports($provider)) {
            $errors[] = ProviderFactory::unsupportedMessage($provider);
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateDocs(mixed $docs): array
    {
        $docs = is_array($docs) ? array_values($docs) : [];

        if ($docs === []) {
            return [sprintf(self::MESSAGE_DOCS_EMPTY, self::ENV_DOCS_ARRAY)];
        }

        $errors = [];

        foreach ($docs as $path) {
            $path = is_scalar($path) ? (string) $path : '';

            if (! file_exists($this->resolve($path))) {
                $errors[] = sprintf(self::MESSAGE_PATH_MISSING, self::ENV_DOCS_ARRAY, $path);
            }
        }

        return $errors;
    }

    /**
     * Resolve a relative path against the base path. Keep an absolute path as given.
     */
    private function resolve(string $path): string
    {
        if (str_starts_with($path, self::UNIX_ROOT) || preg_match(self::WINDOWS_ABSOLUTE_PATH_PATTERN, $path) === 1) {
            return $path;
        }

        return rtrim($this->basePath, self::TRAILING_SEPARATORS).DIRECTORY_SEPARATOR.$path;
    }
}
