<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;

/**
 * An approach that the analysis considered for the request.
 */
final class Alternative
{
    #[SchemaProperty(description: 'A short name and description of the approach.')]
    #[NotBlank]
    public string $approach;

    #[SchemaProperty(description: 'The advantages of the approach. Use one sentence or a short comma-separated list.')]
    public string $pros;

    #[SchemaProperty(description: 'The disadvantages of the approach. Use one sentence or a short comma-separated list.')]
    public string $cons;

    #[SchemaProperty(description: 'The relative effort of the approach, for example "2-3 days" or "high". Write "Not specified" when the documentation does not support an estimate.')]
    public string $effort;

    #[SchemaProperty(description: 'True for the approach that the proposed solution uses. Select at most one approach.')]
    public bool $selected;

    /**
     * @return array{approach: string, pros: string, cons: string, effort: string, selected: bool}
     */
    public function toArray(): array
    {
        return [
            'approach' => $this->approach,
            'pros' => $this->pros,
            'cons' => $this->cons,
            'effort' => $this->effort,
            'selected' => $this->selected,
        ];
    }
}
