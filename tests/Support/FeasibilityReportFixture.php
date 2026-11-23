<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Tests\Support;

use Lucacodorean\BaPanel\Reports\FeasibilityReport;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\StructuredOutput\Deserializer\Deserializer;
use RuntimeException;

/**
 * Build valid feasibility report data for tests. The keys are the property names of FeasibilityReport.
 * The data is the JSON that the model returns. It has no metadata, because PHP adds the metadata.
 */
final class FeasibilityReportFixture
{
    /**
     * Return the report data. The overrides replace the top-level values.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function data(array $overrides = []): array
    {
        return array_replace(self::defaults(), $overrides);
    }

    /**
     * Return the report data with all lists empty. The overrides replace the top-level values.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function emptyListsData(array $overrides = []): array
    {
        $lists = [
            'linkedTickets', 'acceptanceCriteria', 'inScope', 'outOfScope', 'assumptions', 'constraints',
            'alternatives', 'affectedComponents', 'dataModelChanges', 'apiChanges', 'dependencies',
            'securityThreats', 'infraChanges', 'effortBreakdown', 'risks', 'techDebtFollowups',
            'approvalConditions', 'openQuestions', 'references',
        ];

        return array_replace(self::defaults(), array_fill_keys($lists, []), $overrides);
    }

    /**
     * Return the report data as JSON.
     *
     * @param  array<string, mixed>  $data
     */
    public static function json(?array $data = null): string
    {
        return json_encode($data ?? self::data(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Return the assistant message that a fake provider returns for a structured request.
     *
     * @param  array<string, mixed>  $data
     */
    public static function message(?array $data = null): AssistantMessage
    {
        return new AssistantMessage(self::json($data));
    }

    /**
     * Return the report object. The Neuron deserializer builds it, as in a real agent run.
     *
     * @param  array<string, mixed>  $data
     */
    public static function report(?array $data = null): FeasibilityReport
    {
        $report = Deserializer::make()->fromJson(self::json($data), FeasibilityReport::class);

        if (! $report instanceof FeasibilityReport) {
            throw new RuntimeException('The deserializer did not return a feasibility report.');
        }

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        $impact = static fn (string $level, string $details): array => ['level' => $level, 'details' => $details];
        $dimension = static fn (string $rating, string $justification): array => ['rating' => $rating, 'justification' => $justification];

        return [
            'requestTitle' => 'Export invoices as CSV',
            'requestType' => 'feature_request',
            'priority' => 'high',
            'requester' => 'Finance team',
            'targetRelease' => 'v2.4',
            'linkedTickets' => ['FIN-101', 'FIN-102'],
            'requestDescription' => 'The finance team wants to export invoices as a CSV file.',
            'currentBehaviour' => 'Invoices are shown only on the invoices page.',
            'requestedBehaviour' => 'A button exports the filtered invoices as CSV.',
            'technicalMotivation' => 'Not specified in the documentation',
            'acceptanceCriteria' => ['The export contains all filtered invoices.', 'The file uses UTF-8.'],
            'inScope' => ['CSV export of invoices'],
            'outOfScope' => ['PDF export'],
            'assumptions' => ['The invoice API supports filters.'],
            'constraints' => ['The export must finish in 10 seconds.'],
            'currentArchitecture' => 'A Laravel monolith with an invoices module.',
            'proposedSolution' => 'Add an export action to the invoices controller.',
            'alternatives' => [
                ['approach' => 'Synchronous export', 'pros' => 'Simple', 'cons' => 'Slow for large sets', 'effort' => 'Low', 'selected' => true],
                ['approach' => 'Queued export', 'pros' => 'Scales', 'cons' => 'More parts', 'effort' => 'Medium', 'selected' => false],
            ],
            'alternativeSelectionRationale' => 'The data sets are small.',
            'affectedComponents' => [
                ['component' => 'InvoiceController', 'layer' => 'backend', 'changeType' => 'modify', 'impact' => 'medium', 'owner' => 'Billing team'],
            ],
            'dataModelChanges' => [
                ['entity' => 'invoices', 'change' => 'Add an index on issued_at', 'migrationRequired' => true, 'reversible' => true, 'dataBackfill' => false],
            ],
            'migrationNotes' => 'Not applicable',
            'apiChanges' => [
                ['endpoint' => '/invoices/export', 'method' => 'GET', 'change' => 'New endpoint', 'breaking' => false, 'versioningStrategy' => 'None'],
            ],
            'dependencies' => [
                ['name' => 'league/csv', 'type' => 'library', 'version' => '^9.0', 'status' => 'available', 'risk' => 'Low'],
            ],
            'backwardCompatibility' => $impact('none', 'The change adds a new endpoint.'),
            'performance' => $impact('low', 'The export reads the filtered rows once.'),
            'scalability' => $impact('low', 'Not specified in the documentation'),
            'security' => $impact('medium', 'The export must respect the invoice permissions.'),
            'privacy' => $impact('medium', 'Invoices contain personal data.'),
            'availability' => $impact('none', 'No change.'),
            'observability' => $impact('low', 'Log each export.'),
            'maintainability' => $impact('low', 'One new class.'),
            'expectedLoad' => '50 exports per day',
            'latencyTargetP95' => 'Not specified in the documentation',
            'resourceOverhead' => 'Low',
            'authzChanges' => 'None',
            'dataClassification' => 'personal data',
            'securityThreats' => ['CSV injection'],
            'infraChanges' => [],
            'effortBreakdown' => [
                ['task' => 'Backend endpoint', 'role' => 'Backend developer', 'estimateHours' => 6, 'confidence' => 'medium'],
                ['task' => 'Frontend button', 'role' => 'Frontend developer', 'estimateHours' => 2.5, 'confidence' => 'high'],
            ],
            'effortMinHours' => 6,
            'effortMaxHours' => 12,
            'storyPoints' => 5,
            'complexity' => 'medium',
            'estimationMethod' => 'task breakdown',
            'risks' => [
                ['risk' => 'Large exports time out.', 'probability' => 2, 'impact' => 4, 'mitigation' => 'Limit the rows.', 'owner' => 'Billing team'],
            ],
            'techDebtIntroduced' => 'None',
            'techDebtResolved' => 'None',
            'techDebtFollowups' => ['Move the export to a queue later.'],
            'technicalFeasibility' => $dimension('feasible', 'The stack supports CSV output.'),
            'operationalFeasibility' => $dimension('feasible', 'No new operations.'),
            'scheduleFeasibility' => $dimension('feasible_with_conditions', 'The release date is close.'),
            'resourceFeasibility' => $dimension('feasible', 'The team has capacity.'),
            'overallVerdict' => 'feasible_with_conditions',
            'verdictConfidence' => 'medium',
            'approvalConditions' => ['Confirm the column list with finance.'],
            'recommendation' => 'Build the synchronous export first.',
            'openQuestions' => [
                ['question' => 'Which columns are required?', 'addressedTo' => 'Finance team', 'blocking' => true, 'status' => 'open'],
            ],
            'references' => ['docs/invoices.md'],
        ];
    }
}
