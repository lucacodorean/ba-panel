<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\GreaterThanEqual;
use NeuronAI\StructuredOutput\Validation\Rules\LowerThanEqual;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;

/**
 * A risk of the request. The severity is calculated, not asked from the model.
 */
final readonly class Risk
{
    public const int MIN_SCORE = 1;

    public const int MAX_SCORE = 5;

    /**
     * The Neuron deserializer sets the properties through reflection. It does not call this constructor.
     * Code that builds the object directly gives all values here.
     */
    public function __construct(
        #[SchemaProperty(description: 'The risk, in one sentence.')]
        #[NotBlank]
        public string $risk,

        #[SchemaProperty(description: 'The probability of the risk, from 1 (very unlikely) to 5 (almost certain).', min: self::MIN_SCORE, max: self::MAX_SCORE)]
        #[GreaterThanEqual(self::MIN_SCORE)]
        #[LowerThanEqual(self::MAX_SCORE)]
        public int $probability,

        #[SchemaProperty(description: 'The impact of the risk, from 1 (very low) to 5 (very high).', min: self::MIN_SCORE, max: self::MAX_SCORE)]
        #[GreaterThanEqual(self::MIN_SCORE)]
        #[LowerThanEqual(self::MAX_SCORE)]
        public int $impact,

        #[SchemaProperty(description: 'The action that reduces the risk.')]
        public string $mitigation,

        #[SchemaProperty(description: 'The team or person that owns the risk. Write "Not specified" when the documentation does not name an owner.')]
        public string $owner,
    ) {
    }

    /**
     * Return the severity. The severity is the probability multiplied by the impact, from 1 to 25.
     */
    public function severity(): int
    {
        return $this->probability * $this->impact;
    }

    /**
     * @return array{risk: string, probability: int, impact: int, severity: int, mitigation: string, owner: string}
     */
    public function toArray(): array
    {
        return [
            'risk' => $this->risk,
            'probability' => $this->probability,
            'impact' => $this->impact,
            'severity' => $this->severity(),
            'mitigation' => $this->mitigation,
            'owner' => $this->owner,
        ];
    }
}
