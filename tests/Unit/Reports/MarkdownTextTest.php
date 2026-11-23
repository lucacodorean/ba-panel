<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Reports\Enums\Priority;
use Lucacodorean\BaPanel\Reports\MarkdownText;

it('escapes the pipe character in a cell', function (): void {
    expect(MarkdownText::cell('a | b|c'))->toBe('a \| b\|c');
});

it('replaces line breaks with one space in a cell', function (): void {
    expect(MarkdownText::cell("first line\n  second line\r\nthird"))->toBe('first line second line third');
});

it('formats scalar and enum values', function (string|int|float|bool|Priority|null $value, string $expected): void {
    expect(MarkdownText::cell($value))->toBe($expected)
        ->and(MarkdownText::inline($value))->toBe($expected)
        ->and(MarkdownText::text($value))->toBe($expected);
})->with([
    'null' => [null, 'Not specified'],
    'empty string' => ['', 'Not specified'],
    'blank string' => ["  \n ", 'Not specified'],
    'true' => [true, 'Yes'],
    'false' => [false, 'No'],
    'enum' => [Priority::High, 'high'],
    'integer' => [3, '3'],
    'zero' => [0, '0'],
    'float with zero fraction' => [4.0, '4'],
    'float with fraction' => [2.5, '2.5'],
    'string' => ['  value  ', 'value'],
]);

it('does not escape the pipe character on one line', function (): void {
    expect(MarkdownText::inline("a | b\nc"))->toBe('a | b c');
});

it('keeps the line breaks in a block of text', function (): void {
    expect(MarkdownText::text("\nFirst paragraph.\n\nSecond paragraph.\n"))->toBe("First paragraph.\n\nSecond paragraph.");
});

it('returns a bullet per item', function (): void {
    expect(MarkdownText::bullets(['one', 'two']))->toBe("- one\n- two");
});

it('returns "- None" for an empty list', function (): void {
    expect(MarkdownText::bullets([]))->toBe('- None');
});

it('removes blank items from a bullet list', function (): void {
    expect(MarkdownText::bullets(['', '  ']))->toBe('- None')
        ->and(MarkdownText::bullets(['one', ' ', 'two']))->toBe("- one\n- two");
});

it('puts each bullet item on one line', function (): void {
    expect(MarkdownText::bullets(["first\nsecond"]))->toBe('- first second');
});

it('indents the bullets', function (): void {
    expect(MarkdownText::bullets(['one', 'two'], 2))->toBe("  - one\n  - two")
        ->and(MarkdownText::bullets([], 2))->toBe('  - None')
        ->and(MarkdownText::bullets(['one'], -3))->toBe('- one');
});

it('returns nested bullets after a line break', function (): void {
    expect(MarkdownText::nestedBullets(['one', 'two']))->toBe("\n  - one\n  - two");
});

it('returns " None" for an empty nested list', function (): void {
    expect(MarkdownText::nestedBullets([]))->toBe(' None')
        ->and(MarkdownText::nestedBullets(['  ']))->toBe(' None');
});

it('joins a list in one cell', function (): void {
    expect(MarkdownText::cellList(['FIN-1', 'a|b', '']))->toBe('FIN-1, a\|b');
});

it('returns "None" for an empty cell list', function (): void {
    expect(MarkdownText::cellList([]))->toBe('None')
        ->and(MarkdownText::cellList([' ']))->toBe('None');
});

it('formats numbers without trailing zeros', function (int|float $value, string $expected): void {
    expect(MarkdownText::number($value))->toBe($expected);
})->with([
    'integer' => [12, '12'],
    'whole float' => [12.0, '12'],
    'one decimal' => [1.20, '1.2'],
    'two decimals' => [0.25, '0.25'],
    'large value' => [1000.5, '1000.5'],
    'negative zero' => [-0.0, '0'],
    'rounded to two decimals' => [1.004, '1'],
]);
