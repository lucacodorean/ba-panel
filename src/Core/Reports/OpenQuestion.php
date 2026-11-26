<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;

/**
 * A question that the documentation does not answer.
 */
final readonly class OpenQuestion
{
    /**
     * The Neuron deserializer sets the properties through reflection. It does not call this constructor.
     * Code that builds the object directly gives all values here.
     */
    public function __construct(
        #[SchemaProperty(description: 'The question.')]
        #[NotBlank]
        public string $question,

        #[SchemaProperty(description: 'The role or team that can answer the question, for example "product owner".')]
        public string $addressedTo,

        #[SchemaProperty(description: 'True when the request cannot continue without the answer.')]
        public bool $blocking,

        #[SchemaProperty(description: 'The status of the question. Use "Open" for a question that has no answer yet.')]
        public string $status,
    ) {
    }

    /**
     * @return array{question: string, addressed_to: string, blocking: bool, status: string}
     */
    public function toArray(): array
    {
        return [
            'question' => $this->question,
            'addressed_to' => $this->addressedTo,
            'blocking' => $this->blocking,
            'status' => $this->status,
        ];
    }
}
