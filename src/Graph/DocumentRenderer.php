<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Graph;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Render the Markdown document of a node as HTML.
 */
final class DocumentRenderer
{
    /**
     * Escape raw HTML. Remove unsafe links, for example "javascript:".
     *
     * @var array{html_input: string, allow_unsafe_links: bool}
     */
    private const array CONVERTER_CONFIG = [
        'html_input' => 'escape',
        'allow_unsafe_links' => false,
    ];

    private readonly MarkdownConverter $converter;

    public function __construct(private readonly FrontMatterParser $frontMatter = new FrontMatterParser())
    {
        $environment = new Environment(self::CONVERTER_CONFIG);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Return the HTML of the document body. The front matter is not rendered.
     * The file is read only from the node path. Return an empty string when the file cannot be read.
     */
    public function render(Node $node): string
    {
        $contents = is_file($node->absolutePath) ? @file_get_contents($node->absolutePath) : false;

        if ($contents === false) {
            return '';
        }

        $body = $this->frontMatter->parse($contents)['body'];

        return $this->converter->convert($body)->getContent();
    }
}
