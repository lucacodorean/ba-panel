<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Reports\AffectedComponent;
use Lucacodorean\BaPanel\Core\Reports\Alternative;
use Lucacodorean\BaPanel\Core\Reports\ApiChange;
use Lucacodorean\BaPanel\Core\Reports\DataModelChange;
use Lucacodorean\BaPanel\Core\Reports\Dependency;
use Lucacodorean\BaPanel\Core\Reports\EffortTask;
use Lucacodorean\BaPanel\Core\Reports\Enums\FeasibilityRating;
use Lucacodorean\BaPanel\Core\Reports\FeasibilityDimension;
use Lucacodorean\BaPanel\Core\Reports\FeasibilityReport;
use Lucacodorean\BaPanel\Core\Reports\NonFunctionalImpact;
use Lucacodorean\BaPanel\Core\Reports\OpenQuestion;
use Lucacodorean\BaPanel\Core\Reports\Risk;
use Lucacodorean\BaPanel\Tests\Support\FeasibilityReportFixture;

/**
 * Write the value to the property of the object.
 * The property name is a variable. Thus the static analysis does not report the write to a readonly property.
 */
function writeReportDtoProperty(object $dto, string $property, mixed $value): void
{
    $dto->{$property} = $value;
}

/**
 * Return the first item of the list. Throw when the list is empty.
 *
 * @param  array<int, object>  $items
 */
function firstReportDto(array $items): object
{
    return $items[0] ?? throw new RuntimeException('The fixture list is empty.');
}

it('throws when a property of a nested report object changes after deserialization', function (Closure $select, string $class, string $property, mixed $value): void {
    $report = FeasibilityReportFixture::report();
    $dto = $select($report);
    $before = $dto->{$property};

    expect($dto)->toBeInstanceOf($class)
        ->and(fn () => writeReportDtoProperty($dto, $property, $value))
        ->toThrow(Error::class, 'Cannot modify readonly property '.$class.'::$'.$property)
        ->and($dto->{$property})->toBe($before);
})->with([
    'alternative' => [fn (FeasibilityReport $report): object => firstReportDto($report->alternatives), Alternative::class, 'approach', 'Changed.'],
    'affected component' => [fn (FeasibilityReport $report): object => firstReportDto($report->affectedComponents), AffectedComponent::class, 'component', 'Changed.'],
    'data model change' => [fn (FeasibilityReport $report): object => firstReportDto($report->dataModelChanges), DataModelChange::class, 'entity', 'Changed.'],
    'API change' => [fn (FeasibilityReport $report): object => firstReportDto($report->apiChanges), ApiChange::class, 'endpoint', '/changed'],
    'dependency' => [fn (FeasibilityReport $report): object => firstReportDto($report->dependencies), Dependency::class, 'name', 'Changed.'],
    'non-functional impact' => [fn (FeasibilityReport $report): object => $report->performance, NonFunctionalImpact::class, 'details', 'Changed.'],
    'effort task' => [fn (FeasibilityReport $report): object => firstReportDto($report->effortBreakdown), EffortTask::class, 'task', 'Changed.'],
    'risk' => [fn (FeasibilityReport $report): object => firstReportDto($report->risks), Risk::class, 'owner', 'Changed.'],
    'feasibility dimension' => [fn (FeasibilityReport $report): object => $report->technicalFeasibility, FeasibilityDimension::class, 'justification', 'Changed.'],
    'open question' => [fn (FeasibilityReport $report): object => firstReportDto($report->openQuestions), OpenQuestion::class, 'question', 'Changed.'],
]);

it('throws when a property of a report object built with the constructor changes', function (): void {
    $dimension = new FeasibilityDimension(FeasibilityRating::cases()[0], 'The documentation covers the module.');

    expect($dimension->justification)->toBe('The documentation covers the module.')
        ->and(fn () => writeReportDtoProperty($dimension, 'justification', 'Changed.'))
        ->toThrow(Error::class, 'Cannot modify readonly property');
});
