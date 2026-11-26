<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports;

use Lucacodorean\BaPanel\Core\Reports\Enums\DependencyStatus;
use Lucacodorean\BaPanel\Core\Reports\Enums\DependencyType;
use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;

/**
 * A dependency that the request needs.
 */
final readonly class Dependency
{
    /**
     * The Neuron deserializer sets the properties through reflection. It does not call this constructor.
     * Code that builds the object directly gives all values here.
     */
    public function __construct(
        #[SchemaProperty(description: 'The name of the dependency.')]
        #[NotBlank]
        public string $name,

        #[SchemaProperty(description: 'The type of the dependency.')]
        public DependencyType $type,

        #[SchemaProperty(description: 'The required version. Write "Not specified" when the documentation does not give a version.')]
        public string $version,

        #[SchemaProperty(description: 'The availability status of the dependency.')]
        public DependencyStatus $status,

        #[SchemaProperty(description: 'The risk that the dependency adds, in one sentence.')]
        public string $risk,
    ) {
    }

    /**
     * @return array{name: string, type: string, version: string, status: string, risk: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'version' => $this->version,
            'status' => $this->status->value,
            'risk' => $this->risk,
        ];
    }
}
