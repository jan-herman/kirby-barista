<?php

namespace JanHerman\Barista\Latte;

use Generator;
use JanHerman\Barista\Latte\Nodes\EmbedNode;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;
use Latte\Engine;
use Latte\Extension;

class ImplicitEmbedBlockExtension extends Extension
{
    public function __construct(
        protected string $blockName = 'default',
    ) {
    }

    public function getTags(): array
    {
        return [
            'embed' => function (Tag $tag, TemplateParser $parser): Generator {
                return yield from EmbedNode::createWithImplicitBlock($tag, $parser, $this->blockName);
            },
        ];
    }

    public function getCacheKey(Engine $engine): mixed
    {
        return $this->blockName;
    }
}
