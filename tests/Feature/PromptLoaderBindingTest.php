<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\File;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Core\Ai\PromptLoader;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Tests\Support\PanelConfig;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use Lucacodorean\BaPanel\Tests\TestCase;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;

/*
| These tests resolve the prompt loader and the agent from the container only. They never send a request to an AI provider.
| The published prompt directory is removed after each test.
*/

/**
 * Return the published prompt directory of the Laravel application.
 */
function laravelPublishedPromptDirectory(): string
{
    return resource_path('prompts/vendor/ba-panel');
}

/**
 * Return the persona block of the agent. This is the first instruction block.
 */
function laravelAgentPersona(FeasibilityAgent $agent): string
{
    $block = $agent->getInstructions()->getContentBlocks()[0] ?? null;

    expect($block)->toBeInstanceOf(TextContent::class);

    /** @var TextContent $block */
    return $block->content;
}

/**
 * Return the trimmed text of the package prompt file.
 */
function laravelPackagePrompt(): string
{
    return trim((string) file_get_contents(dirname(__DIR__, 2).'/resources/prompts/feasibility.md'));
}

/**
 * Boot an enabled panel with the given AI overrides. Return the temporary filesystem of the docs.
 *
 * @param  array<string, mixed>  $ai
 */
function laravelEnabledPanel(TestCase $test, array $ai = []): TemporaryFilesystem
{
    $fs = new TemporaryFilesystem;
    $test->withBaPanelConfig(PanelConfig::enabled($fs->directory('docs'), $ai))->rebootApplication();

    return $fs;
}

afterEach(function (): void {
    File::deleteDirectory(laravelPublishedPromptDirectory());
});

it('registers the prompt loader as a singleton', function (): void {
    /** @var TestCase $this */
    $app = $this->application();

    expect($app->isShared(PromptLoader::class))->toBeTrue()
        ->and($app->make(PromptLoader::class))->toBeInstanceOf(PromptLoader::class)
        ->and($app->make(PromptLoader::class))->toBe($app->make(PromptLoader::class));
});

it('has no prompt path in the merged config by default', function (): void {
    /** @var TestCase $this */
    expect($this->application()->make(Repository::class)->get('ba-panel.ai.instructions_path'))->toBeNull();
});

it('loads the package prompt when no prompt is published or configured', function (): void {
    /** @var TestCase $this */
    expect($this->application()->make(PromptLoader::class)->load())->toBe(laravelPackagePrompt());
});

it('builds the agent with the package prompt by default', function (): void {
    /** @var TestCase $this */
    $fs = laravelEnabledPanel($this);

    try {
        expect(laravelAgentPersona($this->application()->make(FeasibilityAgent::class)))->toBe(laravelPackagePrompt());
    } finally {
        $fs->cleanup();
    }
});

it('builds the agent with the published prompt copy', function (): void {
    /** @var TestCase $this */
    $fs = laravelEnabledPanel($this);

    try {
        $this->artisan('vendor:publish', ['--tag' => 'ba-panel-prompts', '--force' => true])->assertSuccessful();
        file_put_contents(laravelPublishedPromptDirectory().'/feasibility.md', "  You are the published host persona.\n");

        expect($this->application()->make(PromptLoader::class)->load())->toBe('You are the published host persona.')
            ->and(laravelAgentPersona($this->application()->make(FeasibilityAgent::class)))->toBe('You are the published host persona.');
    } finally {
        $fs->cleanup();
    }
});

it('uses the configured path before the published prompt copy', function (): void {
    /** @var TestCase $this */
    $fs = new TemporaryFilesystem;

    try {
        $configured = $fs->file('prompts/configured.md', 'You are the configured persona.');
        $this->withBaPanelConfig(PanelConfig::enabled($fs->directory('docs'), ['instructions_path' => $configured]))->rebootApplication();

        File::ensureDirectoryExists(laravelPublishedPromptDirectory());
        file_put_contents(laravelPublishedPromptDirectory().'/feasibility.md', 'You are the published host persona.');

        expect(laravelAgentPersona($this->application()->make(FeasibilityAgent::class)))->toBe('You are the configured persona.');
    } finally {
        $fs->cleanup();
    }
});

it('resolves a relative configured path against the application base path', function (): void {
    /** @var TestCase $this */
    $relative = 'ba-panel-test-prompts/relative.md';
    $fs = laravelEnabledPanel($this, ['instructions_path' => $relative]);

    try {
        File::ensureDirectoryExists(base_path('ba-panel-test-prompts'));
        file_put_contents(base_path($relative), 'You are the relative persona.');

        expect(laravelAgentPersona($this->application()->make(FeasibilityAgent::class)))->toBe('You are the relative persona.');
    } finally {
        File::deleteDirectory(base_path('ba-panel-test-prompts'));
        $fs->cleanup();
    }
});

it('throws for a missing configured path and does not use the published copy', function (): void {
    /** @var TestCase $this */
    $fs = laravelEnabledPanel($this, ['instructions_path' => 'ba-panel-test-prompts/missing.md']);

    try {
        File::ensureDirectoryExists(laravelPublishedPromptDirectory());
        file_put_contents(laravelPublishedPromptDirectory().'/feasibility.md', 'You are the published host persona.');

        $this->application()->make(FeasibilityAgent::class);
        throw new LogicException('Expected InvalidConfigurationException.');
    } catch (InvalidConfigurationException $exception) {
        expect($exception->errors())->toBe(['The prompt file does not exist: '.base_path().DIRECTORY_SEPARATOR.'ba-panel-test-prompts/missing.md']);
    } finally {
        $fs->cleanup();
    }
});
