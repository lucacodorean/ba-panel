<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Graph\DocumentRenderer;
use Lucacodorean\BaPanel\Graph\Node;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk and forget it when $cleanup is true.
 */
function rendererFixtures(bool $cleanup = false): ?TemporaryFilesystem
{
    static $fs = null;

    if ($cleanup) {
        $fs?->cleanup();
        $fs = null;

        return null;
    }

    return $fs ??= new TemporaryFilesystem;
}

/**
 * Write the document and render it.
 */
function renderDocument(string $contents): string
{
    $path = (rendererFixtures() ?? throw new RuntimeException('No temporary filesystem.'))->file('docs/doc.md', $contents);

    return (new DocumentRenderer)->render(new Node('0123456789ab', 'doc', 'Doc', 'docs/doc.md', $path));
}

afterEach(function (): void {
    rendererFixtures(cleanup: true);
});

it('renders the markdown body as html', function (): void {
    $html = renderDocument("# Title\n\nSome *text*.\n\n- one\n- two\n");

    expect($html)->toContain('<h1>Title</h1>')
        ->toContain('<em>text</em>')
        ->toContain('<li>one</li>');
});

it('does not render the front matter', function (): void {
    $html = renderDocument("---\ntitle: Secret title\nrelations:\n  - other.md\n---\n# Body\n");

    expect($html)->toContain('<h1>Body</h1>')
        ->and($html)->not->toContain('Secret title')
        ->and($html)->not->toContain('relations')
        ->and($html)->not->toContain('other.md')
        ->and($html)->not->toContain('<hr');
});

it('renders the full contents when the front matter is invalid', function (): void {
    $html = renderDocument("---\ntitle: [unclosed\n---\n# Body\n");

    expect($html)->toContain('<h1>Body</h1>')
        ->toContain('unclosed');
});

it('escapes raw html', function (): void {
    $html = renderDocument("# Title\n\n<script>alert(1)</script>\n\nText <img src=x onerror=alert(1)>\n");

    expect($html)->not->toContain('<script>')
        ->and($html)->not->toContain('<img')
        ->toContain('&lt;script&gt;');
});

it('removes the href of an unsafe link', function (): void {
    $html = renderDocument("[click](javascript:alert(1))\n\n[data](data:text/html;base64,PHNjcmlwdD4=)\n");

    expect($html)->toContain('click')
        ->and($html)->not->toContain('javascript:')
        ->and($html)->not->toContain('href="data:');
});

it('keeps the href of a safe link', function (): void {
    expect(renderDocument("[site](https://example.com)\n"))->toContain('<a href="https://example.com">site</a>');
});

it('renders tables', function (): void {
    $html = renderDocument("| Name | Value |\n| --- | --- |\n| a | 1 |\n");

    expect($html)->toContain('<table>')
        ->toContain('<th>Name</th>')
        ->toContain('<td>1</td>');
});

it('renders code blocks', function (): void {
    $html = renderDocument("```php\necho '<b>';\n```\n");

    expect($html)->toContain('<pre><code class="language-php">')
        ->toContain('&lt;b&gt;');
});

it('returns an empty string when the file is missing', function (): void {
    $node = new Node('0123456789ab', 'missing', 'Missing', 'missing.md', '/does/not/exist/missing.md');

    expect((new DocumentRenderer)->render($node))->toBe('');
});

it('returns an empty string when the path is a directory', function (): void {
    $directory = (rendererFixtures() ?? throw new RuntimeException('No temporary filesystem.'))->directory('docs');

    expect((new DocumentRenderer)->render(new Node('0123456789ab', 'docs', 'Docs', 'docs', $directory)))->toBe('');
});

it('returns the markdown body without the front matter', function (): void {
    $path = (rendererFixtures() ?? throw new RuntimeException('No temporary filesystem.'))
        ->file('docs/doc.md', "---\ntitle: Secret title\n---\n# Body\n\nText <b>raw</b>.\n");

    $body = (new DocumentRenderer)->body(new Node('0123456789ab', 'doc', 'Doc', 'docs/doc.md', $path));

    expect($body)->toBeString()
        ->and($body)->toContain("# Body\n\nText <b>raw</b>.")
        ->and($body)->not->toContain('Secret title')
        ->and($body)->not->toContain('---');
});

it('returns the full contents as the body when there is no front matter', function (): void {
    $path = (rendererFixtures() ?? throw new RuntimeException('No temporary filesystem.'))->file('docs/doc.md', "# Only body\n");

    expect((new DocumentRenderer)->body(new Node('0123456789ab', 'doc', 'Doc', 'docs/doc.md', $path)))->toBe("# Only body\n");
});

it('returns null as the body when the file is missing', function (): void {
    $node = new Node('0123456789ab', 'missing', 'Missing', 'missing.md', '/does/not/exist/missing.md');

    expect((new DocumentRenderer)->body($node))->toBeNull();
});

it('returns null as the body when the path is a directory', function (): void {
    $directory = (rendererFixtures() ?? throw new RuntimeException('No temporary filesystem.'))->directory('docs');

    expect((new DocumentRenderer)->body(new Node('0123456789ab', 'docs', 'Docs', 'docs', $directory)))->toBeNull();
});
