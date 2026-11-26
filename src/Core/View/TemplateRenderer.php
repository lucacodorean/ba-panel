<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\View;

use Closure;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidTemplateException;
use Stringable;
use Throwable;

/**
 * Render a plain PHP template to a string.
 *
 * The renderer searches the directories in the given order. The first directory that contains the template wins.
 * Thus a host directory before the package directory overrides a package template.
 *
 * Each template gets the data as variables, and these helpers:
 * - $e: escape a value for HTML.
 * - $js: encode a value as JSON that is safe in a <script> block.
 */
final readonly class TemplateRenderer
{
    private const string EXTENSION = '.php';

    /**
     * Match a relative name of letters, digits, "_" and "-" segments, for example "panel" or "reports/feasibility".
     * The pattern refuses "..", absolute names and file extensions.
     */
    private const string NAME_PATTERN = '/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*\z/';

    private const int JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    private const string INVALID_NAME = 'The template name is not valid: "%s".';

    private const string NOT_FOUND = 'The template does not exist: "%s".';

    private const string TRAILING_SEPARATORS = '/\\';

    /**
     * @param list<string> $directories The template directories, in search order.
     */
    public function __construct(private array $directories)
    {
    }

    /**
     * Return the output of the template. The data keys become variables. A key does not replace a helper.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidTemplateException When the name is not valid or no directory contains the template.
     * @throws Throwable When the template fails. The output of the template is discarded.
     */
    public function render(string $template, array $data = []): string
    {
        $path = $this->resolve($template);
        $level = ob_get_level();

        ob_start();

        try {
            self::evaluate($path, $data, self::escaper(), self::jsonEncoder());

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $exception;
        }
    }

    /**
     * Return the path of the template file in the first directory that contains it.
     *
     * @throws InvalidTemplateException
     */
    public function resolve(string $template): string
    {
        if (preg_match(self::NAME_PATTERN, $template) !== 1) {
            throw new InvalidTemplateException(sprintf(self::INVALID_NAME, $template));
        }

        foreach ($this->directories as $directory) {
            $path = rtrim($directory, self::TRAILING_SEPARATORS).'/'.$template.self::EXTENSION;

            if (is_file($path)) {
                return $path;
            }
        }

        throw new InvalidTemplateException(sprintf(self::NOT_FOUND, $template));
    }

    /**
     * Include the template in an isolated scope. The scope has no $this and no variables of the renderer.
     *
     * @param array<string, mixed> $data
     */
    private static function evaluate(string $path, array $data, Closure $e, Closure $js): void
    {
        $scope = static function (Closure $e, Closure $js): void {
            // EXTR_SKIP keeps the helpers when the data has the same key.
            extract(func_get_arg(3), EXTR_SKIP);

            require func_get_arg(2);
        };

        // The path and the data are not declared parameters. Thus they are not variables in the template.
        $scope($e, $js, $path, $data); // @phpstan-ignore arguments.count
    }

    /**
     * @return Closure(string|int|float|bool|Stringable|null): string
     */
    private static function escaper(): Closure
    {
        return static fn (string|int|float|bool|Stringable|null $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @return Closure(mixed): string
     */
    private static function jsonEncoder(): Closure
    {
        return static fn (mixed $value): string => json_encode($value, self::JSON_FLAGS);
    }
}
