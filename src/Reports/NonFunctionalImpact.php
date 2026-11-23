<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports;

use Lucacodorean\BaPanel\Reports\Enums\ImpactLevel;
use NeuronAI\StructuredOutput\SchemaProperty;

/**
 * The impact of the request on one non-functional area.
 */
final class NonFunctionalImpact
{
    #[SchemaProperty(description: 'The impact level on the area.')]
    public ImpactLevel $level;

    #[SchemaProperty(description: 'The reason for the impact level. Write "Not specified in the documentation" when the documentation does not support an assessment.')]
    public string $details;

    /**
     * @return array{level: string, details: string}
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level->value,
            'details' => $this->details,
        ];
    }
}
