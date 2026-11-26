<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keep each log record in memory. The logger writes no file and no output.
 */
final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{level: string, message: string, context: array<mixed>}>
     */
    public array $records = [];

    /**
     * @param  array<mixed>  $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => is_string($level) ? $level : 'unknown', 'message' => (string) $message, 'context' => $context];
    }

    /**
     * Return the records of the level.
     *
     * @return list<array{level: string, message: string, context: array<mixed>}>
     */
    public function recordsOf(string $level): array
    {
        return array_values(array_filter($this->records, static fn (array $record): bool => $record['level'] === $level));
    }
}
