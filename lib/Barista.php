<?php

namespace JanHerman\Barista;

use JanHerman\Barista\LatteExtension;
use Kirby\Cms\App as Kirby;
use Kirby\Exception\Exception as KirbyException;
use Kirby\Filesystem\Dir;
use Latte\Engine as LatteEngine;
use Latte\Feature;
use Latte\Essential\TranslatorExtension;
use Latte\Bridges\Tracy\TracyExtension;
use Tracy\Debugger;
use Exception;

class Barista
{
    protected static self $instance;
    protected Kirby $kirby;
    protected bool $isLocalhost;
    protected bool $isTracyInstalled;
    protected string $cacheDirectory;
    protected LatteEngine $latte;

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
     * Registers Barista, translator and optional Tracy extensions.
     */
    protected function registerLatteExtensions(LatteEngine $latte): void
    {
        $latte->addExtension(new LatteExtension());

        $lang = $this->kirby->language()?->code() ?? 'en';
        $translator = new Translator($lang);
        $translatorExtension = new TranslatorExtension([$translator, 'translate']);
        $latte->addExtension($translatorExtension);

        if ($this->isTracyInstalled) {
            $latte->addExtension(new TracyExtension());
        }
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
