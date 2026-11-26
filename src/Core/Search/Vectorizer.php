<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Search;

/**
 * Convert a token into a character trigram vector. Compare two vectors.
 */
final readonly class Vectorizer
{
    private const int GRAM_SIZE = 3;

    private const string PADDING = ' ';

    /**
     * Return the trigram frequencies of the token. The token is padded with one space on each side.
     *
     * @return array<string, int>
     */
    public function vectorize(string $token): array
    {
        if ($token === '') {
            return [];
        }

        $characters = mb_str_split(self::PADDING.$token.self::PADDING);
        $vector = [];

        for ($index = 0, $last = count($characters) - self::GRAM_SIZE; $index <= $last; $index++) {
            $gram = implode('', array_slice($characters, $index, self::GRAM_SIZE));
            $vector[$gram] = ($vector[$gram] ?? 0) + 1;
        }

        return $vector;
    }

    /**
     * Return the cosine similarity of two vectors, from 0 to 1. Return 0 when a vector is empty.
     *
     * @param  array<string, int>  $first
     * @param  array<string, int>  $second
     */
    public function cosine(array $first, array $second): float
    {
        if ($first === [] || $second === []) {
            return 0.0;
        }

        $dot = 0;

        foreach ($first as $gram => $count) {
            $dot += $count * ($second[$gram] ?? 0);
        }

        $norm = sqrt((float) $this->squaredSum($first)) * sqrt((float) $this->squaredSum($second));

        return $norm > 0.0 ? min(1.0, $dot / $norm) : 0.0;
    }

    /**
     * @param  array<string, int>  $vector
     */
    private function squaredSum(array $vector): int
    {
        $sum = 0;

        foreach ($vector as $count) {
            $sum += $count * $count;
        }

        return $sum;
    }
}
