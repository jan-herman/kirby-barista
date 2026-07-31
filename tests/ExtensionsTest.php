<?php declare(strict_types=1);

use JanHerman\Barista\Barista;
use JanHerman\Barista\Latte\BaristaExtension;
use JanHerman\Barista\Latte\CoreFiltersExtension;
use JanHerman\Barista\Latte\SfcExtension;
use JanHerman\Barista\Latte\TemplateDependenciesExtension;
use Kirby\Cms\App;
use Latte\Bridges\Tracy\TracyExtension;
use Latte\Engine;
use Latte\Essential\CoreExtension;
use Latte\Essential\RawPhpExtension;
use Latte\Essential\TranslatorExtension;
use Latte\Extension;
use Latte\Loaders\StringLoader;
use Latte\Sandbox\SandboxExtension;

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

class RegistryTestExtension extends Extension
{
    public function __construct(protected array $filters = [])
    {
    }

    public function getFilters(): array
    {
        return $this->filters;
    }
}

class RegistryTestBarista extends Barista
{
    public function __construct(App $kirby, bool $isTracyInstalled = true)
    {
        $this->kirby = $kirby;
        $this->isTracyInstalled = $isTracyInstalled;
    }

    public function registerExtensions(Engine $latte): void
    {
        $this->registerLatteExtensions($latte);
    }
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

function assertThrows(
    string $label,
    string $expectedClass,
    string $expectedMessage,
    callable $callback,
): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        if (
            $exception instanceof $expectedClass
            && str_contains($exception->getMessage(), $expectedMessage)
        ) {
            return;
        }

        fwrite(
            STDERR,
            "$label failed with unexpected exception.\n"
            . get_class($exception) . ': ' . $exception->getMessage() . "\n",
        );
        exit(1);
    }

    fwrite(STDERR, "$label failed. Expected $expectedClass containing: $expectedMessage\n");
    exit(1);
}

function createKirby(array $options): App
{
    App::destroy();

    return new App(['options' => $options]);
}

/** @return array<string, bool> */
function defaultExtensionOptions(): array
{
    return [
        'translator' => true,
        'coreFilters' => true,
        'sfc' => false,
        'templateDependencies' => false,
        'tracy' => true,
        'rawPhp' => false,
    ];
}

/** @return class-string[] */
function extensionClasses(Engine $latte): array
{
    return array_map(
        static fn (Extension $extension): string => $extension::class,
        $latte->getExtensions(),
    );
}

/** @return class-string[] */
function latteCoreExtensionClasses(): array
{
    return [
        CoreExtension::class,
        SandboxExtension::class,
    ];
}

function registerConfiguredExtensions(
    mixed $extensions,
    bool $isTracyInstalled = true,
): void {
    $kirby = createKirby([
        'jan-herman.barista.extensions' => $extensions,
    ]);

    (new RegistryTestBarista($kirby, $isTracyInstalled))
        ->registerExtensions(new Engine());
}

$customTag = static fn (): null => null;
$customFilter = static fn (string $value): string => "custom:$value";
$customFunction = static fn (): string => 'custom';

createKirby([
    'jan-herman.barista.tags' => ['customTag' => $customTag],
    'jan-herman.barista.filters' => [
        'customFilter' => $customFilter,
        'stripNewLines' => $customFilter,
    ],
    'jan-herman.barista.functions' => ['customFunction' => $customFunction],
]);

$baristaExtension = new BaristaExtension();
$coreFiltersExtension = new CoreFiltersExtension();

assertSameValue(
    'Barista extension registers only custom tags',
    ['customTag' => $customTag],
    $baristaExtension->getTags(),
);
assertSameValue(
    'Barista extension registers only custom filters',
    [
        'customFilter' => $customFilter,
        'stripNewLines' => $customFilter,
    ],
    $baristaExtension->getFilters(),
);
assertSameValue(
    'Barista extension registers only custom functions',
    ['customFunction' => $customFunction],
    $baristaExtension->getFunctions(),
);
assertSameValue(
    'core filters extension registers built-in filters',
    ['stripNewLines'],
    array_keys($coreFiltersExtension->getFilters()),
);

$defaultKirby = createKirby([
    'jan-herman.barista.extensions' => defaultExtensionOptions(),
]);
$defaultBarista = new RegistryTestBarista($defaultKirby);
$defaultLatte = new Engine();
$defaultBarista->registerExtensions($defaultLatte);

assertSameValue(
    'default registry enables only default-on extensions',
    [
        ...latteCoreExtensionClasses(),
        TranslatorExtension::class,
        CoreFiltersExtension::class,
        TracyExtension::class,
        BaristaExtension::class,
    ],
    extensionClasses($defaultLatte),
);
assertThrows(
    'disabled template dependencies reference the nested option',
    LogicException::class,
    'jan-herman.barista.extensions.templateDependencies',
    $defaultBarista->templateDependencies(...),
);

$noTracyLatte = new Engine();
(new RegistryTestBarista($defaultKirby, false))->registerExtensions($noTracyLatte);

assertSameValue(
    'unavailable built-in Tracy extension is skipped',
    [
        ...latteCoreExtensionClasses(),
        TranslatorExtension::class,
        CoreFiltersExtension::class,
        BaristaExtension::class,
    ],
    extensionClasses($noTracyLatte),
);

$disabledKirby = createKirby([
    'jan-herman.barista.extensions' => [
        ...array_map(static fn (): bool => false, defaultExtensionOptions()),
        'custom' => false,
    ],
]);
$disabledLatte = new Engine();
(new RegistryTestBarista($disabledKirby))->registerExtensions($disabledLatte);

