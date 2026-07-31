<?php declare(strict_types=1);

use JanHerman\Barista\Latte\SfcExtension;
use JanHerman\Barista\Latte\TemplateDependenciesExtension;
use JanHerman\Barista\Latte\Nodes\ScriptNode;
use JanHerman\Barista\Latte\Nodes\StyleNode;
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
function collectSfcDependencies(array $templates): TemplateDependenciesExtension
{
    $dependencies = new TemplateDependenciesExtension();
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

assertSameValue('script block name constant', '__sfc_script', ScriptNode::BlockName);
assertSameValue('eager script block name constant', '__sfc_script_eager', ScriptNode::EagerBlockName);
assertSameValue('lazy script block name constant', '__sfc_script_lazy', ScriptNode::LazyBlockName);
assertSameValue('style block name constant', '__sfc_style', StyleNode::BlockName);
assertSameValue('eager style block name constant', '__sfc_style_eager', StyleNode::EagerBlockName);
assertSameValue('lazy style block name constant', '__sfc_style_lazy', StyleNode::LazyBlockName);

foreach ([
    'style without lang' => '{style}SFC_STYLE_DEFAULT{/style}',
    'style with css' => "{style lang: 'css'}SFC_STYLE_CSS{/style}",
    'style with scss' => "{style lang: 'scss'}SFC_STYLE_SCSS{/style}",
    'style with lazy flag' => '{style lazy}SFC_STYLE_LAZY_FLAG{/style}',
    'style with lazy true' => '{style lazy: true}SFC_STYLE_LAZY_TRUE{/style}',
    'style with lazy false' => '{style lazy: false}SFC_STYLE_LAZY_FALSE{/style}',
    'script without lang' => '{script}SFC_SCRIPT_DEFAULT{/script}',
    'script with js' => "{script lang: 'js'}SFC_SCRIPT_JS{/script}",
    'script with ts' => "{script lang: 'ts'}SFC_SCRIPT_TS{/script}",
    'script with lazy flag' => '{script lazy}SFC_SCRIPT_LAZY_FLAG{/script}',
    'script with lazy true' => '{script lazy: true}SFC_SCRIPT_LAZY_TRUE{/script}',
    'script with lazy false' => '{script lazy: false}SFC_SCRIPT_LAZY_FALSE{/script}',
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

assertContains('compiled template records a style marker', StyleNode::BlockName, $metadataTemplate);
assertContains('compiled template records an eager style marker', StyleNode::EagerBlockName, $metadataTemplate);
assertNotContains('compiled eager template omits a lazy style marker', StyleNode::LazyBlockName, $metadataTemplate);
assertContains('compiled template records a script marker', ScriptNode::BlockName, $metadataTemplate);
assertContains('compiled template records an eager script marker', ScriptNode::EagerBlockName, $metadataTemplate);
assertNotContains('compiled eager template omits a lazy script marker', ScriptNode::LazyBlockName, $metadataTemplate);
assertNotContains('compiled template does not index style markers', StyleNode::BlockName . '_0', $metadataTemplate);
assertNotContains('compiled template does not index script markers', ScriptNode::BlockName . '_0', $metadataTemplate);
assertNotContains('compiled template does not render style metadata blocks', "renderBlock('" . StyleNode::BlockName, $metadataTemplate);
assertNotContains('compiled template does not render script metadata blocks', "renderBlock('" . ScriptNode::BlockName, $metadataTemplate);

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
    json_encode([
        StyleNode::BlockName,
        StyleNode::EagerBlockName,
        ScriptNode::BlockName,
        ScriptNode::EagerBlockName,
    ]),
    json_encode($dependencies->templates()[3]->getBlockNames(Template::LayerLocal)),
);
assertSameValue(
    'dependencies returns the runtime tree',
    '[{"file":"main","relation":null,"children":[{"file":"style","relation":"include","children":[]},{"file":"script","relation":"include","children":[]},{"file":"both","relation":"include","children":[]},{"file":"plain","relation":"include","children":[]},{"file":"style","relation":"include","children":[]}]}]',
    json_encode($dependencies->tree()),
);

$loadingDependencies = collectSfcDependencies([
    'main' => '{include file "style-eager"}{include file "style-lazy"}{include file "style-mixed"}{include file "script-eager"}{include file "script-lazy"}{include file "script-mixed"}',
    'style-eager' => '{style}.style-eager{}{/style}',
    'style-lazy' => '{style lazy}.style-lazy{}{/style}',
    'style-mixed' => '{style lazy: false}.style-mixed-eager{}{/style}{style lazy: true}.style-mixed-lazy{}{/style}',
    'script-eager' => '{script}scriptEager(){/script}',
    'script-lazy' => '{script lazy}scriptLazy(){/script}',
    'script-mixed' => '{script lazy: false}scriptMixedEager(){/script}{script lazy: true}scriptMixedLazy(){/script}',
]);

assertSameValue(
    'style dependencies include every loading mode by default',
    '["style-eager","style-lazy","style-mixed"]',
    json_encode($loadingDependencies->filesWithStyle()),
);
assertSameValue(
    'style dependencies filter eager blocks',
    '["style-eager","style-mixed"]',
    json_encode($loadingDependencies->filesWithStyle('eager')),
);
assertSameValue(
    'style dependencies filter lazy blocks',
    '["style-lazy","style-mixed"]',
    json_encode($loadingDependencies->filesWithStyle('lazy')),
);
assertSameValue(
    'script dependencies include every loading mode by default',
    '["script-eager","script-lazy","script-mixed"]',
    json_encode($loadingDependencies->filesWithScript()),
);
assertSameValue(
    'script dependencies filter eager blocks',
    '["script-eager","script-mixed"]',
    json_encode($loadingDependencies->filesWithScript('eager')),
);
assertSameValue(
    'script dependencies filter lazy blocks',
    '["script-lazy","script-mixed"]',
    json_encode($loadingDependencies->filesWithScript('lazy')),
);
assertSameValue(
    'mixed style metadata records both loading modes',
    json_encode([
        StyleNode::BlockName,
        StyleNode::EagerBlockName,
        StyleNode::LazyBlockName,
    ]),
    json_encode($loadingDependencies->templates()[3]->getBlockNames(Template::LayerLocal)),
);
assertSameValue(
    'mixed script metadata records both loading modes',
    json_encode([
        ScriptNode::BlockName,
        ScriptNode::EagerBlockName,
        ScriptNode::LazyBlockName,
    ]),
    json_encode($loadingDependencies->templates()[6]->getBlockNames(Template::LayerLocal)),
);
assertThrows(
    'style dependencies reject unsupported loading modes',
    'Unsupported SFC loading mode: deferred',
    fn() => $loadingDependencies->filesWithStyle('deferred'),
);
assertThrows(
    'script dependencies reject unsupported loading modes',
    'Unsupported SFC loading mode: deferred',
    fn() => $loadingDependencies->filesWithScript('deferred'),
);

$pathDependencies = collectSfcDependencies([
    'main' => '{include file "src/templates/layouts/base"}{include file "src/templates/partials/header"}{include file "src/templates/components-old/legacy"}',
    'src/templates/layouts/base' => '{include file "src/templates/components/card"}',
    'src/templates/components/card' => '{include file "src/templates/components/icons/star"}{style}.card{}{/style}',
    'src/templates/components/icons/star' => '{style}.star{}{/style}',
    'src/templates/partials/header' => '{script}header(){/script}',
    'src/templates/components-old/legacy' => '{style}.legacy{}{/style}',
]);

$componentDependencies = $pathDependencies->filterBy(
    'directory',
    'src\\templates\\components\\',
);

assertSameValue(
    'directory equality is recursive and normalizes separators',
    '["src\/templates\/components\/card","src\/templates\/components\/icons\/star"]',
    json_encode($componentDependencies->files()),
);
assertSameValue(
    'directory filters apply to rendered templates',
    '2',
    (string) count($componentDependencies->templates()),
);
assertSameValue(
    'filtered accessors share the same template snapshot',
    '["src\/templates\/components\/card","src\/templates\/components\/icons\/star"]',
    json_encode($componentDependencies->filesWithStyle()),
);
assertSameValue(
    'directory boundaries do not match sibling prefixes',
    'false',
    json_encode(in_array(
        'src/templates/components-old/legacy',
        $componentDependencies->files(),
        true,
    )),
);
assertSameValue(
    'directory inequality excludes only the selected subtree',
    '["main","src\/templates\/components\/card","src\/templates\/components\/icons\/star","src\/templates\/partials\/header","src\/templates\/components-old\/legacy"]',
    json_encode($pathDependencies->filterBy(
        'directory',
        '!=',
        'src/templates/layouts',
    )->files()),
);
assertSameValue(
    'directory in accepts multiple subtrees',
    '["src\/templates\/components\/card","src\/templates\/components\/icons\/star","src\/templates\/partials\/header"]',
    json_encode($pathDependencies->filterBy(
        'directory',
        'in',
        [
            'src/templates/components',
            'src/templates/partials',
        ],
    )->files()),
);
assertSameValue(
    'directory filters apply to script dependencies',
    '["src\/templates\/partials\/header"]',
    json_encode($pathDependencies->filterBy(
        'directory',
        'src/templates/partials',
    )->filesWithScript()),
);
assertSameValue(
    'directory not in excludes multiple subtrees',
    '["main","src\/templates\/components\/card","src\/templates\/components\/icons\/star","src\/templates\/partials\/header"]',
    json_encode($pathDependencies->filterBy(
        'directory',
        'not in',
        [
            'src/templates/layouts',
            'src/templates/components-old',
        ],
    )->files()),
);
assertSameValue(
    'file equality uses normalized template identifiers',
    '["src\/templates\/components\/card"]',
    json_encode($pathDependencies->filterBy(
        'file',
        'src\\templates\\components\\card',
    )->files()),
);
assertSameValue(
    'file in accepts multiple identifiers',
    '["src\/templates\/components\/card","src\/templates\/partials\/header"]',
    json_encode($pathDependencies->filterBy(
        'file',
        'in',
        [
            'src/templates/components/card',
            'src/templates/partials/header',
        ],
    )->files()),
);
assertSameValue(
    'file not in excludes multiple identifiers',
    '["main","src\/templates\/components\/card","src\/templates\/components\/icons\/star","src\/templates\/partials\/header"]',
    json_encode($pathDependencies->filterBy(
        'file',
        'not in',
        [
            'src/templates/layouts/base',
            'src/templates/components-old/legacy',
        ],
    )->files()),
);
assertSameValue(
    'filters can be chained',
    '["src\/templates\/components\/card"]',
    json_encode($componentDependencies->filterBy(
        'file',
        '!=',
        'src/templates/components/icons/star',
    )->files()),
);
assertSameValue(
    'callback filters receive Latte templates',
    '["src\/templates\/components\/card","src\/templates\/components\/icons\/star"]',
    json_encode($pathDependencies->filter(
        static fn (Template $template): bool => str_contains(
            $template->getName(),
            '/components/',
        ),
    )->files()),
);
assertSameValue(
    'filtering does not mutate the original collector',
    '6',
    (string) count($pathDependencies->templates()),
);
assertSameValue(
    'filtered trees promote matching descendants to roots',
    '[{"file":"src\/templates\/components\/card","relation":null,"children":[{"file":"src\/templates\/components\/icons\/star","relation":"include","children":[]}]}]',
    json_encode($componentDependencies->tree()),
);

assertThrows(
    'dependency filters reject unsupported fields',
    'Unsupported template dependency field',
    fn() => $pathDependencies->filterBy('block', 'style'),
);
assertThrows(
    'dependency filters reject unsupported operators',
    'Unsupported template dependency operator',
    fn() => $pathDependencies->filterBy('file', '*=', 'components'),
);
assertThrows(
    'dependency in filters require an array',
    'in operator requires an array',
    fn() => $pathDependencies->filterBy('file', 'in', 'main'),
);
assertThrows(
    'dependency filters reject additional callback arguments',
    'Callback filters do not accept additional arguments',
    fn() => $pathDependencies->filter(
        static fn (Template $template): bool => true,
        'unexpected',
    ),
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

assertThrows(
    'dynamic lazy is rejected',
    'must be a bare flag or a static boolean',
    fn() => compileSfcTemplate('{style lazy: $lazy}a{/style}'),
);

assertThrows(
    'quoted lazy boolean is rejected',
    'must be a bare flag or a static boolean',
    fn() => compileSfcTemplate("{script lazy: 'true'}a(){/script}"),
);

assertThrows(
    'numeric lazy is rejected',
    'must be a bare flag or a static boolean',
    fn() => compileSfcTemplate('{style lazy: 1}a{/style}'),
);

assertThrows(
    'quoted lazy flag is rejected',
    'must be a bare flag or a static boolean',
    fn() => compileSfcTemplate("{style 'lazy'}a{/style}"),
);

assertThrows(
    'duplicate lazy is rejected',
    'must not be declared more than once',
    fn() => compileSfcTemplate('{script lazy, lazy: true}a(){/script}'),
);

echo "All SFC extension tests passed.\n";
