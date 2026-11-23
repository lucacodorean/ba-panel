<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\BaPanelServiceProvider;
use Lucacodorean\BaPanel\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Exceptions\InvalidFeasibilityRequestException;
use Lucacodorean\BaPanel\Reports\FeasibilityReport;

/*
| These rules apply to the package classes in src/. The test classes in the Tests namespace are not part of the rules.
*/

/**
 * The package namespace. It does not include the Tests namespace.
 */
const ARCH_PACKAGE = 'Lucacodorean\\BaPanel';

const ARCH_TESTS = 'Lucacodorean\\BaPanel\\Tests';

/**
 * The classes that are not readonly. Each class has a reason.
 *
 * @return list<class-string>
 */
function architectureMutableClasses(): array
{
    return [
        // The Neuron Agent parent class is not readonly.
        FeasibilityAgent::class,
        // The Laravel ServiceProvider parent class is not readonly.
        BaPanelServiceProvider::class,
        // The PHP exception parent classes are not readonly.
        InvalidConfigurationException::class,
        InvalidFeasibilityRequestException::class,
        // attachMetadata() changes the report after creation.
        FeasibilityReport::class,
    ];
}

arch('makes all package classes final except the agent factory', function (): void {
    expect(ARCH_PACKAGE)
        ->classes()
        ->toBeFinal()
        ->ignoring([ARCH_TESTS, FeasibilityAgentFactory::class]);
});

arch('does not make the agent factory final, because a test class extends it', function (): void {
    expect(FeasibilityAgentFactory::class)
        ->not->toBeFinal();
});

arch('makes the agent factory readonly, so its child classes must also be readonly', function (): void {
    expect(FeasibilityAgentFactory::class)
        ->toBeReadonly();
});

arch('makes all package classes readonly except the mutable classes', function (): void {
    expect(ARCH_PACKAGE)
        ->classes()
        ->toBeReadonly()
        ->ignoring([ARCH_TESTS, ...architectureMutableClasses()]);
});

arch('does not make the mutable classes readonly', function (): void {
    expect(architectureMutableClasses())
        ->not->toBeReadonly();
});

arch('makes the nested report classes readonly', function (): void {
    expect('Lucacodorean\\BaPanel\\Reports')
        ->classes()
        ->toBeReadonly()
        ->ignoring(FeasibilityReport::class);
});

arch('does not use the PSR clock in the package', function (): void {
    expect(ARCH_PACKAGE)
        ->not->toUse('Psr\\Clock')
        ->ignoring(ARCH_TESTS);
});

arch('does not call the global Laravel helpers in the package', function (): void {
    expect(ARCH_PACKAGE)
        ->not->toUse(['app', 'config', 'view', 'report', 'resolve', 'base_path', 'logger'])
        ->ignoring(ARCH_TESTS);
});
