<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Core\Config\ConfigValidator;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Core\Reports\ReportMetadataFactory;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;
use Lucacodorean\BaPanel\Tests\Support\PanelConfig;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use Lucacodorean\BaPanel\Tests\TestCase;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\OpenAI\OpenAI;

/*
| These tests resolve services from the container only. They never send a request to an AI provider.
*/

it('uses the current time for new metadata by default', function (): void {
    /** @var TestCase $this */
    $before = new DateTimeImmutable('-1 second');
    $metadata = $this->application()->make(ReportMetadataFactory::class)->create();
    $after = new DateTimeImmutable('+1 second');

    $created = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $metadata->createdAt);

    expect($created)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($created >= $before && $created <= $after)->toBeTrue();
});

it('builds a new agent factory on each resolve', function (): void {
    /** @var TestCase $this */
    $app = $this->application();

    expect($app->isShared(FeasibilityAgentFactory::class))->toBeFalse()
        ->and($app->make(FeasibilityAgentFactory::class))->not->toBe($app->make(FeasibilityAgentFactory::class));
});

it('builds new AI settings on each resolve', function (): void {
    /** @var TestCase $this */
    $app = $this->application();

    expect($app->isShared(AiSettings::class))->toBeFalse()
        ->and($app->make(AiSettings::class))->not->toBe($app->make(AiSettings::class));
});

it('reads the AI settings from the current config', function (): void {
    /** @var TestCase $this */
    $app = $this->application();
    $config = $app->make(Repository::class);

    $config->set('ba-panel.ai', ['provider' => 'openai', 'model' => 'first-model', 'key' => 'secret-key']);
    $first = $app->make(AiSettings::class);

    $config->set('ba-panel.ai.model', 'second-model');

    expect($first->toArray())->toBe(['provider' => 'openai', 'model' => 'first-model', 'key' => 'secret-key'])
        ->and($app->make(AiSettings::class)->model)->toBe('second-model');
});

it('gives empty AI settings when the AI config is not an array', function (mixed $ai): void {
    /** @var TestCase $this */
    $app = $this->application();
    $app->make(Repository::class)->set('ba-panel.ai', $ai);

    expect($app->make(AiSettings::class)->toArray())->toBe(['provider' => null, 'model' => null, 'key' => null]);
})->with([
    'null' => [null],
    'string' => ['openai'],
]);

it('builds the agent with the config that is current at resolve time', function (): void {
    /** @var TestCase $this */
    $app = $this->application();
    $config = $app->make(Repository::class);

    $config->set('ba-panel.ai', ['provider' => 'openai', 'model' => 'first-model', 'key' => 'secret-key']);
    $first = $app->make(FeasibilityAgent::class);

    $config->set('ba-panel.ai', ['provider' => 'anthropic', 'model' => 'second-model', 'key' => 'secret-key']);
    $second = $app->make(FeasibilityAgent::class);

    expect($first->getProvider())->toBeInstanceOf(OpenAI::class)
        ->and($first->getProvider()->getModel())->toBe('first-model')
        ->and($second->getProvider())->toBeInstanceOf(Anthropic::class)
        ->and($second->getProvider()->getModel())->toBe('second-model');
});

it('registers the template renderer as a singleton', function (): void {
    /** @var TestCase $this */
    $app = $this->application();

    expect($app->isShared(TemplateRenderer::class))->toBeTrue()
        ->and($app->make(TemplateRenderer::class))->toBe($app->make(TemplateRenderer::class));
});

it('resolves the package templates with the template renderer', function (): void {
    /** @var TestCase $this */
    $renderer = $this->application()->make(TemplateRenderer::class);

    expect(realpath($renderer->resolve('panel')))->toBe(realpath(dirname(__DIR__, 2).'/resources/views/panel.php'))
        ->and(realpath($renderer->resolve('reports/feasibility')))->toBe(realpath(dirname(__DIR__, 2).'/resources/views/reports/feasibility.php'));
});

it('resolves the agent through the agent factory', function (): void {
    /** @var TestCase $this */
    $fs = new TemporaryFilesystem;

    try {
        $this->withBaPanelConfig(PanelConfig::enabled($fs->directory('docs')))->rebootApplication();

        $app = $this->application();
        $agent = $app->make(FeasibilityAgent::class);

        expect($agent)->toBeInstanceOf(FeasibilityAgent::class)
            ->and($agent->getProvider())->toBeInstanceOf(OpenAI::class)
            ->and($app->resolved(FeasibilityAgentFactory::class))->toBeTrue()
            ->and($app->make(FeasibilityAgent::class))->not->toBe($agent);
    } finally {
        $fs->cleanup();
    }
});

it('throws when it resolves the agent with an empty AI config', function (): void {
    /** @var TestCase $this */
    expect(fn () => $this->application()->make(FeasibilityAgent::class))->toThrow(InvalidConfigurationException::class);
});

it('resolves the config validator with the application base path', function (): void {
    /** @var TestCase $this */
    $fs = new TemporaryFilesystem;

    try {
        $fs->directory('docs');
        $app = $this->application();
        $app->setBasePath($fs->root());

        $validator = $app->make(ConfigValidator::class);
        $ai = ['provider' => 'openai', 'model' => 'model-test', 'key' => 'secret-key'];

        // The relative path exists only under the new base path.
        $validator->validate(['ai' => $ai, 'docs' => ['docs']]);

        expect(fn () => $validator->validate(['ai' => $ai, 'docs' => ['missing']]))
            ->toThrow(InvalidConfigurationException::class);
    } finally {
        $fs->cleanup();
    }
});

it('builds a new config validator on each resolve', function (): void {
    /** @var TestCase $this */
    $app = $this->application();

    expect($app->isShared(ConfigValidator::class))->toBeFalse()
        ->and($app->make(ConfigValidator::class))->not->toBe($app->make(ConfigValidator::class));
});
