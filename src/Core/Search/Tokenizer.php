<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Search;

/**
 * Split a file name or a query into lowercase tokens.
 */
final readonly class Tokenizer
{
    /**
     * Match a camelCase boundary, for example "dataModel" or "APIClient".
     */
    private const string CAMEL_CASE_PATTERN = '/(?<=[\p{Ll}\p{N}])(?=\p{Lu})|(?<=\p{Lu})(?=\p{Lu}\p{Ll})/u';

    /**
     * Match a run of characters that are not letters or digits.
     */
    private const string SEPARATOR_PATTERN = '/[^\p{L}\p{N}]+/u';

    private const string SPACE = ' ';

    /**
     * @return list<string>
     */
    public function tokenize(string $text): array
    {
        $text = preg_replace(self::CAMEL_CASE_PATTERN, self::SPACE, $text) ?? $text;
        $parts = preg_split(self::SEPARATOR_PATTERN, mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [] : $parts;
    }
}
