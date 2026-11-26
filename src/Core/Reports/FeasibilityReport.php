<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports;

use LogicException;
use Lucacodorean\BaPanel\Core\Reports\Enums\Complexity;
use Lucacodorean\BaPanel\Core\Reports\Enums\Confidence;
use Lucacodorean\BaPanel\Core\Reports\Enums\OverallVerdict;
use Lucacodorean\BaPanel\Core\Reports\Enums\Priority;
use Lucacodorean\BaPanel\Core\Reports\Enums\RequestType;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;
use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\ArrayOf;
use NeuronAI\StructuredOutput\Validation\Rules\GreaterThanEqual;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;

/**
 * The feasibility report of a change request or a feature request.
 *
 * The public properties are the structured output of the feasibility agent. They make the JSON schema.
 * The system fields are in ReportMetadata. The metadata property is private, so the schema does not include it.
 */
final class FeasibilityReport
{
    /**
     * The template name of the Markdown report.
     */
    public const string VIEW = 'reports/feasibility';

    private const string METADATA_MISSING = 'The report has no metadata. Call attachMetadata() first.';

    private const string NOT_SPECIFIED_HINT = ' Write "Not specified in the documentation" when the documentation does not give this information.';

    private const string EMPTY_LIST_HINT = ' Use an empty list when the documentation does not give this information.';

    // 1. Metadata

    #[SchemaProperty(description: 'A short title of the request, at most 10 words.')]
    #[NotBlank]
    public string $requestTitle;

    #[SchemaProperty(description: 'The type of the request: a change to existing behaviour or a new feature.')]
    public RequestType $requestType;

    #[SchemaProperty(description: 'The priority of the request. Use "medium" when the user does not give a priority.')]
    public Priority $priority;

    #[SchemaProperty(description: 'The person or team that asks for the change. Write "Not specified" when the user does not say it.')]
    public string $requester;

    #[SchemaProperty(description: 'The target release of the change. Write "Not specified" when the user does not say it.')]
    public string $targetRelease;

    /**
     * @var list<string>
     */
    #[SchemaProperty(description: 'The ticket ids that the user mentions. Use an empty list when the user does not mention a ticket.')]
    #[ArrayOf('string', allowEmpty: true)]
    public array $linkedTickets;

    // 2. Request summary

    #[SchemaProperty(description: 'A description of the request in your own words.')]
    #[NotBlank]
    public string $requestDescription;

    #[SchemaProperty(description: 'The current behaviour of the system, as the documentation describes it.'.self::NOT_SPECIFIED_HINT)]
    public string $currentBehaviour;

    #[SchemaProperty(description: 'The behaviour that the request asks for.')]
    #[NotBlank]
    public string $requestedBehaviour;

    #[SchemaProperty(description: 'The technical reason for the change.'.self::NOT_SPECIFIED_HINT)]
    public string $technicalMotivation;

    /**
     * @var list<string>
     */
    #[SchemaProperty(description: 'The acceptance criteria of the request. Each item is one testable statement.')]
    #[ArrayOf('string', allowEmpty: true)]
    public array $acceptanceCriteria;

    // 3. Scope

    /**
     * @var list<string>
     */
    #[SchemaProperty(description: 'The items that are in scope.')]
    #[ArrayOf('string', allowEmpty: true)]
    public array $inScope;

    /**
     * @var list<string>
     */
    #[SchemaProperty(description: 'The items that are out of scope.')]
    #[ArrayOf('string', allowEmpty: true)]
    public array $outOfScope;

    /**
     * @var list<string>
     */
    #[SchemaProperty(description: 'The assumptions of the analysis. Mark each assumption that the documentation does not support.')]
    #[ArrayOf('string', allowEmpty: true)]
    public array $assumptions;

    /**
     * @var list<string>
     */
    #[SchemaProperty(description: 'The constraints on the solution, for example technical, legal or time constraints.'.self::EMPTY_LIST_HINT)]
    #[ArrayOf('string', allowEmpty: true)]
    public array $constraints;

    // 4. Technical analysis

    #[SchemaProperty(description: 'The part of the current architecture that the request touches, as the documentation describes it.'.self::NOT_SPECIFIED_HINT)]
    public string $currentArchitecture;

