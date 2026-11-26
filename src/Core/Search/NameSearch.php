<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Search;

use Lucacodorean\BaPanel\Core\Graph\Graph;

/**
 * Search the graph nodes by name. Compare tokens as character trigram vectors.
 */
final readonly class NameSearch
{
    /**
     * The minimum score for a result.
     */
    public const float THRESHOLD = 0.8;

    private const float FULL_MATCH = 1.0;

    public function __construct(
        private Tokenizer $tokenizer,
        private Vectorizer $vectorizer,
        private float $threshold = self::THRESHOLD,
    ) {}

    public function threshold(): float
    {
        return $this->threshold;
    }

    /**
     * Return the nodes with a score at or above the threshold.
     * Sort by score, highest first, then by path.
     *
     * @return list<SearchResult>
     */
    public function search(Graph $graph, string $query): array
    {
        $queryTokens = $this->tokenizer->tokenize($query);

        if ($queryTokens === []) {
            return [];
        }

        $queryVectors = array_map($this->vectorizer->vectorize(...), $queryTokens);
        $results = [];

        foreach ($graph->nodes() as $node) {
            $score = $this->score($queryTokens, $queryVectors, $this->tokenizer->tokenize($node->name));

            if ($score >= $this->threshold) {
                $results[] = new SearchResult($node, $score);
            }
        }

        usort($results, static fn (SearchResult $first, SearchResult $second): int => ($second->score <=> $first->score)
            ?: strcmp($first->node->path, $second->node->path));

        return $results;
    }

    /**
     * Return the average, over the query tokens, of the best similarity against the name tokens.
     *
     * @param  non-empty-list<string>  $queryTokens
     * @param  list<array<string, int>>  $queryVectors
     * @param  list<string>  $nameTokens
     */
    private function score(array $queryTokens, array $queryVectors, array $nameTokens): float
    {
        if ($nameTokens === []) {
            return 0.0;
        }

        $nameVectors = array_map($this->vectorizer->vectorize(...), $nameTokens);
        $total = 0.0;

        foreach ($queryTokens as $index => $queryToken) {
            $best = 0.0;

            foreach ($nameTokens as $nameIndex => $nameToken) {
                // A name token that starts with the query token is a full match. This supports type-ahead.
                $similarity = str_starts_with($nameToken, $queryToken)
                    ? self::FULL_MATCH
                    : $this->vectorizer->cosine($queryVectors[$index], $nameVectors[$nameIndex]);

                $best = max($best, $similarity);
            }

            $total += $best;
        }

        return $total / count($queryTokens);
    }
}
