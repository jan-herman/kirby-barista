<?php

namespace JanHerman\Barista;

use Latte\Extension;
use Latte\Runtime\FilterInfo;

class LatteExtension extends Extension
{
    public function getTags(): array
    {
        return option('jan-herman.barista.tags', []);
    }

    public function getFilters(): array
    {
        $built_in_filters = [
            'stripNewLines' => function (FilterInfo $info, string $html): string {
                $lines = preg_split('/\R/', $html);
                $lines = array_map('ltrim', $lines);
                return implode('', $lines);
            }
        ];

        $custom_filters = option('jan-herman.barista.filters', []);

        return array_merge($built_in_filters, $custom_filters);
    }

    public function getFunctions(): array
    {
        return option('jan-herman.barista.functions', []);
    }
}
