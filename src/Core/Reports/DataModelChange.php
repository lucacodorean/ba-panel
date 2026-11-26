<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;

/**
 * A change to an entity or a table of the data model.
 */
final readonly class DataModelChange
{
    /**
     * The Neuron deserializer sets the properties through reflection. It does not call this constructor.
     * Code that builds the object directly gives all values here.
     */
    public function __construct(
        #[SchemaProperty(description: 'The name of the entity or the table.')]
        #[NotBlank]
        public string $entity,

        #[SchemaProperty(description: 'The change to the entity or the table, for example "add column status".')]
        #[NotBlank]
        public string $change,

        #[SchemaProperty(description: 'True when the change needs a database migration.')]
        public bool $migrationRequired,

        #[SchemaProperty(description: 'True when the change can be rolled back without data loss.')]
        public bool $reversible,

        #[SchemaProperty(description: 'True when existing data must be filled in for the change.')]
        public bool $dataBackfill,
    ) {
    }

    /**
     * @return array{entity: string, change: string, migration_required: bool, reversible: bool, data_backfill: bool}
     */
    public function toArray(): array
    {
        return [
            'entity' => $this->entity,
            'change' => $this->change,
            'migration_required' => $this->migrationRequired,
            'reversible' => $this->reversible,
            'data_backfill' => $this->dataBackfill,
        ];
    }
}
