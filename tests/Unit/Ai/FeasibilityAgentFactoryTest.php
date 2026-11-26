<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Ai\PromptLoader;
use Lucacodorean\BaPanel\Core\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\OpenAI\OpenAI;

/*
| These tests only build the agent. They never send a request to an AI provider.
*/

/**
 * Return the package root. The prompt loader resolves a relative path against this directory.
 */
function feasibilityAgentFactoryRoot(): string
{
    return dirname(__DIR__, 3);
}

/**
 * Build the factory with the given settings. The prompt loader uses the given base path or the package root.
 */
function feasibilityAgentFactoryWith(AiSettings $settings, ?string $basePath = null): FeasibilityAgentFactory
{
    return new FeasibilityAgentFactory(new ProviderFactory, $settings, new PromptLoader($basePath ?? feasibilityAgentFactoryRoot()));
}

/**
 * Build the factory with the AI settings of the given "ai" config value. A value that is not an array gives empty settings.
 */
function feasibilityAgentFactory(mixed $ai): FeasibilityAgentFactory
{
    return feasibilityAgentFactoryWith(AiSettings::fromArray(is_array($ai) ? $ai : []));
}

/**
 * Return the text of the first instruction block of the agent. This block is the persona.
 */
function feasibilityAgentFactoryPersona(FeasibilityAgent $agent): string
{
    $block = $agent->getInstructions()->getContentBlocks()[0] ?? null;

    expect($block)->toBeInstanceOf(TextContent::class);

    /** @var TextContent $block */
    return $block->content;
}

/**
 * Return the AI config of a valid provider with the given prompt path.
 *
 * @return array<string, mixed>
 */
function feasibilityAgentFactoryAi(mixed $instructionsPath): array
{
    return ['provider' => 'openai', 'model' => 'model-test', 'key' => 'secret-key', 'instructions_path' => $instructionsPath];
}

/**
 * Run make() and return the collected errors. Return null when no exception occurs.
 *
 * @return list<string>|null
 */
function feasibilityAgentFactoryErrors(FeasibilityAgentFactory $factory): ?array
{
    try {
        $factory->make();
    } catch (InvalidConfigurationException $exception) {
        return $exception->errors();
    }

    return null;
}

it('builds an agent with the provider and the model of the config', function (string $provider, string $class): void {
    $agent = feasibilityAgentFactory(['provider' => $provider, 'model' => 'model-test', 'key' => 'secret-key'])->make();

    expect($agent)->toBeInstanceOf(FeasibilityAgent::class)
        ->and($agent->getProvider())->toBeInstanceOf($class)
        ->and($agent->getProvider()->getModel())->toBe('model-test');
})->with([
    'openai' => ['openai', OpenAI::class],
    'anthropic' => ['anthropic', Anthropic::class],
]);

it('builds a new agent on each call', function (): void {
    $factory = feasibilityAgentFactory(['provider' => 'openai', 'model' => 'model-test', 'key' => 'secret-key']);

    expect($factory->make())->not->toBe($factory->make());
});

it('builds each agent from the given settings', function (): void {
    $factory = feasibilityAgentFactoryWith(new AiSettings('openai', 'first-model', 'secret-key'));

    expect($factory->make()->getProvider()->getModel())->toBe('first-model')
        ->and($factory->make()->getProvider()->getModel())->toBe('first-model');
});

it('builds the agent with the settings of each factory', function (): void {
    $first = feasibilityAgentFactoryWith(new AiSettings('openai', 'first-model', 'secret-key'));
    $second = feasibilityAgentFactoryWith(new AiSettings('anthropic', 'second-model', 'secret-key'));

    expect($first->make()->getProvider())->toBeInstanceOf(OpenAI::class)
        ->and($second->make()->getProvider())->toBeInstanceOf(Anthropic::class)
        ->and($second->make()->getProvider()->getModel())->toBe('second-model');
});

it('throws with every missing AI value when the settings are empty', function (): void {
    expect(feasibilityAgentFactoryErrors(feasibilityAgentFactoryWith(new AiSettings)))->toBe([
        'BAP_AI_PROVIDER is required to build the AI provider.',
        'BAP_AI_MODEL is required to build the AI provider.',
        'BAP_AI_KEY is required to build the AI provider.',
    ]);
});

it('throws with every missing AI value', function (mixed $ai): void {
    expect(feasibilityAgentFactoryErrors(feasibilityAgentFactory($ai)))->toBe([
        'BAP_AI_PROVIDER is required to build the AI provider.',
        'BAP_AI_MODEL is required to build the AI provider.',
        'BAP_AI_KEY is required to build the AI provider.',
    ]);
})->with([
    'null values' => [['provider' => null, 'model' => null, 'key' => null]],
    'empty array' => [[]],
    'missing key' => [null],
    'not an array' => ['openai'],
    'values that are not strings' => [['provider' => 1, 'model' => ['gpt'], 'key' => true]],
]);

it('throws when one AI value is blank', function (): void {
    expect(feasibilityAgentFactoryErrors(feasibilityAgentFactory(['provider' => 'openai', 'model' => 'model-test', 'key' => '  '])))
        ->toBe(['BAP_AI_KEY is required to build the AI provider.']);
});

it('throws when the provider is not supported', function (): void {
    expect(fn () => feasibilityAgentFactory(['provider' => 'unknown-provider', 'model' => 'model-test', 'key' => 'secret-key'])->make())
        ->toThrow(InvalidConfigurationException::class);
});

