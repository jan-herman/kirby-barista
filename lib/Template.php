<?php

namespace JanHerman\Barista;

use Exception;
use Kirby\Cms\App;
use Kirby\Template\Template as DefaultTemplate;
use Kirby\Exception\Exception as KirbyException;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Tpl;
use Throwable;

class Template extends DefaultTemplate
{
    protected $templatesDirectory;

    public function __construct(string $name, string $type = 'html', string $defaultType = 'html')
    {
        parent::__construct($name, $type, $defaultType);
        $this->templatesDirectory = $this->getTemplatesDirectory();
    }

    /**
    * @param array $data
    * @return string
    * @throws \Kirby\Exception\Exception
    */
    public function render(array $data = []): string
    {
        if ($this->hasDefaultType() === true) {
            $html = barista()->renderToString($this->file(), $data);
        } else {
            try {
                $html = Tpl::load($this->file(), $data);
            } catch (Throwable $e) {
                throw new KirbyException($e->getMessage());
            }
        }

        return $html;
    }

    public function extension(): string
    {
        return 'latte';
    }

    public function file(): ?string
    {
        if ($this->hasDefaultType() === true) {
            try {
                // Try the default template in the default template directory.
                return F::realpath($this->getFilename(), $this->getTemplatesDirectory());
            } catch (Exception $e) {
                //
            }

            // Look for the default template provided by an extension.
            $path = App::instance()->extension($this->store(), $this->name());

            if ($path !== null) {
                return $path;
            }
        }

        // disallow latte extension for content representation, for ex: /blog.latte
        if ($this->type() === 'latte') {
            return null;
        }

        $name = $this->name() . '.' . $this->type();

        try {
            // Try the template with type extension in the default template directory.
            return F::realpath($this->getFilename($name), $this->getTemplatesDirectory());
        } catch (Exception $e) {
            // Look for the template with type extension provided by an extension.
            // This might be null if the template does not exist.
            $fallback = App::instance()->extension($this->store(), $name);

            // fallback null with provided extension, set header as default type instead extension
            if ($fallback === null) {
                App::instance()->response()->type($this->defaultType());
            }

            return $fallback;
        }
    }

    public function getFilename(string $name = null): string
    {
        if ($name === null) {
            return $this->getTemplatesDirectory() . '/' . $this->name() . '.' . $this->extension();
        }

        if ($this->hasDefaultType() === true) {
            return $this->getTemplatesDirectory() . '/' . $this->name() . '.' . $this->extension();
        }

        return $this->getTemplatesDirectory() . '/' . $name . '.' . $this->extension();
    }

    protected function getTemplatesDirectory(): string
    {
        if ($this->templatesDirectory !== null) {
            return $this->templatesDirectory;
        }

        $path = option('jan-herman.barista.templatesDirectory');

        if ($path !== null && is_dir($path) === true) {
            if (is_callable($path)) {
                return $path();
            }

            $path = App::instance()->root() . '/' . $path;
        }

        if (empty($path) === true) {
            return $this->root();
        }

        return $path;
    }
}
