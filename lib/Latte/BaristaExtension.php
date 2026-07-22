<?php

namespace JanHerman\Barista\Latte;

use Latte\Extension;

class BaristaExtension extends Extension
{
    public function getTags(): array
    {
        return option('jan-herman.barista.tags', []);
    }

    public function getFilters(): array
    {
        $customFilters = option('jan-herman.barista.filters', []);
        $builtInFilters = [
            'stripNewLines' => Filters::stripNewLines(...),
        ];

        return array_merge($builtInFilters, $customFilters);
    }

    public function getFunctions(): array
    {
        return option('jan-herman.barista.functions', []);
    }
}