    #[SchemaProperty(description: 'The proposed solution. Refer to the documentation files that support it.')]
    #[NotBlank]
    public string $proposedSolution;

    /**
     * @var list<Alternative>
     */
    #[SchemaProperty(description: 'The approaches that you considered, including the selected approach.', anyOf: [Alternative::class])]
    #[ArrayOf(Alternative::class, allowEmpty: true)]
    public array $alternatives;

    #[SchemaProperty(description: 'The reason for the selected approach.')]
    public string $alternativeSelectionRationale;

    /**
     * @var list<AffectedComponent>
     */
    #[SchemaProperty(description: 'The components that the request changes.'.self::EMPTY_LIST_HINT, anyOf: [AffectedComponent::class])]
    #[ArrayOf(AffectedComponent::class, allowEmpty: true)]
    public array $affectedComponents;

    /**
     * @var list<DataModelChange>
     */
    #[SchemaProperty(description: 'The changes to the data model. Use an empty list when the data model does not change.', anyOf: [DataModelChange::class])]
    #[ArrayOf(DataModelChange::class, allowEmpty: true)]
    public array $dataModelChanges;

    #[SchemaProperty(description: 'Notes about the data migration. Write "Not applicable" when no migration is necessary.')]
    public string $migrationNotes;

    /**
     * @var list<ApiChange>
     */
    #[SchemaProperty(description: 'The changes to APIs or interface contracts. Use an empty list when no contract changes.', anyOf: [ApiChange::class])]
    #[ArrayOf(ApiChange::class, allowEmpty: true)]
    public array $apiChanges;

    /**
     * @var list<Dependency>
     */
    #[SchemaProperty(description: 'The dependencies that the request needs.'.self::EMPTY_LIST_HINT, anyOf: [Dependency::class])]
    #[ArrayOf(Dependency::class, allowEmpty: true)]
    public array $dependencies;

    // 5. Non-functional impact

    #[SchemaProperty(description: 'The impact on backward compatibility.')]
    public NonFunctionalImpact $backwardCompatibility;

    #[SchemaProperty(description: 'The impact on performance.')]
    public NonFunctionalImpact $performance;

    #[SchemaProperty(description: 'The impact on scalability.')]
    public NonFunctionalImpact $scalability;

    #[SchemaProperty(description: 'The impact on security.')]
    public NonFunctionalImpact $security;

    #[SchemaProperty(description: 'The impact on privacy and compliance.')]
    public NonFunctionalImpact $privacy;

    #[SchemaProperty(description: 'The impact on availability and reliability.')]
    public NonFunctionalImpact $availability;

    #[SchemaProperty(description: 'The impact on observability, for example logs, metrics and alerts.')]
    public NonFunctionalImpact $observability;

    #[SchemaProperty(description: 'The impact on maintainability.')]
    public NonFunctionalImpact $maintainability;

    #[SchemaProperty(description: 'The expected load on the changed parts.'.self::NOT_SPECIFIED_HINT)]
    public string $expectedLoad;

    #[SchemaProperty(description: 'The p95 latency target.'.self::NOT_SPECIFIED_HINT)]
    public string $latencyTargetP95;

    #[SchemaProperty(description: 'The extra CPU, memory and storage that the change needs.'.self::NOT_SPECIFIED_HINT)]
    public string $resourceOverhead;

    #[SchemaProperty(description: 'The changes to authentication and authorisation. Write "None" when nothing changes.')]
    public string $authzChanges;

    #[SchemaProperty(description: 'The classes of data that the change touches, for example "personal data".'.self::NOT_SPECIFIED_HINT)]
    public string $dataClassification;

    /**
     * @var list<string>
     */
    #[SchemaProperty(description: 'The security threats that the change adds.'.self::EMPTY_LIST_HINT)]
    #[ArrayOf('string', allowEmpty: true)]
    public array $securityThreats;

    /**
     * @var list<string>
     */
    #[SchemaProperty(description: 'The configuration and infrastructure changes. Use an empty list when nothing changes.')]
    #[ArrayOf('string', allowEmpty: true)]
    public array $infraChanges;

    // 6. Effort estimation

    /**
     * @var list<EffortTask>
     */
    #[SchemaProperty(description: 'The tasks of the effort estimate. The total estimate is the sum of the task estimates.', anyOf: [EffortTask::class])]
    #[ArrayOf(EffortTask::class, allowEmpty: true)]
    public array $effortBreakdown;

