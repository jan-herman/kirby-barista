<?php

namespace JanHerman\Barista\Latte;

use Latte\Extension;

class CoreFiltersExtension extends Extension
{
    public function getFilters(): array
    {
        return [
            'stripNewLines' => Filters::stripNewLines(...),
        ];
    }
}
