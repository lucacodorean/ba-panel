<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Convert Markdown to HTML that is safe to show in the panel.
 */
final readonly class SafeMarkdownConverter
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

    private MarkdownConverter $converter;

    /**
     * The CommonMark converter is an internal part of this adapter. This class owns the safe settings, so the converter is not injected.
     */
    public function __construct()
    {
        $environment = new Environment(self::CONVERTER_CONFIG);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Return the HTML of the Markdown text.
     */
    public function convert(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
