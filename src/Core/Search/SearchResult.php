<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Search;

use Lucacodorean\BaPanel\Core\Graph\Node;

/**
 * A node that matches a search query, with its score.
 */
final readonly class SearchResult
{
    private const int SCORE_PRECISION = 4;

    /**
     * @param float $score The similarity, from 0 to 1.
     */
    public function __construct(
        public Node $node,
        public float $score,
    ) {
    }

    /**
     * @return array{id: string, name: string, path: string, score: float}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->node->id,
            'name' => $this->node->name,
            'path' => $this->node->path,
            'score' => round($this->score, self::SCORE_PRECISION),
        ];
    }
}
