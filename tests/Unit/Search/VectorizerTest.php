<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Search\Vectorizer;

it('converts a token into padded character trigrams', function (): void {
    expect((new Vectorizer)->vectorize('api'))->toBe([' ap' => 1, 'api' => 1, 'pi ' => 1]);
});

it('counts repeated trigrams', function (): void {
    expect((new Vectorizer)->vectorize('aaaa'))->toBe([' aa' => 1, 'aaa' => 2, 'aa ' => 1]);
});

it('creates trigrams for a one character token', function (): void {
    expect((new Vectorizer)->vectorize('a'))->toBe([' a ' => 1]);
});

it('returns an empty vector for an empty token', function (): void {
    expect((new Vectorizer)->vectorize(''))->toBe([]);
});

it('returns 1.0 for identical vectors', function (): void {
    $vectorizer = new Vectorizer;
    $vector = $vectorizer->vectorize('endpoints');

    expect($vectorizer->cosine($vector, $vector))->toEqualWithDelta(1.0, 1e-9);
});

it('returns 0.0 when a vector is empty', function (): void {
    $vectorizer = new Vectorizer;
    $vector = $vectorizer->vectorize('api');

    expect($vectorizer->cosine([], $vector))->toBe(0.0)
        ->and($vectorizer->cosine($vector, []))->toBe(0.0)
        ->and($vectorizer->cosine([], []))->toBe(0.0);
});

it('returns 0.0 for vectors without common trigrams', function (): void {
    $vectorizer = new Vectorizer;

    expect($vectorizer->cosine($vectorizer->vectorize('api'), $vectorizer->vectorize('xyz')))->toBe(0.0);
});

it('returns the cosine similarity of two vectors', function (): void {
    $vectorizer = new Vectorizer;

    // " endpoints " has 9 trigrams. " endpoint " has 8 trigrams. They share 7 trigrams.
    expect($vectorizer->cosine($vectorizer->vectorize('endpoints'), $vectorizer->vectorize('endpoint')))
        ->toEqualWithDelta(7 / sqrt(72), 1e-9);
});

it('is symmetric', function (): void {
    $vectorizer = new Vectorizer;
    $first = $vectorizer->vectorize('model');
    $second = $vectorizer->vectorize('models');

    expect($vectorizer->cosine($first, $second))->toBe($vectorizer->cosine($second, $first));
});
