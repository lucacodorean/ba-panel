<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Graph;

/**
 * Build the documentation graph from the configured paths.
 */
final readonly class GraphBuilder
{
    private const string METADATA_TITLE = 'title';

    private const string METADATA_RELATIONS = 'relations';

    private const string MARKDOWN_EXTENSION = 'md';

    private const int ID_LENGTH = 12;

    /**
     * Match the first level-one ATX heading, for example "# Title".
     */
    private const string HEADING_PATTERN = '/^ {0,3}#[ \t]+(.+?)(?:[ \t]+#+)?[ \t]*$/m';

    /**
     * Match a Windows absolute path, for example "C:\" or "C:/".
     */
    private const string WINDOWS_ABSOLUTE_PATH_PATTERN = '/^[A-Za-z]:[\\\\\/]/';

    private const string UNIX_ROOT = '/';

    private const string TRAILING_SEPARATORS = '/\\';

    private const string HIDDEN_PREFIX = '.';

    private const string PAIR_SEPARATOR = '|';

    /**
     * @param  list<string>  $paths  The configured files and directories.
     */
    public function __construct(
        private array $paths,
        private string $basePath,
        private FrontMatterParser $frontMatter,
    ) {}

    /**
     * Scan the paths and return the graph. Missing paths and invalid relations are skipped.
     */
    public function build(): Graph
    {
        $files = $this->scan();

        /** @var array<string, Node> $nodes Nodes keyed by the real path. */
        $nodes = [];

        /** @var array<string, list<string>> $relations Relation values keyed by the real path of the source. */
        $relations = [];

        foreach ($files as $realPath) {
            $contents = @file_get_contents($realPath);

            if ($contents === false) {
                continue;
            }

            ['metadata' => $metadata, 'body' => $body] = $this->frontMatter->parse($contents);

            $nodes[$realPath] = $this->createNode($realPath, $metadata, $body);
            $relations[$realPath] = $this->relationValues($metadata);
        }

        /** @var array<string, Relationship> $relationships Relationships keyed by the sorted pair of node ids. */
        $relationships = [];

        // A relation is directed. Keep one relationship per pair, in the direction of the first declaration.
        foreach ($relations as $sourcePath => $values) {
            $source = $nodes[$sourcePath];

            foreach ($values as $value) {
                $targetPath = $this->resolveRelation($sourcePath, $value);

                // Skip a missing target, a target outside the scan, and a self link.
                if ($targetPath === null || ! isset($nodes[$targetPath]) || $targetPath === $sourcePath) {
                    continue;
                }

                $target = $nodes[$targetPath];
                $key = $this->pairKey($source, $target);
                $existing = $relationships[$key] ?? null;

                // Add a new pair. When the target declares the source too, mark the pair as bidirectional.
                // Skip a duplicate declaration from the same document.
                if ($existing === null) {
                    $relationships[$key] = new Relationship($source, $target);
                } elseif ($existing->source()->id === $target->id) {
                    $relationships[$key] = new Relationship($existing->source(), $existing->target(), true);
                }
            }
        }

        return new Graph(array_values($nodes), array_values($relationships));
    }

    /**
     * Return the key of a pair, in any direction: the two node ids, sorted.
     */
    private function pairKey(Node $first, Node $second): string
    {
        $ids = [$first->id, $second->id];
        sort($ids, SORT_STRING);

        return implode(self::PAIR_SEPARATOR, $ids);
    }

    /**
     * Return the real paths of all Markdown files, without duplicates, sorted by path.
     *
     * @return list<string>
     */
    private function scan(): array
    {
        /** @var array<string, true> $files */
        $files = [];

        /** @var array<string, true> $visited */
        $visited = [];

        foreach ($this->paths as $path) {
            $realPath = realpath($this->resolve($path));

            if ($realPath === false) {
                continue;
            }

            if (is_dir($realPath)) {
                $this->scanDirectory($realPath, $files, $visited);
            } elseif ($this->isMarkdownFile($realPath)) {
                $files[$realPath] = true;
            }
        }

        $paths = array_keys($files);
        sort($paths, SORT_STRING);

        return $paths;
    }

    /**
     * Add the Markdown files of the directory and its subdirectories. Skip hidden entries.
     *
     * @param  array<string, true>  $files
     * @param  array<string, true>  $visited  The directories already scanned. This stops symbolic link loops.
     */
    private function scanDirectory(string $directory, array &$files, array &$visited): void
    {
        if (isset($visited[$directory])) {
            return;
        }

        $visited[$directory] = true;

        foreach (@scandir($directory) ?: [] as $entry) {
            if (str_starts_with($entry, self::HIDDEN_PREFIX)) {
                continue;
            }

            $realPath = realpath($directory.DIRECTORY_SEPARATOR.$entry);

            if ($realPath === false) {
                continue;
            }

            if (is_dir($realPath)) {
                $this->scanDirectory($realPath, $files, $visited);
            } elseif ($this->isMarkdownFile($realPath)) {
                $files[$realPath] = true;
            }
        }
    }

    private function isMarkdownFile(string $path): bool
    {
        return is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === self::MARKDOWN_EXTENSION;
    }

    /**
     * @param  array<array-key, mixed>  $metadata
     */
    private function createNode(string $realPath, array $metadata, string $body): Node
    {
        $name = pathinfo($realPath, PATHINFO_FILENAME);

        return new Node(
            id: substr(sha1($realPath), 0, self::ID_LENGTH),
            name: $name,
            title: $this->title($metadata, $body, $name),
            path: $this->displayPath($realPath),
            absolutePath: $realPath,
        );
    }

    /**
     * Return the front matter title. Fall back to the first "# " heading, then to the name.
     *
     * @param  array<array-key, mixed>  $metadata
     */
    private function title(array $metadata, string $body, string $name): string
    {
        $title = $metadata[self::METADATA_TITLE] ?? null;

        if (is_string($title) && trim($title) !== '') {
            return trim($title);
        }

        if (preg_match(self::HEADING_PATTERN, $body, $matches) === 1) {
            return trim($matches[1]);
        }

        return $name;
    }

    /**
     * Return the relation values as strings. Ignore values of other types.
     *
     * @param  array<array-key, mixed>  $metadata
     * @return list<string>
     */
    private function relationValues(array $metadata): array
    {
        $relations = $metadata[self::METADATA_RELATIONS] ?? [];

        if (! is_array($relations)) {
            return [];
        }

        $values = [];

        foreach ($relations as $relation) {
            if (is_string($relation) && trim($relation) !== '') {
                $values[] = trim($relation);
            }
        }

        return $values;
    }

    /**
     * Resolve a relation against the directory of the source document. Return null when the target does not exist.
     */
    private function resolveRelation(string $sourcePath, string $relation): ?string
    {
        $target = $this->isAbsolute($relation)
            ? $relation
            : dirname($sourcePath).DIRECTORY_SEPARATOR.$relation;

        $realPath = realpath($target);

        return $realPath === false ? null : $realPath;
    }

    /**
     * Return the path relative to the base path, with forward slashes. Keep the absolute path when it is outside.
     */
    private function displayPath(string $realPath): string
    {
        $base = realpath($this->basePath);
        $base = rtrim($base === false ? $this->basePath : $base, self::TRAILING_SEPARATORS).DIRECTORY_SEPARATOR;

        $path = str_starts_with($realPath, $base) ? substr($realPath, strlen($base)) : $realPath;

        return str_replace('\\', '/', $path);
    }

    /**
     * Resolve a relative path against the base path. Keep an absolute path as given.
     */
    private function resolve(string $path): string
    {
        if ($this->isAbsolute($path)) {
            return $path;
        }

        return rtrim($this->basePath, self::TRAILING_SEPARATORS).DIRECTORY_SEPARATOR.$path;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, self::UNIX_ROOT) || preg_match(self::WINDOWS_ABSOLUTE_PATH_PATTERN, $path) === 1;
    }
}
