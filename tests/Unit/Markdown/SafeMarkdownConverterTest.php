<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Markdown\SafeMarkdownConverter;

it('converts Markdown to HTML', function (): void {
    $html = (new SafeMarkdownConverter)->convert("# Title\n\nSome *text*.\n\n- one\n");

    expect($html)->toContain('<h1>Title</h1>')
        ->toContain('<em>text</em>')
        ->toContain('<li>one</li>');
});

it('escapes raw HTML', function (): void {
    $html = (new SafeMarkdownConverter)->convert("Hello <script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n");

    expect($html)->not->toContain('<script>')
        ->and($html)->not->toContain('<img')
        ->and($html)->toContain('&lt;script&gt;');
});

it('removes unsafe links', function (): void {
    $html = (new SafeMarkdownConverter)->convert('[click](javascript:alert(1)) and [ok](https://example.com)');

    expect($html)->not->toContain('javascript:')
        ->and($html)->toContain('href="https://example.com"');
});

it('converts tables', function (): void {
    $html = (new SafeMarkdownConverter)->convert("| A | B |\n|---|---|\n| 1 | x \\| y |\n");

    expect($html)->toContain('<table>')
        ->toContain('<th>A</th>')
        ->toContain('<td>x | y</td>');
});