    #[SchemaProperty(description: 'The lowest total estimate, in hours.', min: 0)]
    #[GreaterThanEqual(0)]
    public float $effortMinHours;

    #[SchemaProperty(description: 'The highest total estimate, in hours.', min: 0)]
    #[GreaterThanEqual(0)]
    public float $effortMaxHours;

    #[SchemaProperty(description: 'The story points of the request. Use null when the documentation does not support an estimate.', min: 0)]
    #[GreaterThanEqual(0)]
    public ?int $storyPoints = null;

    #[SchemaProperty(description: 'The complexity of the request.')]
    public Complexity $complexity;

    #[SchemaProperty(description: 'The estimation method, for example "task breakdown" or "analogy with a similar feature".')]
    public string $estimationMethod;

    // 7. Risk assessment

    /**
     * @var list<Risk>
     */
    #[SchemaProperty(description: 'The risks of the request. Include the risk of missing documentation when information is absent.', anyOf: [Risk::class])]
    #[ArrayOf(Risk::class, allowEmpty: true)]
    public array $risks;

    // 8. Technical debt

    #[SchemaProperty(description: 'The technical debt that the change adds. Write "None" when the change adds no debt.')]
    public string $techDebtIntroduced;

    #[SchemaProperty(description: 'The technical debt that the change removes. Write "None" when the change removes no debt.')]
    public string $techDebtResolved;

    /**
     * @var list<string>
     */
    #[SchemaProperty(description: 'The follow-up tasks for the technical debt. Use an empty list when no follow-up is necessary.')]
    #[ArrayOf('string', allowEmpty: true)]
    public array $techDebtFollowups;

    // 9. Feasibility verdict

    #[SchemaProperty(description: 'The technical feasibility.')]
    public FeasibilityDimension $technicalFeasibility;

    #[SchemaProperty(description: 'The operational feasibility.')]
    public FeasibilityDimension $operationalFeasibility;

    #[SchemaProperty(description: 'The schedule feasibility.')]
    public FeasibilityDimension $scheduleFeasibility;

    #[SchemaProperty(description: 'The resource feasibility.')]
    public FeasibilityDimension $resourceFeasibility;

    #[SchemaProperty(description: 'The overall verdict. Use "needs_more_info" when the documentation does not give enough information for a verdict.')]
    public OverallVerdict $overallVerdict;

    #[SchemaProperty(description: 'The confidence level of the verdict.')]
    public Confidence $verdictConfidence;

    /**
     * @var list<string>
     */
    #[SchemaProperty(description: 'The conditions for approval. Use an empty list when there is no condition.')]
    #[ArrayOf('string', allowEmpty: true)]
    public array $approvalConditions;

    #[SchemaProperty(description: 'The recommendation for the business analyst, in a short paragraph.')]
    #[NotBlank]
    public string $recommendation;

    // 10. Open questions

    /**
     * @var list<OpenQuestion>
     */
    #[SchemaProperty(description: 'The questions that the documentation does not answer. Add a question for each piece of missing information.', anyOf: [OpenQuestion::class])]
    #[ArrayOf(OpenQuestion::class, allowEmpty: true)]
    public array $openQuestions;

    // 11. References

    /**
     * @var list<string>
     */
    #[SchemaProperty(description: 'The paths of the documentation files that the analysis uses. Use only paths from the given files.')]
    #[ArrayOf('string', allowEmpty: true)]
    public array $references;

    /**
     * The system fields. The property is private, so the JSON schema and the deserializer ignore it.
     */
    private ?ReportMetadata $metadata = null;

