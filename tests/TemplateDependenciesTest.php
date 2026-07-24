<?php declare(strict_types=1);

use JanHerman\Barista\Barista;
use Kirby\Cms\App;

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

if (!class_exists(App::class)) {
    fwrite(STDERR, "Unable to load Composer autoload.\n");
    exit(1);
}

function assertSameValue(string $label, mixed $expected, mixed $actual): void
{
    if ($actual !== $expected) {
        fwrite(
            STDERR,
            "$label failed.\nExpected: " . var_export($expected, true)
            . "\nActual:   " . var_export($actual, true) . "\n",
        );
        exit(1);
    }
}

$base = sys_get_temp_dir() . '/barista-template-dependencies-' . getmypid();
$templates = $base . '/site/templates';

foreach ([
    $base . '/cache',
    $base . '/content',
    $base . '/media',
    $base . '/site/config',
    $base . '/site/plugins',
    $templates,
] as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
}

$parameterizedTemplate = $templates . '/parameterized.latte';
$optionalTemplate = $templates . '/optional.latte';

file_put_contents(
    $parameterizedTemplate,
    '{parameters string $message}<p>{$message}</p>{style}.parameterized{}{/style}',
);
file_put_contents(
    $optionalTemplate,
    '<p>Optional</p>{script}optional(){/script}',
);

App::destroy();
$kirby = new App([
    'roots' => [
        'index' => $base,
        'site' => $base . '/site',
        'config' => $base . '/site/config',
        'plugins' => $base . '/site/plugins',
        'content' => $base . '/content',
        'media' => $base . '/media',
    ],
    'options' => [
        'jan-herman.barista.cacheDirectory' => $base . '/cache',
        'jan-herman.barista.sfc' => true,
        'jan-herman.barista.templateDependencies' => true,
    ],
]);

$barista = Barista::getInstance($kirby);
$dependencies = $barista->collectTemplateDependencies(
    $parameterizedTemplate,
    ['message' => 'Hello'],
);

assertSameValue(
    'collection returns the shared dependency collector',
    true,
    $dependencies === $barista->templateDependencies(),
);
assertSameValue(
    'collection renders with supplied parameters',
    [$parameterizedTemplate],
    $dependencies->files(),
);
assertSameValue(
    'collection exposes templates containing style tags',
    [$parameterizedTemplate],
    $dependencies->filesWithStyle(),
);
assertSameValue(
    'collection exposes no script files when none were rendered',
    [],
    $dependencies->filesWithScript(),
);

$optionalDependencies = $barista->collectTemplateDependencies($optionalTemplate);

assertSameValue(
    'collection parameters remain optional',
    [$optionalTemplate],
    $optionalDependencies->files(),
);
assertSameValue(
    'a new collection resets the previous result',
    [],
    $optionalDependencies->filesWithStyle(),
);
assertSameValue(
    'the current collector is available without rendering again',
    [$optionalTemplate],
    $barista->templateDependencies()->filesWithScript(),
);

echo "All template dependency tests passed.\n";
