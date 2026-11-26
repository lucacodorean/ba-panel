<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Ai;

use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\XAI\Grok;

final readonly class ProviderFactory
{
    public const string ANTHROPIC = 'anthropic';

    public const string OPENAI = 'openai';

    public const string GEMINI = 'gemini';

    public const string MISTRAL = 'mistral';

    public const string DEEPSEEK = 'deepseek';

    public const string XAI = 'xai';

    /**
     * The supported provider names. This list is the single source of truth.
     *
     * @var list<string>
     */
    public const array SUPPORTED_PROVIDERS = [
        self::ANTHROPIC,
        self::OPENAI,
        self::GEMINI,
        self::MISTRAL,
        self::DEEPSEEK,
        self::XAI,
    ];

    private const string CONFIG_PROVIDER = 'provider';

    private const string CONFIG_MODEL = 'model';

    private const string CONFIG_KEY = 'key';

    /**
     * Map each AI config key to its environment variable name.
     *
     * @var array<string, string>
     */
    private const array ENV_NAMES = [
        self::CONFIG_PROVIDER => 'BAP_AI_PROVIDER',
        self::CONFIG_MODEL => 'BAP_AI_MODEL',
        self::CONFIG_KEY => 'BAP_AI_KEY',
    ];

    /**
     * Template for an unsupported provider. The arguments are the given value and the supported values.
     */
    private const string MESSAGE_UNSUPPORTED = 'BAP_AI_PROVIDER value is not supported: "%s". Use one of these values: %s.';

    /**
     * Template for a missing AI value. The argument is the environment variable name.
     */
    private const string MESSAGE_MISSING = '%s is required to build the AI provider.';

    private const string LIST_SEPARATOR = ', ';

    /**
     * Return true when the provider name is supported. The check ignores case and outer whitespace.
     */
    public static function supports(string $provider): bool
    {
        return in_array(self::normalize($provider), self::SUPPORTED_PROVIDERS, true);
    }

    /**
     * Return the error message for an unsupported provider name.
     */
    public static function unsupportedMessage(string $provider): string
    {
        return sprintf(
            self::MESSAGE_UNSUPPORTED,
            $provider,
            implode(self::LIST_SEPARATOR, self::SUPPORTED_PROVIDERS),
        );
    }

    /**
     * Build the provider from the AI config array, for example AiSettings::toArray().
     *
     * Expected shape: array{provider?: ?string, model?: ?string, key?: ?string}.
     *
     * @param  array<mixed>  $ai
     *
     * @throws InvalidConfigurationException
     */
    public function fromConfig(array $ai): AIProviderInterface
    {
        $values = [];
        $errors = [];

        foreach (self::ENV_NAMES as $configKey => $envName) {
            $value = $ai[$configKey] ?? null;

            if (! is_string($value) || trim($value) === '') {
                $errors[] = sprintf(self::MESSAGE_MISSING, $envName);

                continue;
            }

            $values[$configKey] = $value;
        }

        if ($errors !== []) {
            throw new InvalidConfigurationException($errors);
        }

        return $this->make(
            $values[self::CONFIG_PROVIDER],
            $values[self::CONFIG_MODEL],
            $values[self::CONFIG_KEY],
        );
    }

    /**
     * Build the provider. The provider name ignores case and outer whitespace.
     *
     * @throws InvalidConfigurationException
     */
    public function make(string $provider, string $model, string $key): AIProviderInterface
    {
        // Use named arguments. The argument order is different between providers.
        return match (self::normalize($provider)) {
            self::ANTHROPIC => new Anthropic(key: $key, model: $model),
            self::OPENAI => new OpenAI(key: $key, model: $model),
            self::GEMINI => new Gemini(key: $key, model: $model),
            self::MISTRAL => new Mistral(key: $key, model: $model),
            self::DEEPSEEK => new Deepseek(key: $key, model: $model),
            self::XAI => new Grok(key: $key, model: $model),
            default => throw new InvalidConfigurationException([self::unsupportedMessage($provider)]),
        };
    }

    private static function normalize(string $provider): string
    {
        return strtolower(trim($provider));
    }
}
