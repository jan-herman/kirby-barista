<?php declare(strict_types=1);

use JanHerman\Barista\Latte\SfcExtension;
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

function createSfcEngine(string $template): Engine
{
    $latte = new Engine();
    $latte->addExtension(new SfcExtension());
    $latte->setLoader(new StringLoader(['main' => $template]));

    return $latte;
}

function compileSfcTemplate(string $template): string
{
    return createSfcEngine($template)->compile('main');
}

function renderSfcTemplate(string $template): string
{
    return createSfcEngine($template)->renderToString('main');
}

function assertSameValue(string $label, string $expected, string $actual): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "$label failed.\nExpected: $expected\nActual:   $actual\n");
        exit(1);
    }
}

function assertNotContains(string $label, string $needle, string $haystack): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "$label failed.\nUnexpected value: $needle\n");
        exit(1);
    }
}

function assertThrows(string $label, string $expectedMessage, callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if (str_contains($exception->getMessage(), $expectedMessage)) {
            return;
        }

        fwrite(STDERR, "$label failed with unexpected exception.\n" . get_class($exception) . ': ' . $exception->getMessage() . "\n");
        exit(1);
    }

    fwrite(STDERR, "$label failed. Expected exception containing: $expectedMessage\n");
    exit(1);
}

foreach ([
    'style without lang' => '{style}SFC_STYLE_DEFAULT{/style}',
    'style with css' => "{style lang: 'css'}SFC_STYLE_CSS{/style}",
    'style with scss' => "{style lang: 'scss'}SFC_STYLE_SCSS{/style}",
    'script without lang' => '{script}SFC_SCRIPT_DEFAULT{/script}',
    'script with js' => "{script lang: 'js'}SFC_SCRIPT_JS{/script}",
    'script with ts' => "{script lang: 'ts'}SFC_SCRIPT_TS{/script}",
    'style with ignored properties' => "{style scoped: true, media: 'print', lang: 'scss', lang: 'css'}SFC_STYLE_PROPERTIES{/style}",
    'script with ignored positional property' => "{script 'module', defer: true, source: \$source}SFC_SCRIPT_PROPERTIES{/script}",
] as $label => $block) {
    assertSameValue($label, '<main>Visible</main>', renderSfcTemplate('<main>Visible</main>' . $block));
}

$template = "<article>Visible</article>{style lang: 'scss'}SFC_STYLE_SENTINEL {notALatteTag} .component { color: red; }{/style}{script lang: 'ts'}SFC_SCRIPT_SENTINEL const component = { enabled: true }; {notALatteTag}{/script}";
$compiled = compileSfcTemplate($template);

assertSameValue('SFC blocks render no output', '<article>Visible</article>', renderSfcTemplate($template));
assertNotContains('compiled template omits style content', 'SFC_STYLE_SENTINEL', $compiled);
assertNotContains('compiled template omits script content', 'SFC_SCRIPT_SENTINEL', $compiled);
assertNotContains('compiled template omits ignored Latte syntax', 'notALatteTag', $compiled);

assertThrows(
    'style requires a closing tag',
    '{/style}',
    fn() => compileSfcTemplate('{style}SFC_STYLE_UNCLOSED'),
);

assertThrows(
    'script requires a closing tag',
    '{/script}',
    fn() => compileSfcTemplate('{script}SFC_SCRIPT_UNCLOSED'),
);

assertThrows(
    'style cannot be self-closing',
    'must be paired',
    fn() => compileSfcTemplate('{style/}'),
);

assertThrows(
    'script cannot be self-closing',
    'must be paired',
    fn() => compileSfcTemplate('{script/}'),
);

assertThrows(
    'n:style is not supported',
    'Attribute n:style is not supported',
    fn() => compileSfcTemplate('<div n:style></div>'),
);

assertThrows(
    'n:script is not supported',
    'Attribute n:script is not supported',
    fn() => compileSfcTemplate('<div n:script></div>'),
);

assertThrows(
    'dynamic lang is rejected',
    'must be a static string',
    fn() => compileSfcTemplate('{script lang: $lang}{/script}'),
);

assertThrows(
    'non-string lang is rejected',
    'must be a static string',
    fn() => compileSfcTemplate('{style lang: 1}{/style}'),
);

assertThrows(
    'unsupported style language is rejected',
    "Unsupported lang 'less'",
    fn() => compileSfcTemplate("{style lang: 'less'}{/style}"),
);

assertThrows(
    'unsupported script language is rejected',
    "Unsupported lang 'tsx'",
    fn() => compileSfcTemplate("{script lang: 'tsx'}{/script}"),
);

echo "All SFC extension tests passed.\n";
