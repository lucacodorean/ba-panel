<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Graph;

use Lucacodorean\BaPanel\Markdown\SafeMarkdownConverter;

/**
 * Render the Markdown document of a node as HTML.
 */
final readonly class DocumentRenderer
{
    public function __construct(
        private FrontMatterParser $frontMatter,
        private SafeMarkdownConverter $converter,
    ) {
    }

    /**
     * Return the HTML of the document body. The front matter is not rendered.
     * The file is read only from the node path. Return an empty string when the file cannot be read.
     */
    public function render(Node $node): string
    {
        $body = $this->body($node);

        return $body === null ? '' : $this->converter->convert($body);
    }

    /**
     * Return the Markdown body of the document without the front matter.
     * The file is read only from the node path. Return null when the file cannot be read.
     */
    public function body(Node $node): ?string
    {
        $contents = is_file($node->absolutePath) ? @file_get_contents($node->absolutePath) : false;

        if ($contents === false) {
            return null;
        }

        return $this->frontMatter->parse($contents)['body'];
    }
}
