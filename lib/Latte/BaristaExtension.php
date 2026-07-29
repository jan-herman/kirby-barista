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
        return option('jan-herman.barista.filters', []);
    }

    public function getFunctions(): array
    {
        return option('jan-herman.barista.functions', []);
    }
}
