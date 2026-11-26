<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports;

use Lucacodorean\BaPanel\Core\Reports\Enums\ImpactLevel;
use NeuronAI\StructuredOutput\SchemaProperty;

/**
 * The impact of the request on one non-functional area.
 */
final readonly class NonFunctionalImpact
{
    /**
     * The Neuron deserializer sets the properties through reflection. It does not call this constructor.
     * Code that builds the object directly gives all values here.
     */
    public function __construct(
        #[SchemaProperty(description: 'The impact level on the area.')]
        public ImpactLevel $level,

        #[SchemaProperty(description: 'The reason for the impact level. Write "Not specified in the documentation" when the documentation does not support an assessment.')]
        public string $details,
    ) {
    }

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
