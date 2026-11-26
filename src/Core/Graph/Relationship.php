<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Graph;

/**
 * A directed link between two documents. The source declares the target.
 * A bidirectional link is a link that both documents declare. One relationship is kept per pair of documents.
 */
final readonly class Relationship
{
    /**
     * @param Node $source The document of the first declaration.
     * @param Node $target The declared document.
     * @param bool $bidirectional True when the target also declares the source.
     */
    public function __construct(
        private Node $source,
        private Node $target,
        private bool $bidirectional = false,
    ) {
    }

    /**
     * Return the document that declares the link. For a bidirectional link, this is the first declaration.
     */
    public function source(): Node
    {
        return $this->source;
    }

    /**
     * Return the declared document.
     */
    public function target(): Node
    {
        return $this->target;
    }

    /**
     * Return true when the two documents declare each other.
     */
    public function bidirectional(): bool
    {
        return $this->bidirectional;
    }

    /**
     * Return true when the node is one of the two ends. Nodes are compared by id.
     */
    public function connects(Node $node): bool
    {
        return $this->source->id === $node->id || $this->target->id === $node->id;
    }

    /**
     * Return true when the link can be followed from the node.
     * This is the source, or the target of a bidirectional link. Nodes are compared by id.
     */
    public function leadsFrom(Node $node): bool
    {
        return $this->source->id === $node->id || ($this->bidirectional && $this->target->id === $node->id);
    }

    /**
     * Return the node at the other end. Return null when the node is not one of the two ends.
     */
    public function other(Node $node): ?Node
    {
        return match ($node->id) {
            $this->source->id => $this->target,
            $this->target->id => $this->source,
            default => null,
        };
    }

    /**
     * @return array{source: string, target: string, bidirectional: bool}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source->id,
            'target' => $this->target->id,
            'bidirectional' => $this->bidirectional,
        ];
    }
}
