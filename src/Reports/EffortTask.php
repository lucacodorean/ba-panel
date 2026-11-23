<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports;

use Lucacodorean\BaPanel\Reports\Enums\Confidence;
use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\GreaterThanEqual;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;

/**
 * A task of the effort estimate.
 */
final class EffortTask
{
    #[SchemaProperty(description: 'The task to do.')]
    #[NotBlank]
    public string $task;

    #[SchemaProperty(description: 'The role that does the task, for example "backend developer".')]
    public string $role;

    #[SchemaProperty(description: 'The estimate for the task, in hours.', min: 0)]
    #[GreaterThanEqual(0)]
    public float $estimateHours;

    #[SchemaProperty(description: 'The confidence level of the estimate.')]
    public Confidence $confidence;

    /**
     * @return array{task: string, role: string, estimate_hours: float, confidence: string}
     */
    public function toArray(): array
    {
        return [
            'task' => $this->task,
            'role' => $this->role,
            'estimate_hours' => $this->estimateHours,
            'confidence' => $this->confidence->value,
        ];
    }
}
