<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Tests\Support;

use RuntimeException;

/**
 * Create a temporary directory tree for a test and remove it afterwards.
 */
final class TemporaryFilesystem
{
    private readonly string $root;

    public function __construct()
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ba-panel-tests-'.bin2hex(random_bytes(8));

        if (! mkdir($root, 0777, true) && ! is_dir($root)) {
            throw new RuntimeException('Cannot create temporary directory: '.$root);
        }

        $this->root = $root;
    }

    public function root(): string
    {
        return $this->root;
    }

    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->root : $this->root.DIRECTORY_SEPARATOR.$relative;
    }

    public function file(string $relative, string $contents = ''): string
    {
        $path = $this->path($relative);
        $this->ensureDirectory(dirname($path));
        file_put_contents($path, $contents);

        return $path;
    }

    public function directory(string $relative): string
    {
        $path = $this->path($relative);
        $this->ensureDirectory($path);

        return $path;
    }

    public function cleanup(): void
    {
        $this->remove($this->root);
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new RuntimeException('Cannot create directory: '.$path);
        }
    }

    private function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->remove($path.DIRECTORY_SEPARATOR.$entry);
        }

        rmdir($path);
    }
}
