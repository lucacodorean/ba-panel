<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Graph;

use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser as CommonMarkFrontMatterParser;

/**
 * Split a Markdown document into the YAML front matter and the body.
 */
final class FrontMatterParser
{
    private readonly CommonMarkFrontMatterParser $parser;

    public function __construct()
    {
        $this->parser = new CommonMarkFrontMatterParser(new SymfonyYamlFrontMatterParser());
    }

    /**
     * Return the metadata and the body. Invalid or missing front matter gives empty metadata and the full contents.
     *
     * @return array{metadata: array<array-key, mixed>, body: string}
     */
    public function parse(string $contents): array
    {
        try {
            $input = $this->parser->parse($contents);
        } catch (InvalidFrontMatterException) {
            return ['metadata' => [], 'body' => $contents];
        }

        $metadata = $input->getFrontMatter();

        // A YAML scalar, for example a single string, is not metadata.
        return [
            'metadata' => is_array($metadata) ? $metadata : [],
            'body' => $input->getContent(),
        ];
    }
}
