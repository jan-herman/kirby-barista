<?php

namespace JanHerman\Barista\Latte;

use Generator;
use JanHerman\Barista\Latte\Nodes\EmbedNode;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;
use Latte\Engine;
use Latte\Extension;

class LatteExtension extends Extension
{
    public function getTags(): array
    {
        $customTags = option('jan-herman.barista.tags', []);
        $builtInTags = [];

        if (option('jan-herman.barista.implicitEmbedBlock', true) !== false) {
            $blockName = option('jan-herman.barista.implicitEmbedBlockName', 'default');

            $builtInTags['embed'] = function (Tag $tag, TemplateParser $parser) use ($blockName): Generator {
                return yield from EmbedNode::createWithImplicitBlock($tag, $parser, $blockName);
            };
        }

        return array_merge($builtInTags, $customTags);
    }

    public function getCacheKey(Engine $engine): mixed
    {
        return [
            'implicitEmbedBlock' => option('jan-herman.barista.implicitEmbedBlock', true),
            'implicitEmbedBlockName' => option('jan-herman.barista.implicitEmbedBlockName', 'default'),
        ];
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
