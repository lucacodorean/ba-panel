<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Search\Tokenizer;

it('splits on separators and camelCase boundaries in lowercase', function (): void {
    expect((new Tokenizer)->tokenize('dataModel APIClient getting-started_v2'))
        ->toBe(['data', 'model', 'api', 'client', 'getting', 'started', 'v2']);
});

it('tokenizes text', function (string $text, array $tokens): void {
    expect((new Tokenizer)->tokenize($text))->toBe($tokens);
})->with([
    'single word' => ['readme', ['readme']],
    'upper case word' => ['README', ['readme']],
    'dots' => ['release.notes.2024', ['release', 'notes', '2024']],
    'pascal case' => ['DataModel', ['data', 'model']],
    'digit before upper case' => ['v2Api', ['v2', 'api']],
    'leading and trailing separators' => ['--api--', ['api']],
    'repeated separators' => ['a  __ b', ['a', 'b']],
]);

it('returns no tokens for text without letters or digits', function (string $text): void {
    expect((new Tokenizer)->tokenize($text))->toBe([]);
})->with(['', '   ', '-_./', "\t\n"]);
