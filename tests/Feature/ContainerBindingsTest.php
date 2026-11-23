<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Config\ConfigValidator;
use Lucacodorean\BaPanel\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Reports\ReportMetadataFactory;
use Lucacodorean\BaPanel\Tests\Support\PanelConfig;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use Lucacodorean\BaPanel\Tests\TestCase;
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

it('registers the agent factory as a singleton', function (): void {
    /** @var TestCase $this */
    $app = $this->application();

    expect($app->isShared(FeasibilityAgentFactory::class))->toBeTrue()
        ->and($app->make(FeasibilityAgentFactory::class))->toBe($app->make(FeasibilityAgentFactory::class));
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
