<?php

namespace JanHerman\Barista;

use Exception;
use Kirby\Cms\App as Kirby;
use Kirby\Exception\Exception as KirbyException;
use Kirby\Toolkit\Dir;

use Latte\Engine as LatteEngine;
use Latte\Essential\TranslatorExtension;
use Latte\Bridges\Tracy\TracyExtension;
// use Latte\Macros\MacroSet;

class Barista
{
    protected static $instance;

    protected $kirby;
    protected $latte;
    protected $temp_directory;

    private function __construct(Kirby $kirby)
    {
        $this->kirby = $kirby;

        $this->temp_directory = $this->setTempDirectory();
        $this->checkTempDirectory();

        // Init Latte
        $this->latte = new LatteEngine;

        // Register custom filters & functions
        $this->setFilters();
        $this->setFunctions();
        // $this->setMacros();

        // Add Extension - Translator
        $lang = $this->kirby->language()->code();
        $translator = new Translator($lang);
        $translator_extension = new TranslatorExtension([$translator, 'translate']);
        $this->latte->addExtension($translator_extension);

        // Add Extension - Tracy
        if (class_exists('Tracy\Debugger')) {
            $this->latte->addExtension(new TracyExtension);
        }

        // Set custom file loader
        $this->latte->setLoader(new FileLoader());

        // Set temp directory
        $this->latte->setTempDirectory($this->temp_directory);
    }

    public static function getInstance(Kirby $kirby)
    {
        return self::$instance ??= new self($kirby);
    }

    protected function setTempDirectory(): string
    {
        $path = option('jan-herman.barista.tempDirectory');

        if (is_callable($path) === true) {
            return $path();
        }

        if (empty($path) === true) {
            return $this->kirby->root('cache') . '/temp';
        }

        return $path;
    }

    protected function checkTempDirectory(): void
    {
        if (Dir::exists($this->temp_directory) === false) {
            try {
                Dir::make($this->temp_directory);
            } catch (Exception $e) {
                throw new KirbyException($this->temp_directory . ' directory is not writable.');
            }
        }
    }

    protected function setFilters(): void
    {
        foreach (option('jan-herman.barista.filters', []) as $filter => $callback) {
            $this->addFilter($filter, $callback);
        }
    }

    protected function setFunctions(): void
    {
        foreach (option('jan-herman.barista.functions', []) as $function => $callback) {
            $this->addFunction($function, $callback);
        }
    }

    /* protected function setMacros(): void
    {
        $macros = option('jan-herman.barista.macros', []);

        if (empty($macros) === false) {
            $set = new MacroSet($this->latte->getCompiler());

            foreach (option('jan-herman.barista.macros', []) as $macro) {
                $set->addMacro(...$macro);
            }
        }
    } */

    protected function addFilter(string $name, callable $callback): void
    {
        $this->latte->addFilter($name, $callback);
    }

    protected function addFunction(string $name, callable $callback): void
    {
        $this->latte->addFunction($name, $callback);
    }

    public function render(string $view, array $data = []): void
    {
        $this->latte->render($view, $data);
    }

    public function renderToString(string $view, array $data = []): string
    {
        return $this->latte->renderToString($view, $data);
    }
}
