<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports;

use Lucacodorean\BaPanel\Core\Reports\Enums\ChangeType;
use Lucacodorean\BaPanel\Core\Reports\Enums\Impact;
use Lucacodorean\BaPanel\Core\Reports\Enums\Layer;
use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;

/**
 * A component of the system that the request changes.
 */
final readonly class AffectedComponent
{
    /**
     * The Neuron deserializer sets the properties through reflection. It does not call this constructor.
     * Code that builds the object directly gives all values here.
     */
    public function __construct(
        #[SchemaProperty(description: 'The name of the component, as the documentation names it.')]
        #[NotBlank]
        public string $component,

        #[SchemaProperty(description: 'The architecture layer of the component.')]
        public Layer $layer,

        #[SchemaProperty(description: 'The type of change to the component.')]
        public ChangeType $changeType,

        #[SchemaProperty(description: 'The impact of the change on the component.')]
        public Impact $impact,

        #[SchemaProperty(description: 'The team or person that owns the component. Write "Not specified" when the documentation does not name an owner.')]
        public string $owner,
    ) {}

    /**
     * @return array{component: string, layer: string, change_type: string, impact: string, owner: string}
     */
    public function toArray(): array
    {
        return [
            'component' => $this->component,
            'layer' => $this->layer->value,
            'change_type' => $this->changeType->value,
            'impact' => $this->impact->value,
            'owner' => $this->owner,
        ];
    }
}
