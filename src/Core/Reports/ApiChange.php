<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;

/**
 * A change to an API endpoint or an interface contract.
 */
final readonly class ApiChange
{
    /**
     * The Neuron deserializer sets the properties through reflection. It does not call this constructor.
     * Code that builds the object directly gives all values here.
     */
    public function __construct(
        #[SchemaProperty(description: 'The endpoint path or the interface name.')]
        #[NotBlank]
        public string $endpoint,

        #[SchemaProperty(description: 'The HTTP method, for example "GET", or "Not applicable" for an interface that is not HTTP.')]
        public string $method,

        #[SchemaProperty(description: 'The change to the endpoint or the interface.')]
        #[NotBlank]
        public string $change,

        #[SchemaProperty(description: 'True when the change breaks existing clients.')]
        public bool $breaking,

        #[SchemaProperty(description: 'The versioning strategy for the change. Write "Not specified" when the documentation does not describe one.')]
        public string $versioningStrategy,
    ) {
    }

    /**
     * @return array{endpoint: string, method: string, change: string, breaking: bool, versioning_strategy: string}
     */
    public function toArray(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'method' => $this->method,
            'change' => $this->change,
            'breaking' => $this->breaking,
            'versioning_strategy' => $this->versioningStrategy,
        ];
    }
}
