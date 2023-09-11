<?php

namespace JanHerman\Barista;

use Kirby\Template\Template as DefaultTemplate;
use Kirby\Toolkit\Str;

class Template extends DefaultTemplate
{
    public function render(array $data = []): string
    {
        if (Str::endsWith($this->file(), '.latte')) {
            return barista()->renderToString($this->file(), $data);
        } else {
            return parent::render();
        }
    }

    public function extension(): string
    {
        return 'latte';
    }
}
