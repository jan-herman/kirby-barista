<?php

namespace JanHerman\Barista;

use Kirby\Filesystem\F;
use Kirby\Template\Template as DefaultTemplate;

class Template extends DefaultTemplate
{
    public function extension(): string
    {
        return 'latte';
    }

    public function isLatte(): bool
    {
        return F::extension($this->file()) === $this->extension();
    }

    public function render(array $data = []): string
    {
        if ($this->isLatte() === false) {
            return parent::render($data);
        }

        return barista()->renderToString($this->file(), $data);
    }

    public function renderBlock(string $name, array $data = []): string
    {
        if ($this->isLatte() === false) {
            return '';
        }

        return barista()->renderToString($this->file(), $data, $name);
    }
}
