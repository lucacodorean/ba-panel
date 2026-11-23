<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports;

use BackedEnum;

/**
 * Format report values for the Markdown report view.
 */
final readonly class MarkdownText
{
    public const string NOT_SPECIFIED = 'Not specified';

    public const string NONE = 'None';

    private const string YES = 'Yes';

    private const string NO = 'No';

    private const string LIST_SEPARATOR = ', ';

    /**
     * Match line breaks and the white space around them.
     */
    private const string LINE_BREAKS_PATTERN = '/\s*\R\s*/u';

    /**
     * Return the value as one line for a Markdown table cell.
     * Escape the pipe character. Replace line breaks with a space.
     */
    public static function cell(string|int|float|bool|BackedEnum|null $value): string
    {
        return str_replace('|', '\|', self::inline($value));
    }

    /**
     * Return the value as one line, for example in a heading or a bullet. Replace line breaks with a space.
     */
    public static function inline(string|int|float|bool|BackedEnum|null $value): string
    {
        return (string) preg_replace(self::LINE_BREAKS_PATTERN, ' ', self::text($value));
    }

    /**
     * Return the value as a block of text. Return "Not specified" when the value is blank.
     */
    public static function text(string|int|float|bool|BackedEnum|null $value): string
    {
        $text = trim(match (true) {
            $value === null => '',
            is_bool($value) => $value ? self::YES : self::NO,
            $value instanceof BackedEnum => (string) $value->value,
            is_float($value) => self::number($value),
            default => (string) $value,
        });

        return $text === '' ? self::NOT_SPECIFIED : $text;
    }

    /**
     * Return the items as a Markdown bullet list, one item per line. Return "- None" when no item is given.
     * The indent puts the list inside an other list item.
     *
     * @param list<string> $items
     */
    public static function bullets(array $items, int $indent = 0): string
    {
        $prefix = str_repeat(' ', max(0, $indent)).'- ';
        $items = self::filled($items);

        if ($items === []) {
            return $prefix.self::NONE;
        }

        return implode("\n", array_map(static fn (string $item): string => $prefix.self::inline($item), $items));
    }

    /**
     * Return the items as a list inside a bullet, after the bullet label.
     * Return " None" when no item is given. Else return a line break and the items, indented by 2 spaces.
     *
     * @param list<string> $items
     */
    public static function nestedBullets(array $items): string
    {
        return self::filled($items) === [] ? ' '.self::NONE : "\n".self::bullets($items, 2);
    }

    /**
     * Return the items on one line for a Markdown table cell. Return "None" when no item is given.
     *
     * @param list<string> $items
     */
    public static function cellList(array $items): string
    {
        $items = self::filled($items);

        return $items === [] ? self::NONE : self::cell(implode(self::LIST_SEPARATOR, $items));
    }

    /**
     * Return the number without trailing zeros, for example "4" or "2.5".
     */
    public static function number(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        $text = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    /**
     * Remove blank items.
     *
     * @param list<string> $items
     * @return list<string>
     */
    private static function filled(array $items): array
    {
        return array_values(array_filter($items, static fn (string $item): bool => trim($item) !== ''));
    }
}
