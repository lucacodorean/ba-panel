<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports;

use Lucacodorean\BaPanel\Reports\Enums\FeasibilityRating;
use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;

/**
 * The feasibility rating of one dimension, for example the technical dimension.
 */
final readonly class FeasibilityDimension
{
    /**
     * The Neuron deserializer sets the properties through reflection. It does not call this constructor.
     * Code that builds the object directly gives all values here.
     */
    public function __construct(
        #[SchemaProperty(description: 'The feasibility rating of the dimension.')]
        public FeasibilityRating $rating,

        #[SchemaProperty(description: 'The reason for the rating, based only on the documentation.')]
        #[NotBlank]
        public string $justification,
    ) {
    }

    /**
     * @return array{rating: string, justification: string}
     */
    public function toArray(): array
    {
        return [
            'rating' => $this->rating->value,
            'justification' => $this->justification,
        ];
    }
}
