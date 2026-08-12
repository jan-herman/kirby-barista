<?php

namespace JanHerman\Barista;

use JanHerman\Barista\Latte\FileLoader;
use JanHerman\Barista\Latte\BaristaExtension;
use JanHerman\Barista\Latte\CoreFiltersExtension;
use JanHerman\Barista\LatteSfc\SfcExtension;
use JanHerman\Barista\LatteSfc\TemplateDependenciesExtension;
use JanHerman\Barista\Latte\Translator;
use Kirby\Cms\App as Kirby;
use Kirby\Exception\Exception as KirbyException;
use Kirby\Filesystem\Dir;
use Latte\Engine as LatteEngine;
use Latte\Essential\RawPhpExtension;
use Latte\Extension as LatteExtension;
use Latte\Feature;
use Latte\Essential\TranslatorExtension;
use Latte\Bridges\Tracy\TracyExtension;
use Tracy\Debugger;
use Exception;
use InvalidArgumentException;
use UnexpectedValueException;

class Barista
{
    protected static self $instance;
    protected Kirby $kirby;
    protected bool $isLocalhost;
    protected bool $isTracyInstalled;
    protected string $cacheDirectory;
    protected LatteEngine $latte;
    protected ?TemplateDependenciesExtension $templateDependencies = null;

    /**
     * Initializes Barista and its Latte engine.
     */
    private function __construct(Kirby $kirby)
    {
        $this->kirby = $kirby;
        $this->isLocalhost = $kirby->environment()->isLocal();
        $this->isTracyInstalled = class_exists(Debugger::class);

        $this->cacheDirectory = $this->resolveCacheDirectory();
        $this->ensureCacheDirectory();

        $latte = $this->createLatteEngine();
        $this->configureLatteFeatures($latte);
        $this->configureLatteLocale($latte);
        $this->registerLatteExtensions($latte);
        $this->configureLatteLoader($latte);
        $this->configureLatteCache($latte);

        $this->latte = $this->applyInitHook($latte);
    }

    /**
     * Returns the shared Barista instance.
     */
    public static function getInstance(Kirby $kirby)
    {
        return self::$instance ??= new self($kirby);
    }

    /**
     * Returns the configured Latte engine.
     */
    public function getEngine(): LatteEngine
    {
        return $this->latte;
    }

    /**
     * Renders a template and collects only its runtime template dependencies.
     */
    public function collectTemplateDependencies(
        string $file,
        object|array $params = [],
        ?string $block = null,
    ): TemplateDependenciesExtension
    {
        $dependencies = $this->templateDependencies();
        $dependencies->reset();
        $this->renderToString($file, $params, $block);

        return $dependencies;
    }

    /**
     * Returns the collector for rendered template dependencies.
     */
    public function templateDependencies(): TemplateDependenciesExtension
    {
        if ($this->templateDependencies === null) {
            throw new \LogicException(
                'Template dependency tracking is disabled. Set the jan-herman.barista.extensions.templateDependencies option to true.',
            );
        }

        return $this->templateDependencies;
    }

    /**
     * Removes and recreates the Latte cache directory.
     */
    public function flushCache(): void
    {
        if (Dir::remove($this->cacheDirectory) === false) {
            throw new KirbyException($this->cacheDirectory . ' directory could not be removed.');
        }

        $this->ensureCacheDirectory();
    }

    /**
     * Returns a Barista plugin option.
     */
    public function getOption(string $key, $default = null): mixed
    {
        return option('jan-herman.barista.' . $key, $default);
    }

    /**
     * Creates a fresh Latte engine.
     */
    protected function createLatteEngine(): LatteEngine
    {
        return new LatteEngine();
    }

    /**
     * Applies feature flags and refresh behavior.
     */
    protected function configureLatteFeatures(LatteEngine $latte): void
    {
        $latte->setFeature(Feature::StrictTypes, $this->getOption('strictTypes', false));
        $latte->setFeature(Feature::StrictParsing, $this->getOption('strictParsing', false));
        $latte->setFeature(Feature::MigrationWarnings, $this->getOption('migrationWarnings', false));
        $latte->setFeature(Feature::ScopedLoopVariables, $this->getOption('scopedLoopVariables', true));
        $latte->setFeature(Feature::Dedent, $this->getOption('dedent', true));
        $latte->setAutoRefresh($this->getOption('autoRefresh', true));
    }

    /**
     * Applies the current Kirby locale.
     */
    protected function configureLatteLocale(LatteEngine $latte): void
    {
        $latte->setLocale($this->kirby->language()?->locale(LC_ALL) ?? option('locale', 'en_US'));
    }

