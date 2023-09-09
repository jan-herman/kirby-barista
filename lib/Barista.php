<?php

namespace JanHerman\Barista;

use JanHerman\Barista\LatteExtension;

use Exception;
use Kirby\Cms\App as Kirby;
use Kirby\Exception\Exception as KirbyException;
use Kirby\Filesystem\Dir;

use Latte\Engine as LatteEngine;
use Latte\Essential\TranslatorExtension;
use Latte\Bridges\Tracy\TracyExtension;

use Tracy\Debugger;

class Barista
{
    protected static $instance;

    protected $kirby;
    protected $is_localhost;
    protected $is_tracy_installed;
    protected $latte;
    protected $temp_directory;

    private function __construct(Kirby $kirby)
    {
        $this->kirby = $kirby;
        $this->is_localhost = $kirby->environment()->isLocal();
        $this->is_tracy_installed = class_exists('Tracy\Debugger');

        $this->temp_directory = $this->setTempDirectory();
        $this->checkTempDirectory();

        // Init Latte
        $this->latte = new LatteEngine;

        // Register custom tags, filters & functions
        $this->latte->addExtension(new LatteExtension());

        // Add Extension - Translator
        $lang = $this->kirby->language()->code();
        $translator = new Translator($lang);
        $translator_extension = new TranslatorExtension([$translator, 'translate']);
        $this->latte->addExtension($translator_extension);

        // Add Extension - Tracy
        if ($this->is_tracy_installed) {
            $this->latte->addExtension(new TracyExtension());
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
        try {
			$this->latte->render($view, $data);
		} catch (Exception $e) {
            if ($this->is_localhost) {
                throw $e;
            } else {
                if ($this->is_tracy_installed) {
                    Debugger::log($e, Debugger::ERROR);
                }
                return;
            }
		}
    }

    public function renderToString(string $view, array $data = []): string
    {
        try {
			return $this->latte->renderToString($view, $data);
		} catch (Exception $e) {
            if ($this->is_localhost) {
                throw $e;
            } else {
                if ($this->is_tracy_installed) {
                    Debugger::log($e, Debugger::ERROR);
                }
                return '';
            }
		}
    }
}