it('builds the agent with the package prompt when no path is configured', function (mixed $instructionsPath): void {
    $agent = feasibilityAgentFactory(feasibilityAgentFactoryAi($instructionsPath))->make();
    $package = trim((string) file_get_contents(feasibilityAgentFactoryRoot().'/resources/prompts/feasibility.md'));

    expect(feasibilityAgentFactoryPersona($agent))->toBe($package);
})->with([
    'null' => [null],
    'empty' => [''],
    'blank' => ['   '],
    'not a string' => [1],
]);

it('builds the agent with the package prompt when the key is missing', function (): void {
    $agent = feasibilityAgentFactory(['provider' => 'openai', 'model' => 'model-test', 'key' => 'secret-key'])->make();
    $package = trim((string) file_get_contents(feasibilityAgentFactoryRoot().'/resources/prompts/feasibility.md'));

    expect(feasibilityAgentFactoryPersona($agent))->toBe($package);
});

it('builds the agent with the configured absolute prompt path', function (): void {
    $fs = new TemporaryFilesystem;

    try {
        $path = $fs->file('prompts/custom.md', "\n  You are a custom persona.  \n");
        $agent = feasibilityAgentFactory(feasibilityAgentFactoryAi($path))->make();

        expect(feasibilityAgentFactoryPersona($agent))->toBe('You are a custom persona.');
    } finally {
        $fs->cleanup();
    }
});

it('resolves a configured relative prompt path against the base path', function (): void {
    $fs = new TemporaryFilesystem;

    try {
        $fs->file('prompts/custom.md', 'You are a relative persona.');
        $agent = feasibilityAgentFactoryWith(AiSettings::fromArray(feasibilityAgentFactoryAi('prompts/custom.md')), $fs->root())->make();

        expect(feasibilityAgentFactoryPersona($agent))->toBe('You are a relative persona.');
    } finally {
        $fs->cleanup();
    }
});

it('builds the agent with the published prompt when no path is configured', function (): void {
    $fs = new TemporaryFilesystem;

    try {
        $published = $fs->file('published/feasibility.md', 'You are the published persona.');
        $factory = new FeasibilityAgentFactory(
            new ProviderFactory,
            AiSettings::fromArray(feasibilityAgentFactoryAi(null)),
            new PromptLoader($fs->root(), $published),
        );

        expect(feasibilityAgentFactoryPersona($factory->make()))->toBe('You are the published persona.');
    } finally {
        $fs->cleanup();
    }
});

it('throws for a missing configured path when a published prompt exists', function (): void {
    $fs = new TemporaryFilesystem;

    try {
        $published = $fs->file('published/feasibility.md', 'You are the published persona.');
        $factory = new FeasibilityAgentFactory(
            new ProviderFactory,
            AiSettings::fromArray(feasibilityAgentFactoryAi('prompts/missing.md')),
            new PromptLoader($fs->root(), $published),
        );

        expect(feasibilityAgentFactoryErrors($factory))
            ->toBe(['The prompt file does not exist: '.$fs->root().DIRECTORY_SEPARATOR.'prompts/missing.md']);
    } finally {
        $fs->cleanup();
    }
});

it('reads the prompt file again on each call', function (): void {
    $fs = new TemporaryFilesystem;

    try {
        $path = $fs->file('prompts/custom.md', 'First persona.');
        $factory = feasibilityAgentFactory(feasibilityAgentFactoryAi($path));
        $first = $factory->make();

        file_put_contents($path, 'Second persona.');

        expect(feasibilityAgentFactoryPersona($first))->toBe('First persona.')
            ->and(feasibilityAgentFactoryPersona($factory->make()))->toBe('Second persona.');
    } finally {
        $fs->cleanup();
    }
});

it('throws when the configured prompt file does not exist', function (): void {
    $fs = new TemporaryFilesystem;

    try {
        $path = $fs->path('missing.md');

        expect(feasibilityAgentFactoryErrors(feasibilityAgentFactory(feasibilityAgentFactoryAi($path))))
            ->toBe(['The prompt file does not exist: '.$path]);
    } finally {
        $fs->cleanup();
    }
});

it('throws when the configured prompt file is empty', function (): void {
    $fs = new TemporaryFilesystem;

    try {
        $path = $fs->file('empty.md', " \n\t ");

        expect(feasibilityAgentFactoryErrors(feasibilityAgentFactory(feasibilityAgentFactoryAi($path))))
            ->toBe(['The prompt file is empty: '.$path]);
    } finally {
        $fs->cleanup();
    }
});

it('checks the provider before it loads the prompt file', function (): void {
    // Both values are not valid. The provider error comes first, so the prompt error does not show.
    $settings = new AiSettings(instructionsPath: '/ba-panel-tests/missing-prompt.md');

    expect(feasibilityAgentFactoryErrors(feasibilityAgentFactoryWith($settings)))->toBe([
        'BAP_AI_PROVIDER is required to build the AI provider.',
        'BAP_AI_MODEL is required to build the AI provider.',
        'BAP_AI_KEY is required to build the AI provider.',
    ]);
});

it('is not final, so a host or a test can extend it', function (): void {
    expect((new ReflectionClass(FeasibilityAgentFactory::class))->isFinal())->toBeFalse();
});
