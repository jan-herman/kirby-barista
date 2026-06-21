<?php declare(strict_types=1);

use JanHerman\Barista\LatteExtension;
use Kirby\Cms\App;
use Latte\Engine;
use Latte\Loaders\StringLoader;

$autoloads = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 5) . '/vendor/autoload.php',
];

foreach ($autoloads as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}

if (!class_exists(Engine::class)) {
    fwrite(STDERR, "Unable to load Composer autoload.\n");
    exit(1);
}

function bootKirbyForLayoutTests(array $barista_options = []): void
{
    App::destroy();

    $base = sys_get_temp_dir() . '/barista-implicit-layout-tests';
    foreach (['site', 'config', 'plugins', 'content', 'media'] as $directory) {
        if (!is_dir($base . '/' . $directory)) {
            mkdir($base . '/' . $directory, 0777, true);
        }
    }

    new App([
        'roots' => [
            'index' => $base,
            'site' => $base . '/site',
            'config' => $base . '/config',
            'plugins' => $base . '/plugins',
            'content' => $base . '/content',
            'media' => $base . '/media',
        ],
        'options' => [
            'jan-herman.barista.implicitEmbedBlock' => true,
            'jan-herman.barista.implicitEmbedBlockName' => 'default',
            'jan-herman.barista.implicitLayoutBlock' => $barista_options['implicitLayoutBlock'] ?? true,
            'jan-herman.barista.implicitLayoutBlockName' => $barista_options['implicitLayoutBlockName'] ?? 'default',
            'jan-herman.barista.tags' => $barista_options['tags'] ?? [],
        ],
    ]);
}

function renderLayoutTemplate(string $main, string $layout = '', array $params = [], array $barista_options = []): string
{
    bootKirbyForLayoutTests($barista_options);

    $latte = new Engine();
    $latte->addExtension(new LatteExtension());
    $latte->setLoader(new StringLoader([
        'main' => $main,
        'layout' => $layout,
    ]));

    return $latte->renderToString('main', $params);
}

function assertSameLayoutValue(string $label, string $expected, string $actual): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "$label failed.\nExpected: $expected\nActual:   $actual\n");
        exit(1);
    }
}

function assertLayoutThrows(string $label, string $expected_message, callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if (str_contains($exception->getMessage(), $expected_message)) {
            return;
        }

        fwrite(STDERR, "$label failed with unexpected exception.\n" . get_class($exception) . ': ' . $exception->getMessage() . "\n");
        exit(1);
    }

    fwrite(STDERR, "$label failed. Expected exception containing: $expected_message\n");
    exit(1);
}

assertSameLayoutValue(
    'loose content renders into default layout block',
    '<section><p>Hello</p></section>',
    renderLayoutTemplate(
        '{extends "layout"}<p>Hello</p>',
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertSameLayoutValue(
    'configured layout block name renders into configured block',
    '<section><p>Hello</p></section>',
    renderLayoutTemplate(
        '{layout "layout"}<p>Hello</p>',
        '<section>{block content}Fallback{/block}</section>',
        [],
        ['implicitLayoutBlockName' => 'content'],
    ),
);

assertSameLayoutValue(
    'head variables are available in layout and implicit block',
    '<main>Ada|<h1>Ada</h1></main>',
    renderLayoutTemplate(
        '{extends "layout"}{var $heading = "Ada"}<h1>{$heading}</h1>',
        '<main>{$heading}|{block default}Fallback{/block}</main>',
    ),
);

assertSameLayoutValue(
    'variables inside implicit block are block-local',
    '<main><span>local</span>|missing</main>',
    renderLayoutTemplate(
        '{extends "layout"}<span>{var $inside = "local"}{$inside}</span>',
        '<main>{block default}Fallback{/block}|{ifset $inside}{$inside}{else}missing{/ifset}</main>',
    ),
);

assertSameLayoutValue(
    'named blocks and loose content coexist',
    '<article>Title|Loose</article>',
    renderLayoutTemplate(
        '{extends "layout"}Loose{block title}Title{/block}',
        '<article>{block title}Fallback title{/block}|{block default}Fallback default{/block}</article>',
    ),
);

assertSameLayoutValue(
    'whitespace-only layout content is ignored',
    '<section>Fallback</section>',
    renderLayoutTemplate(
        "{extends \"layout\"}\n    \n",
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertSameLayoutValue(
    'disabled option keeps native ignored loose content behavior',
    '<section>Fallback</section>',
    renderLayoutTemplate(
        '{extends "layout"}<p>Hello</p>',
        '<section>{block default}Fallback{/block}</section>',
        [],
        ['implicitLayoutBlock' => false],
    ),
);

assertSameLayoutValue(
    'layout none keeps native standalone output',
    '<p>Hello</p>Block',
    renderLayoutTemplate(
        '{layout none}<p>Hello</p>{block default}Block{/block}',
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertSameLayoutValue(
    'template without explicit layout is unaffected',
    '<p>Hello</p>',
    renderLayoutTemplate(
        '<p>Hello</p>',
    ),
);

assertLayoutThrows(
    'explicit same-name block plus loose content throws',
    'Cannot combine loose content with an explicit {block default} inside a template using {layout}/{extends}; both define the default block',
    fn() => renderLayoutTemplate(
        '{extends "layout"}Loose{block default}Explicit{/block}',
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertLayoutThrows(
    'explicit same-name define plus loose content throws',
    'Cannot combine loose content with an explicit {block default} inside a template using {layout}/{extends}; both define the default block',
    fn() => renderLayoutTemplate(
        '{extends "layout"}Loose{define default}Explicit{/define}',
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertLayoutThrows(
    'invalid configured layout block name throws a clear error',
    'implicitLayoutBlockName contains invalid block name',
    fn() => renderLayoutTemplate(
        '{extends "layout"}<p>Hello</p>',
        '<section>{block default}Fallback{/block}</section>',
        [],
        ['implicitLayoutBlockName' => '123'],
    ),
);

App::destroy();
echo "All implicit layout block tests passed.\n";