    /**
     * Set the system fields of the report.
     */
    public function attachMetadata(ReportMetadata $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Return the system fields. The metadata must be set with attachMetadata() first.
     *
     * @throws LogicException When no metadata is set.
     */
    public function metadata(): ReportMetadata
    {
        return $this->metadata ?? throw new LogicException(self::METADATA_MISSING);
    }

    /**
     * Return the total estimate in hours. The total is the sum of the task estimates.
     */
    public function effortTotalHours(): float
    {
        return (float) array_sum(array_map(static fn (EffortTask $task): float => $task->estimateHours, $this->effortBreakdown));
    }

    /**
     * Return the report as Markdown. The template "reports/feasibility" makes the text.
     * The text has no blank lines at the start and ends with one line break.
     * The caller gives the renderer. The agent deserializes the report, so the report cannot get services in the constructor.
     *
     * @throws LogicException When no metadata is set.
     */
    public function toMarkdown(TemplateRenderer $renderer): string
    {
        return trim($renderer->render(self::VIEW, ['report' => $this, 'metadata' => $this->metadata()]))."\n";
    }

    /**
     * Return the report as an array with snake_case keys. The array includes the system fields and the calculated fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'metadata' => $this->metadata()->toArray(),
            'request_title' => $this->requestTitle,
            'request_type' => $this->requestType->value,
            'priority' => $this->priority->value,
            'requester' => $this->requester,
            'target_release' => $this->targetRelease,
            'linked_tickets' => $this->linkedTickets,
            'request_description' => $this->requestDescription,
            'current_behaviour' => $this->currentBehaviour,
            'requested_behaviour' => $this->requestedBehaviour,
            'technical_motivation' => $this->technicalMotivation,
            'acceptance_criteria' => $this->acceptanceCriteria,
            'in_scope' => $this->inScope,
            'out_of_scope' => $this->outOfScope,
            'assumptions' => $this->assumptions,
            'constraints' => $this->constraints,
            'current_architecture' => $this->currentArchitecture,
            'proposed_solution' => $this->proposedSolution,
            'alternatives' => array_map(static fn (Alternative $item): array => $item->toArray(), $this->alternatives),
            'alternative_selection_rationale' => $this->alternativeSelectionRationale,
            'affected_components' => array_map(static fn (AffectedComponent $item): array => $item->toArray(), $this->affectedComponents),
            'data_model_changes' => array_map(static fn (DataModelChange $item): array => $item->toArray(), $this->dataModelChanges),
            'migration_notes' => $this->migrationNotes,
            'api_changes' => array_map(static fn (ApiChange $item): array => $item->toArray(), $this->apiChanges),
            'dependencies' => array_map(static fn (Dependency $item): array => $item->toArray(), $this->dependencies),
            'non_functional_impact' => [
                'backward_compatibility' => $this->backwardCompatibility->toArray(),
                'performance' => $this->performance->toArray(),
                'scalability' => $this->scalability->toArray(),
                'security' => $this->security->toArray(),
                'privacy' => $this->privacy->toArray(),
                'availability' => $this->availability->toArray(),
                'observability' => $this->observability->toArray(),
                'maintainability' => $this->maintainability->toArray(),
            ],
            'expected_load' => $this->expectedLoad,
            'latency_target_p95' => $this->latencyTargetP95,
            'resource_overhead' => $this->resourceOverhead,
            'authz_changes' => $this->authzChanges,
            'data_classification' => $this->dataClassification,
            'security_threats' => $this->securityThreats,
            'infra_changes' => $this->infraChanges,
            'effort_breakdown' => array_map(static fn (EffortTask $item): array => $item->toArray(), $this->effortBreakdown),
            'effort_total_hours' => $this->effortTotalHours(),
            'effort_min_hours' => $this->effortMinHours,
            'effort_max_hours' => $this->effortMaxHours,
            'story_points' => $this->storyPoints,
            'complexity' => $this->complexity->value,
            'estimation_method' => $this->estimationMethod,
            'risks' => array_map(static fn (Risk $item): array => $item->toArray(), $this->risks),
            'tech_debt_introduced' => $this->techDebtIntroduced,
            'tech_debt_resolved' => $this->techDebtResolved,
            'tech_debt_followups' => $this->techDebtFollowups,
            'feasibility' => [
                'technical' => $this->technicalFeasibility->toArray(),
                'operational' => $this->operationalFeasibility->toArray(),
                'schedule' => $this->scheduleFeasibility->toArray(),
                'resource' => $this->resourceFeasibility->toArray(),
            ],
            'overall_verdict' => $this->overallVerdict->value,
            'verdict_confidence' => $this->verdictConfidence->value,
            'approval_conditions' => $this->approvalConditions,
            'recommendation' => $this->recommendation,
            'open_questions' => array_map(static fn (OpenQuestion $item): array => $item->toArray(), $this->openQuestions),
            'references' => $this->references,
        ];
    }
}
