<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Exceptions\InvalidTemplateException;
use Lucacodorean\BaPanel\Core\View\TemplateRenderer;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;

/**
 * Return the temporary filesystem for the current test. Create it on first use.
 * Remove it from disk and forget it when $cleanup is true.
 */
function templateFixtures(bool $cleanup = false): ?TemporaryFilesystem
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
 * Write the template file under the directory and return the file path.
 */
function templateFile(string $directory, string $name, string $contents): string
{
    $fs = templateFixtures() ?? throw new RuntimeException('No temporary filesystem.');

    return $fs->file($directory.'/'.$name.'.php', $contents);
}

/**
 * Return a renderer for the given directories of the temporary filesystem.
 */
function templateRenderer(string ...$directories): TemplateRenderer
{
    $fs = templateFixtures() ?? throw new RuntimeException('No temporary filesystem.');

    return new TemplateRenderer(array_values(array_map(static fn (string $directory): string => $fs->path($directory), $directories)));
}

afterEach(function (): void {
    templateFixtures(cleanup: true);
});

it('renders the template with the data as variables', function (): void {
    templateFile('views', 'greeting', 'Hello <?= $name ?>, you have <?= $count ?> files.');

    expect(templateRenderer('views')->render('greeting', ['name' => 'Ana', 'count' => 3]))->toBe('Hello Ana, you have 3 files.');
});

it('renders a template without data', function (): void {
    templateFile('views', 'static', 'Static text.');

    expect(templateRenderer('views')->render('static'))->toBe('Static text.');
});

it('renders a template in a sub directory', function (): void {
    templateFile('views', 'reports/summary', 'Summary');

    expect(templateRenderer('views')->render('reports/summary'))->toBe('Summary');
});

it('escapes HTML with the $e helper', function (mixed $value, string $expected): void {
    templateFile('views', 'escape', '<?= $e($value) ?>');

    expect(templateRenderer('views')->render('escape', ['value' => $value]))->toBe($expected);
})->with([
    'tags and ampersand' => ['<b>&</b>', '&lt;b&gt;&amp;&lt;/b&gt;'],
    'double quote' => ['"', '&quot;'],
    'single quote' => ["'", '&#039;'],
    'integer' => [42, '42'],
    'float' => [1.5, '1.5'],
    'true' => [true, '1'],
    'false' => [false, ''],
    'null' => [null, ''],
    'invalid UTF-8' => ["a\xC3", "a\u{FFFD}"],
]);

it('escapes a Stringable value with the $e helper', function (): void {
    templateFile('views', 'escape', '<?= $e($value) ?>');
    $value = new class implements Stringable
    {
        public function __toString(): string
        {
            return '<i>';
        }
    };

    expect(templateRenderer('views')->render('escape', ['value' => $value]))->toBe('&lt;i&gt;');
});

it('encodes script-safe JSON with the $js helper', function (): void {
    templateFile('views', 'script', '<?= $js($value) ?>');

    $output = templateRenderer('views')->render('script', ['value' => ['text' => "</script><b>&'\"", 'path' => 'a/b', 'name' => 'é']]);

    expect($output)->toBe('{"text":"\u003C\/script\u003E\u003Cb\u003E\u0026\u0027\u0022","path":"a\/b","name":"é"}')
        ->and($output)->not->toContain('</script>')
        ->and(json_decode($output, true))->toBe(['text' => "</script><b>&'\"", 'path' => 'a/b', 'name' => 'é']);
});

it('encodes scalar values with the $js helper', function (mixed $value, string $expected): void {
    templateFile('views', 'script', '<?= $js($value) ?>');

    expect(templateRenderer('views')->render('script', ['value' => $value]))->toBe($expected);
})->with([
    'string' => ['/ba-panel', '"\/ba-panel"'],
    'integer' => [50, '50'],
    'null' => [null, 'null'],
    'empty list' => [[], '[]'],
]);

it('throws when the $js helper cannot encode the value', function (): void {
    templateFile('views', 'script', '<?= $js($value) ?>');

    expect(fn () => templateRenderer('views')->render('script', ['value' => "a\xC3"]))->toThrow(JsonException::class);
});

it('does not let a data key replace a helper', function (): void {
    templateFile('views', 'helpers', '<?= $e("<b>") ?> <?= $js("x") ?>');

    $output = templateRenderer('views')->render('helpers', ['e' => 'replaced', 'js' => 'replaced']);

    expect($output)->toBe('&lt;b&gt; "x"');
});

it('does not give the renderer variables to the template', function (): void {
    templateFile('views', 'scope', '<?= implode(",", array_keys(get_defined_vars())) ?>|<?= isset($this) ? "this" : "no-this" ?>');

    expect(templateRenderer('views')->render('scope', ['first' => 1]))->toBe('e,js,first|no-this');
});

