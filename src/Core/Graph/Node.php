<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Graph;

/**
 * A Markdown document in the documentation graph.
 */
final readonly class Node
{
    /**
     * @param  string  $id  The first 12 hex characters of sha1(realpath).
     * @param  string  $name  The file name without the extension.
     * @param  string  $title  The front matter title, the first heading, or the name.
     * @param  string  $path  The display path. It is relative to the base path when possible.
     * @param  string  $absolutePath  The real path on disk. Do not send it to the browser.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $title,
        public string $path,
        public string $absolutePath,
    ) {}

    /**
     * Return the public data. The absolute path is not included.
     *
     * @return array{id: string, name: string, title: string, path: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'path' => $this->path,
        ];
    }
}
