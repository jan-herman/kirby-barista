<?php declare(strict_types=1);

use JanHerman\Barista\Latte\SfcExtension;
use JanHerman\Barista\Latte\TemplateDependencies;
use Latte\Engine;
use Latte\Loaders\StringLoader;
use Latte\Runtime\Template;

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

/**
 * @param array<string, string> $templates
 */
function collectSfcDependencies(array $templates): TemplateDependencies
{
    $dependencies = new TemplateDependencies();
    $latte = new Engine();
    $latte->addExtension(new SfcExtension());
    $latte->addExtension($dependencies);
    $latte->setLoader(new StringLoader($templates));
    $latte->renderToString('main');

    return $dependencies;
}

function assertSameValue(string $label, string $expected, string $actual): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "$label failed.\nExpected: $expected\nActual:   $actual\n");
        exit(1);
    }
}

function assertContains(string $label, string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "$label failed.\nMissing value: $needle\n");
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

$template = "<article>Visible</article>{style lang: 'scss'}SFC_STYLE_SENTINEL {notALatteTag} .component{color:red}{/style}{script lang: 'ts'}SFC_SCRIPT_SENTINEL const component={enabled:true}; const html=\"<div class='component'>\"; const marker=\"<!--\"; const identity=<T>(value:T):T=>value; {notALatteTag}{/script}";
$compiled = compileSfcTemplate($template);

assertSameValue('SFC blocks render no output', '<article>Visible</article>', renderSfcTemplate($template));
assertNotContains('compiled template omits style content', 'SFC_STYLE_SENTINEL', $compiled);
assertNotContains('compiled template omits script content', 'SFC_SCRIPT_SENTINEL', $compiled);
assertNotContains('compiled template omits ignored Latte syntax', 'notALatteTag', $compiled);
assertSameValue(
    'lexer state is restored after SFC block',
    '<footer>1</footer>',
    renderSfcTemplate('{script}const html="<!--";{/script}<footer>{= 1}</footer>'),
);

$metadataTemplate = compileSfcTemplate(
    '{style}a{/style}{script}a(){/script}{style}b{/style}{script}b(){/script}',
);

assertContains('compiled template records a style marker', '__sfc_style', $metadataTemplate);
assertContains('compiled template records a script marker', '__sfc_script', $metadataTemplate);
assertNotContains('compiled template does not index style markers', '__sfc_style_0', $metadataTemplate);
assertNotContains('compiled template does not index script markers', '__sfc_script_0', $metadataTemplate);
assertNotContains('compiled template does not render SFC metadata blocks', "renderBlock('__sfc_", $metadataTemplate);

$dependencies = collectSfcDependencies([
    'main' => '{include file "style"}{include file "script"}{include file "both"}{include file "plain"}{include file "style"}',
    'style' => '{style}.style-only{}{/style}',
    'script' => '{script}scriptOnly(){/script}',
    'both' => '{style}.first{}{/style}{style}.second{}{/style}{script}both(){/script}',
    'plain' => '<p>Plain</p>',
]);

assertSameValue(
    'dependencies returns rendered templates',
    '6',
    (string) count($dependencies->templates()),
);
assertSameValue(
    'dependencies returns unique files in render order',
    '["main","style","script","both","plain"]',
    json_encode($dependencies->files()),
);
assertSameValue(
    'dependencies filters files containing style tags',
    '["style","both"]',
    json_encode($dependencies->filesWithStyle()),
);
assertSameValue(
    'dependencies filters files containing script tags',
    '["script","both"]',
    json_encode($dependencies->filesWithScript()),
);
assertSameValue(
    'SFC metadata blocks use the local block layer',
    '["__sfc_style","__sfc_script"]',
    json_encode($dependencies->templates()[3]->getBlockNames(Template::LayerLocal)),
);
assertSameValue(
    'dependencies returns the runtime tree',
    '[{"file":"main","relation":null,"children":[{"file":"style","relation":"include","children":[]},{"file":"script","relation":"include","children":[]},{"file":"both","relation":"include","children":[]},{"file":"plain","relation":"include","children":[]},{"file":"style","relation":"include","children":[]}]}]',
    json_encode($dependencies->tree()),
);

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
    'style cannot be empty',
    'Tag {style} must not be empty',
    fn() => compileSfcTemplate('{style}{/style}'),
);

assertThrows(
    'style cannot contain only newlines',
    'Tag {style} must not be empty',
    fn() => compileSfcTemplate("{style}\n\n{/style}"),
);

assertThrows(
    'script cannot be empty',
    'Tag {script} must not be empty',
    fn() => compileSfcTemplate('{script}{/script}'),
);

assertThrows(
    'script cannot contain only whitespace',
    'Tag {script} must not be empty',
    fn() => compileSfcTemplate("{script}\n \t\n{/script}"),
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
