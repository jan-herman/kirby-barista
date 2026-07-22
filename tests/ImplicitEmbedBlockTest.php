<?php declare(strict_types=1);

use JanHerman\Barista\Latte\BaristaExtension;
use JanHerman\Barista\Latte\ImplicitEmbedBlockExtension;
use Kirby\Cms\App;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\Tag;
use Latte\CompileException;
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

function bootKirby(array $barista_options = []): void
{
    App::destroy();

    $base = sys_get_temp_dir() . '/barista-implicit-embed-tests';
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
            'jan-herman.barista.implicitEmbedBlock' => $barista_options['implicitEmbedBlock'] ?? true,
            'jan-herman.barista.implicitEmbedBlockName' => $barista_options['implicitEmbedBlockName'] ?? 'default',
            'jan-herman.barista.tags' => $barista_options['tags'] ?? [],
        ],
    ]);
}

function renderTemplate(string $main, string $component, array $params = [], array $barista_options = []): string
{
    bootKirby($barista_options);

    $latte = new Engine();
    if (option('jan-herman.barista.implicitEmbedBlock', true)) {
        $latte->addExtension(new ImplicitEmbedBlockExtension(
            option('jan-herman.barista.implicitEmbedBlockName', 'default'),
        ));
    }
    $latte->addExtension(new BaristaExtension());
    $latte->setLoader(new StringLoader([
        'main' => $main,
        'component' => $component,
    ]));

    return $latte->renderToString('main', $params);
}

function assertSameValue(string $label, string $expected, string $actual): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "$label failed.\nExpected: $expected\nActual:   $actual\n");
        exit(1);
    }
}

function assertDifferentValue(string $label, string $first, string $second): void
{
    if ($first === $second) {
        fwrite(STDERR, "$label failed.\nValues should differ: $first\n");
        exit(1);
    }
}

function assertThrows(string $label, string $expected_message, callable $callback): void
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

assertSameValue(
    'loose content renders into default block',
    '<section><p>Hello</p></section>',
    renderTemplate(
        '{embed file "component"}<p>Hello</p>{/embed}',
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertSameValue(
    'configured block name renders into configured block',
    '<section><p>Hello</p></section>',
    renderTemplate(
        '{embed file "component"}<p>Hello</p>{/embed}',
        '<section>{block content}Fallback{/block}</section>',
        [],
        ['implicitEmbedBlockName' => 'content'],
    ),
);

$defaultBlockEngine = new Engine();
$defaultBlockEngine->addExtension(new ImplicitEmbedBlockExtension('default'));
$defaultBlockEngine->addExtension(new BaristaExtension());

$contentBlockEngine = new Engine();
$contentBlockEngine->addExtension(new ImplicitEmbedBlockExtension('content'));
$contentBlockEngine->addExtension(new BaristaExtension());

assertDifferentValue(
    'implicit block name changes the template cache key',
    $defaultBlockEngine->getTemplateClass('main'),
    $contentBlockEngine->getTemplateClass('main'),
);

assertSameValue(
    'embed-site variables are available inside implicit block',
    '<section><p>Ada</p></section>',
    renderTemplate(
        '{embed file "component"}<p>{$name}</p>{/embed}',
        '<section>{block default}Fallback{/block}</section>',
        ['name' => 'Ada'],
    ),
);

assertSameValue(
    'named blocks still override normally',
    '<article>D|X</article>',
    renderTemplate(
        '{embed file "component"}{block title}X{/block}{/embed}',
        '<article>{block default}D{/block}|{block title}T{/block}</article>',
    ),
);

assertSameValue(
    'loose content and named blocks render together',
    '<article>Y|X</article>',
    renderTemplate(
        '{embed file "component"}Y{block title}X{/block}{/embed}',
        '<article>{block default}D{/block}|{block title}T{/block}</article>',
    ),
);

assertSameValue(
    'implicit loose content supports include parent',
    '<button><span>Parent</span><strong>Child</strong></button>',
    renderTemplate(
        '{embed file "component", label: "Parent"}{include parent}<strong>Child</strong>{/embed}',
        '<button>{block default}<span>{$label}</span>{/block}</button>',
    ),
);

assertSameValue(
    'explicit default block still supports include parent',
    '<button><span>Parent</span><strong>Child</strong></button>',
    renderTemplate(
        '{embed file "component", label: "Parent"}{block default}{include parent}<strong>Child</strong>{/block}{/embed}',
        '<button>{block default}<span>{$label}</span>{/block}</button>',
    ),
);

assertSameValue(
    'outer whitespace around loose content is trimmed',
    '<section><p>Hello</p></section>',
    renderTemplate(
        "{embed file \"component\"}\n    <p>Hello</p>\n{/embed}",
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertSameValue(
    'whitespace between loose content nodes is preserved',
    '<section><span>One</span> <span>Two</span></section>',
    renderTemplate(
        "{embed file \"component\"}\n    <span>One</span> <span>Two</span>\n{/embed}",
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertSameValue(
    'whitespace-only embed content is ignored',
    '<section>Fallback</section>',
    renderTemplate(
        "{embed file \"component\"}\n    \n{/embed}",
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertSameValue(
    'empty paired embed keeps native behavior',
    'A<section>Fallback</section>B',
    renderTemplate(
        'A{embed file "component"}{/embed}B',
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertSameValue(
    'self-closing embed keeps native behavior',
    'A<section>Fallback</section>B',
    renderTemplate(
        'A{embed file "component"/}B',
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertSameValue(
    'explicit default block without loose content keeps native behavior',
    '<section>Explicit</section>',
    renderTemplate(
        '{embed file "component"}{block default}Explicit{/block}{/embed}',
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertSameValue(
    'whitespace around explicit default block does not create implicit duplicate',
    '<section>Explicit</section>',
    renderTemplate(
        "{embed file \"component\"}\n    {block default}Explicit{/block}\n{/embed}",
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertSameValue(
    'custom embed tag option still overrides Barista embed tag',
    'custom',
    renderTemplate(
        '{embed}',
        '',
        [],
        [
            'tags' => [
                'embed' => fn(Tag $tag) => new TextNode('custom'),
            ],
        ],
    ),
);

assertThrows(
    'explicit same-name block plus loose content throws',
    'Cannot combine loose content with an explicit {block default} inside {embed}; both define the default block',
    fn() => renderTemplate(
        '{embed file "component"}Y{block default}Z{/block}{/embed}',
        '<section>{block default}Fallback{/block}</section>',
    ),
);

assertThrows(
    'explicit configured-name block plus loose content throws configured-name error',
    'Cannot combine loose content with an explicit {block content} inside {embed}; both define the content block',
    fn() => renderTemplate(
        '{embed file "component"}Y{block content}Z{/block}{/embed}',
        '<section>{block content}Fallback{/block}</section>',
        [],
        ['implicitEmbedBlockName' => 'content'],
    ),
);

assertThrows(
    'disabled option restores native embed error',
    'Unexpected content inside {embed} tags',
    fn() => renderTemplate(
        '{embed file "component"}<p>Hello</p>{/embed}',
        '<section>{block default}Fallback{/block}</section>',
        [],
        ['implicitEmbedBlock' => false],
    ),
);

assertThrows(
    'invalid configured block name throws a clear error',
    'implicitEmbedBlockName contains invalid block name',
    fn() => renderTemplate(
        '{embed file "component"}<p>Hello</p>{/embed}',
        '<section>{block default}Fallback{/block}</section>',
        [],
        ['implicitEmbedBlockName' => '123'],
    ),
);

App::destroy();
echo "All implicit embed block tests passed.\n";
