<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgent;
use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidFeasibilityRequestException;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidTemplateException;
use Lucacodorean\BaPanel\Core\Reports\FeasibilityReport;
use Lucacodorean\BaPanel\Laravel\BaPanelServiceProvider;
use Lucacodorean\BaPanel\Symfony\BaPanelBundle;
use Lucacodorean\BaPanel\Symfony\Routing\BaPanelRouteLoader;

/*
| These rules apply to the package classes in src/. The test classes in the Tests namespace are not part of the rules.
*/

/**
 * The package namespace. It does not include the Tests namespace.
 */
const ARCH_PACKAGE = 'Lucacodorean\\BaPanel';

const ARCH_TESTS = 'Lucacodorean\\BaPanel\\Tests';

/**
 * The framework-independent core. The shells depend on it. It does not depend on a shell or a framework.
 */
const ARCH_CORE = 'Lucacodorean\\BaPanel\\Core';

const ARCH_LARAVEL = 'Lucacodorean\\BaPanel\\Laravel';

const ARCH_SYMFONY = 'Lucacodorean\\BaPanel\\Symfony';

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
        // The Symfony AbstractBundle and Loader parent classes are not readonly.
        BaPanelBundle::class,
        BaPanelRouteLoader::class,
        // The PHP exception parent classes are not readonly.
        InvalidConfigurationException::class,
        InvalidFeasibilityRequestException::class,
        InvalidTemplateException::class,
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
    expect('Lucacodorean\\BaPanel\\Core\\Reports')
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

arch('does not use a framework in the core', function (): void {
    // The core gets symfony/yaml only through league/commonmark. It does not import a Symfony class directly.
    expect(ARCH_CORE)
        ->not->toUse([
            'Illuminate',
            'Symfony\\Component\\HttpFoundation',
            'Symfony\\Component\\HttpKernel',
            'Symfony\\Component\\DependencyInjection',
            'Symfony\\Component\\Routing',
            'Symfony\\Component\\Config',
            'Symfony\\Bundle',
            'Psr\\Log',
        ]);
});

arch('does not use a shell in the core', function (): void {
    expect(ARCH_CORE)
        ->not->toUse([ARCH_LARAVEL, ARCH_SYMFONY]);
});

arch('does not use the Symfony shell or the Symfony bundles in the Laravel shell', function (): void {
    expect(ARCH_LARAVEL)
        ->not->toUse([ARCH_SYMFONY, 'Symfony\\Bundle']);
});

arch('does not use Laravel or the Laravel shell in the Symfony shell', function (): void {
    expect(ARCH_SYMFONY)
        ->not->toUse(['Illuminate', ARCH_LARAVEL]);
});

arch('makes the core services final and readonly', function (): void {
    expect([
        'Lucacodorean\\BaPanel\\Core\\Feasibility',
        'Lucacodorean\\BaPanel\\Core\\Panel',
        'Lucacodorean\\BaPanel\\Core\\View',
        'Lucacodorean\\BaPanel\\Core\\Config',
    ])
        ->classes()
        ->toBeFinal()
        ->toBeReadonly();
});