    /**
     * Registers configured Latte extensions followed by Barista's custom extension.
     */
    protected function registerLatteExtensions(LatteEngine $latte): void
    {
        $configuredExtensions = $this->getOption('extensions', []);

        if (!is_array($configuredExtensions)) {
            throw new InvalidArgumentException(
                'The jan-herman.barista.extensions option must be an array.',
            );
        }

        $factories = $this->latteExtensionFactories();

        foreach ($configuredExtensions as $name => $configuredExtension) {
            if (!is_string($name) || $name === '') {
                throw new InvalidArgumentException(
                    'Latte extension names in jan-herman.barista.extensions must be non-empty strings.',
                );
            }

            if ($configuredExtension === false) {
                continue;
            }

            if ($configuredExtension === true) {
                if (!array_key_exists($name, $factories)) {
                    throw new InvalidArgumentException(
                        "Unknown built-in Latte extension '$name'. Provide a callable factory instead of true.",
                    );
                }

                if ($factories[$name] === null) {
                    continue;
                }

                $factory = $factories[$name];
            } elseif (is_callable($configuredExtension)) {
                $factory = $configuredExtension;
            } else {
                throw new InvalidArgumentException(
                    "The jan-herman.barista.extensions.$name option must be a boolean or callable.",
                );
            }

            $extension = $factory($this->kirby);

            if (!$extension instanceof LatteExtension) {
                throw new UnexpectedValueException(
                    "The jan-herman.barista.extensions.$name factory must return an instance of Latte\\Extension.",
                );
            }

            if (
                $name === 'templateDependencies'
                && !$extension instanceof TemplateDependenciesExtension
            ) {
                throw new UnexpectedValueException(
                    'The jan-herman.barista.extensions.templateDependencies factory must return an instance of '
                    . TemplateDependenciesExtension::class . '.',
                );
            }

            if ($extension instanceof TemplateDependenciesExtension) {
                $this->templateDependencies = $extension;
            }

            $latte->addExtension($extension);
        }

        $latte->addExtension(new BaristaExtension());
    }

    /**
     * Returns factories for Barista's built-in Latte extensions.
     *
     * @return array<string, (callable(Kirby): LatteExtension)|null>
     */
    protected function latteExtensionFactories(): array
    {
        return [
            'translator' => static function (Kirby $kirby): LatteExtension {
                $translator = new Translator($kirby->language()?->code() ?? 'en');

                return new TranslatorExtension([$translator, 'translate']);
            },
            'coreFilters' => static fn (Kirby $kirby): LatteExtension => new CoreFiltersExtension(),
            'sfc' => static fn (Kirby $kirby): LatteExtension => new SfcExtension(),
            'templateDependencies' => static fn (Kirby $kirby): LatteExtension => new TemplateDependenciesExtension(),
            'tracy' => $this->isTracyInstalled
                ? static fn (Kirby $kirby): LatteExtension => new TracyExtension()
                : null,
            'rawPhp' => static fn (Kirby $kirby): LatteExtension => new RawPhpExtension(),
        ];
    }

    /**
     * Installs Barista's custom file loader.
     */
    protected function configureLatteLoader(LatteEngine $latte): void
    {
        $latte->setLoader(new FileLoader());
    }

    /**
     * Points Latte at Barista's cache directory.
     */
    protected function configureLatteCache(LatteEngine $latte): void
    {
        $latte->setCacheDirectory($this->cacheDirectory);
    }

    /**
     * Runs the post-initialization hook.
     */
    protected function applyInitHook(LatteEngine $latte): LatteEngine
    {
        return $this->kirby->apply('jan-herman.barista.init:after', ['latte' => $latte], 'latte');
    }

    /**
     * Resolves the configured cache directory path.
     */
    protected function resolveCacheDirectory(): string
    {
        $path = $this->getOption('cacheDirectory', $this->kirby->root('cache') . '/barista');

        if (is_callable($path) === true) {
            return $path();
        }

        return $path;
    }

    /**
     * Creates and validates the cache directory.
     */
    protected function ensureCacheDirectory(): void
    {
        if (Dir::exists($this->cacheDirectory) === false) {
            try {
                Dir::make($this->cacheDirectory);
            } catch (Exception $e) {
                throw new KirbyException($this->cacheDirectory . ' directory is not writable.');
            }
        }

        if (Dir::exists($this->cacheDirectory) === false || is_writable($this->cacheDirectory) === false) {
            throw new KirbyException($this->cacheDirectory . ' directory is not writable.');
        }
    }

    /**
     * Resolves Barista path aliases for a template path.
     */
    public function resolvePathAlias(string $path): string
    {
        $fileLoader = $this->latte->getLoader();

        return $fileLoader->resolvePathAlias($path);
    }

    /**
     * Renders a Latte template directly.
     */
    public function render(string $file, object|array $params = [], ?string $block = null): void
    {
        echo $this->renderToString($file, $params, $block);
    }

    /**
     * Renders a Latte template to a string.
     */
    public function renderToString(string $file, object|array $params = [], ?string $block = null): string
    {
        try {
            $html = $this->latte->renderToString($file, $params, $block);

            return $this->kirby->apply('jan-herman.barista.render:after', [
                'block'  => $block,
                'file'   => $file,
                'html'   => $html,
                'params' => $params,
            ], 'html');
        } catch (Exception $e) {
            if ($this->isLocalhost) {
                throw $e;
            } else {
                if ($this->isTracyInstalled) {
                    Debugger::log($e, Debugger::ERROR);
                }
                return '';
            }
        }
    }
}