assertSameValue(
    'all configurable extensions can be disabled',
    [
        ...latteCoreExtensionClasses(),
        BaristaExtension::class,
    ],
    extensionClasses($disabledLatte),
);

foreach (array_keys(defaultExtensionOptions()) as $name) {
    $replacement = $name === 'templateDependencies'
        ? new TemplateDependenciesExtension()
        : new RegistryTestExtension();
    $receivedKirby = null;
    $kirby = createKirby([
        'jan-herman.barista.extensions' => [
            $name => static function (App $app) use ($replacement, &$receivedKirby): Extension {
                $receivedKirby = $app;

                return $replacement;
            },
        ],
    ]);
    $latte = new Engine();
    (new RegistryTestBarista($kirby, false))->registerExtensions($latte);

    assertSameValue(
        "$name factory receives the current Kirby app",
        true,
        $receivedKirby === $kirby,
    );
    assertSameValue(
        "$name built-in can be replaced",
        [
            ...latteCoreExtensionClasses(),
            $replacement::class,
            BaristaExtension::class,
        ],
        extensionClasses($latte),
    );
}

$receivedKirby = null;
$enabledExtensions = defaultExtensionOptions();
$enabledExtensions['sfc'] = true;
$enabledExtensions['templateDependencies'] = true;
$enabledExtensions['tracy'] = false;
$enabledExtensions['rawPhp'] = true;
$enabledExtensions['first'] = static function (App $app) use (&$receivedKirby): Extension {
    $receivedKirby = $app;

    return new RegistryTestExtension([
        'extensionOrder' => static fn (): string => 'first',
        'baristaWins' => static fn (): string => 'extension',
    ]);
};
$enabledExtensions['second'] = static fn (App $app): Extension => new RegistryTestExtension([
    'extensionOrder' => static fn (): string => 'second',
]);

$enabledKirby = createKirby([
    'jan-herman.barista.extensions' => $enabledExtensions,
    'jan-herman.barista.filters' => [
        'baristaWins' => static fn (): string => 'barista',
    ],
]);
$enabledBarista = new RegistryTestBarista($enabledKirby, false);
$enabledLatte = new Engine();
$enabledBarista->registerExtensions($enabledLatte);

assertSameValue(
    'enabled registry preserves extension order and registers Barista last',
    [
        ...latteCoreExtensionClasses(),
        TranslatorExtension::class,
        CoreFiltersExtension::class,
        SfcExtension::class,
        TemplateDependenciesExtension::class,
        RawPhpExtension::class,
        RegistryTestExtension::class,
        RegistryTestExtension::class,
        BaristaExtension::class,
    ],
    extensionClasses($enabledLatte),
);
assertSameValue(
    'custom factories receive the exact Kirby app',
    true,
    $receivedKirby === $enabledKirby,
);
assertSameValue(
    'later registry extensions override earlier extensions',
    'second',
    $enabledLatte->invokeFilter('extensionOrder', []),
);
assertSameValue(
    'Barista custom filters have final precedence',
    'barista',
    $enabledLatte->invokeFilter('baristaWins', []),
);
assertSameValue(
    'registered template dependencies are exposed by Barista',
    true,
    in_array($enabledBarista->templateDependencies(), $enabledLatte->getExtensions(), true),
);

$enabledLatte->setLoader(new StringLoader([
    'raw' => '{php echo "Raw PHP";}',
]));

assertSameValue(
    'Raw PHP is available when explicitly enabled',
    'Raw PHP',
    $enabledLatte->renderToString('raw'),
);

$legacyKirby = createKirby([
    'jan-herman.barista.extensions' => defaultExtensionOptions(),
    'jan-herman.barista.sfc' => true,
    'jan-herman.barista.templateDependencies' => true,
]);
$legacyLatte = new Engine();
(new RegistryTestBarista($legacyKirby, false))->registerExtensions($legacyLatte);

assertSameValue(
    'removed top-level options do not enable extensions',
    false,
    in_array(SfcExtension::class, extensionClasses($legacyLatte), true)
    || in_array(TemplateDependenciesExtension::class, extensionClasses($legacyLatte), true),
);

assertThrows(
    'extensions option must be an array',
    InvalidArgumentException::class,
    'jan-herman.barista.extensions option must be an array',
    static fn () => registerConfiguredExtensions('invalid'),
);
assertThrows(
    'unknown true extension requires a factory',
    InvalidArgumentException::class,
    "Unknown built-in Latte extension 'custom'",
    static fn () => registerConfiguredExtensions(['custom' => true]),
);
assertThrows(
    'extension values must be booleans or callables',
    InvalidArgumentException::class,
    'jan-herman.barista.extensions.custom option must be a boolean or callable',
    static fn () => registerConfiguredExtensions(['custom' => 'not-a-callable']),
);
assertThrows(
    'extension factories must return Latte extensions',
    UnexpectedValueException::class,
    'must return an instance of Latte\\Extension',
    static fn () => registerConfiguredExtensions([
        'custom' => static fn (App $kirby): stdClass => new stdClass(),
    ]),
);
assertThrows(
    'template dependencies replacement preserves the collector contract',
    UnexpectedValueException::class,
    TemplateDependenciesExtension::class,
    static fn () => registerConfiguredExtensions([
        'templateDependencies' => static fn (App $kirby): Extension => new RegistryTestExtension(),
    ]),
);

echo "All extension tests passed.\n";
