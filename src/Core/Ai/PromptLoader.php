<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Ai;

use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;

/**
 * Select the prompt file, read it and return its text. The class has no fallback text.
 *
 * The precedence of the prompt file:
 * 1. The configured path. A missing configured file is an error. The class does not try the other files.
 * 2. The published path, when the host gives one and the file exists.
 * 3. The default path. This is the package prompt file.
 *
 * A relative path resolves against the base path of the host application. An absolute path is used as given.
 */
final readonly class PromptLoader
{
    /**
     * Match a Windows absolute path, for example "C:\" or "C:/".
     */
    private const string WINDOWS_ABSOLUTE_PATH_PATTERN = '/^[A-Za-z]:[\\\\\/]/';

    private const string UNIX_ROOT = '/';

    private const string TRAILING_SEPARATORS = '/\\';

    /**
     * Template for a missing prompt file. The argument is the path.
     */
    private const string MESSAGE_MISSING = 'The prompt file does not exist: %s';

    /**
     * Template for a prompt file that cannot be read. The argument is the path.
     */
    private const string MESSAGE_UNREADABLE = 'The prompt file is not readable: %s';

    /**
     * Template for a prompt file with no text. The argument is the path.
     */
    private const string MESSAGE_EMPTY = 'The prompt file is empty: %s';

    /**
     * @param string      $basePath      The base path of the host application. Laravel gives basePath(). Symfony gives the project directory.
     * @param string|null $publishedPath The path of the published prompt file. Null disables this step.
     * @param string      $defaultPath   The path of the package prompt file.
     */
    public function __construct(
        private string $basePath,
        private ?string $publishedPath = null,
        private string $defaultPath = AiSettings::DEFAULT_INSTRUCTIONS_PATH,
    ) {
    }

    /**
     * Return the text of the prompt file. The text is trimmed. The error messages show the resolved path.
     *
     * @param string|null $path The configured path. A null or blank value selects the published or the default file.
     *
     * @throws InvalidConfigurationException When the selected file is missing, not readable or empty.
     */
    public function load(?string $path = null): string
    {
        $path = $this->select($path);

        if (! is_file($path)) {
            throw new InvalidConfigurationException([sprintf(self::MESSAGE_MISSING, $path)]);
        }

        $contents = is_readable($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new InvalidConfigurationException([sprintf(self::MESSAGE_UNREADABLE, $path)]);
        }

        $text = trim($contents);

        if ($text === '') {
            throw new InvalidConfigurationException([sprintf(self::MESSAGE_EMPTY, $path)]);
        }

        return $text;
    }

    /**
     * Select the resolved path of the prompt file. See the precedence in the class comment.
     */
    private function select(?string $path): string
    {
        if ($path !== null && trim($path) !== '') {
            return $this->resolve($path);
        }

        if ($this->publishedPath !== null && trim($this->publishedPath) !== '') {
            $published = $this->resolve($this->publishedPath);

            if (is_file($published)) {
                return $published;
            }
        }

        return $this->resolve($this->defaultPath);
    }

    /**
     * Resolve a relative path against the base path. Keep an absolute path as given.
     */
    private function resolve(string $path): string
    {
        if (str_starts_with($path, self::UNIX_ROOT) || preg_match(self::WINDOWS_ABSOLUTE_PATH_PATTERN, $path) === 1) {
            return $path;
        }

        return rtrim($this->basePath, self::TRAILING_SEPARATORS).DIRECTORY_SEPARATOR.$path;
    }
}