it('uses the template of the first directory that has it', function (): void {
    templateFile('host', 'panel', 'Host');
    templateFile('package', 'panel', 'Package');
    templateFile('package', 'other', 'Package other');

    $renderer = templateRenderer('host', 'package');

    expect($renderer->render('panel'))->toBe('Host')
        ->and($renderer->render('other'))->toBe('Package other')
        ->and(templateRenderer('package', 'host')->render('panel'))->toBe('Package');
});

it('skips a directory that does not exist', function (): void {
    templateFile('package', 'panel', 'Package');

    expect(templateRenderer('missing', 'package')->render('panel'))->toBe('Package');
});

it('accepts a directory with a trailing separator', function (): void {
    $fs = templateFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    templateFile('views', 'panel', 'Panel');

    expect((new TemplateRenderer([$fs->path('views').'/']))->render('panel'))->toBe('Panel');
});

it('returns the path of the resolved template', function (): void {
    $path = templateFile('views', 'reports/feasibility', 'Report');

    expect(templateRenderer('views')->resolve('reports/feasibility'))->toBe($path);
});

it('accepts a valid template name', function (string $name): void {
    templateFile('views', $name, 'ok');

    expect(templateRenderer('views')->render($name))->toBe('ok');
})->with([
    'letters' => ['panel'],
    'digits' => ['panel2'],
    'underscore and dash' => ['my_panel-v2'],
    'upper case' => ['Panel'],
    'nested' => ['a/b/c'],
]);

it('refuses an invalid template name', function (string $name): void {
    templateFile('views', 'panel', 'Panel');

    expect(fn () => templateRenderer('views')->render($name))
        ->toThrow(InvalidTemplateException::class, sprintf('The template name is not valid: "%s".', $name));
})->with([
    'empty' => [''],
    'parent directory' => ['../panel'],
    'parent segment' => ['reports/../panel'],
    'dot segment' => ['./panel'],
    'absolute path' => ['/etc/passwd'],
    'php extension' => ['panel.php'],
    'blade extension' => ['panel.blade'],
    'namespace' => ['ba-panel::panel'],
    'backslash' => ['reports\\feasibility'],
    'trailing slash' => ['panel/'],
    'double slash' => ['reports//feasibility'],
    'final line break' => ["panel\n"],
    'null byte' => ["panel\0"],
    'space' => ['my panel'],
]);

it('refuses an invalid name before it searches the directories', function (): void {
    templateFile('views', 'secret', 'Secret');

    // The file exists one level up from "views/reports". The renderer still refuses the name.
    expect(fn () => templateRenderer('views/reports')->resolve('../secret'))->toThrow(InvalidTemplateException::class);
});

it('throws when no directory has the template', function (): void {
    templateFile('views', 'panel', 'Panel');

    expect(fn () => templateRenderer('views')->render('missing'))
        ->toThrow(InvalidTemplateException::class, 'The template does not exist: "missing".');
});

it('throws when there are no directories', function (): void {
    expect(fn () => (new TemplateRenderer([]))->render('panel'))->toThrow(InvalidTemplateException::class);
});

it('does not use a directory with the template name', function (): void {
    $fs = templateFixtures() ?? throw new RuntimeException('No temporary filesystem.');
    $fs->directory('views/panel.php');

    expect(fn () => templateRenderer('views')->render('panel'))->toThrow(InvalidTemplateException::class);
});

it('restores the output buffer level when the template throws', function (): void {
    templateFile('views', 'broken', 'Before <?php ob_start(); ?>inner<?php throw new RuntimeException("Template failed."); ?>');
    $level = ob_get_level();

    expect(fn () => templateRenderer('views')->render('broken'))->toThrow(RuntimeException::class, 'Template failed.')
        ->and(ob_get_level())->toBe($level);
});

it('does not send the output of a failed template', function (): void {
    templateFile('views', 'broken', 'Partial output<?php throw new RuntimeException("Template failed."); ?>');

    ob_start();

    try {
        templateRenderer('views')->render('broken');
    } catch (RuntimeException) {
        // The test checks the output below.
    }

    expect((string) ob_get_clean())->toBe('');
});

it('renders the same template again after a failure', function (): void {
    templateFile('views', 'maybe', '<?php if ($fail) { throw new RuntimeException("Failed."); } ?>ok');
    $renderer = templateRenderer('views');

    expect(fn () => $renderer->render('maybe', ['fail' => true]))->toThrow(RuntimeException::class)
        ->and($renderer->render('maybe', ['fail' => false]))->toBe('ok');
});

it('renders the package templates', function (string $name): void {
    $renderer = new TemplateRenderer([dirname(__DIR__, 3).'/resources/views']);

    expect(is_file($renderer->resolve($name)))->toBeTrue();
})->with([
    'panel' => ['panel'],
    'report' => ['reports/feasibility'],
]);

it('has no Blade templates in the package', function (): void {
    $files = glob(dirname(__DIR__, 3).'/resources/views/{,*/}*.blade.php', GLOB_BRACE);

    expect($files)->toBe([]);
});
