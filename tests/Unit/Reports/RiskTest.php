<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Reports\Risk;
use NeuronAI\StructuredOutput\Deserializer\Deserializer;
use NeuronAI\StructuredOutput\Validation\Validator;

/**
 * Build a risk through the Neuron deserializer, as in a real agent run.
 */
function riskFixture(int $probability, int $impact): Risk
{
    $risk = Deserializer::make()->fromJson((string) json_encode([
        'risk' => 'The export times out.',
        'probability' => $probability,
        'impact' => $impact,
        'mitigation' => 'Limit the rows.',
        'owner' => 'Billing team',
    ]), Risk::class);

    if (! $risk instanceof Risk) {
        throw new RuntimeException('The deserializer did not return a risk.');
    }

    return $risk;
}

it('calculates the severity as probability multiplied by impact', function (int $probability, int $impact, int $severity): void {
    expect(riskFixture($probability, $impact)->severity())->toBe($severity);
})->with([
    'lowest' => [1, 1, 1],
    'mixed' => [2, 4, 8],
    'reversed' => [4, 2, 8],
    'highest' => [5, 5, 25],
]);

it('returns the risk as an array with the severity', function (): void {
    expect(riskFixture(3, 4)->toArray())->toBe([
        'risk' => 'The export times out.',
        'probability' => 3,
        'impact' => 4,
        'severity' => 12,
        'mitigation' => 'Limit the rows.',
        'owner' => 'Billing team',
    ]);
});

it('accepts scores from 1 to 5', function (): void {
    expect(Validator::validate(riskFixture(1, 5)))->toBe([])
        ->and(Validator::validate(riskFixture(5, 1)))->toBe([]);
});

it('refuses scores outside 1 to 5', function (int $probability, int $impact): void {
    expect(Validator::validate(riskFixture($probability, $impact)))->not->toBe([]);
})->with([
    'probability zero' => [0, 3],
    'probability six' => [6, 3],
    'impact zero' => [3, 0],
    'impact six' => [3, 6],
]);
