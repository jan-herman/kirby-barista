<?php

namespace JanHerman\Barista;

use Exception;
use Kirby\Cms\App as Kirby;
use Kirby\Exception\Exception as KirbyException;
use Kirby\Toolkit\Dir;

use Latte\Engine as LatteEngine;
use Latte\Essential\TranslatorExtension;
use Latte\Bridges\Tracy\TracyExtension;

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

        // Register custom tags, filters & functions
        $this->latte->addExtension(new LatteExtension);

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
        $path = option('jan-herman.barista.tempDirectory', $this->kirby->root('cache') . '/barista');

        if (is_callable($path) === true) {
            return $path();
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
