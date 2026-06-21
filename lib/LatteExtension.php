<?php

namespace JanHerman\Barista;

use Generator;
use JanHerman\Barista\Latte\Nodes\EmbedNode;
use JanHerman\Barista\Latte\Passes\ImplicitLayoutBlockPass;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;
use Latte\Engine;
use Latte\Extension;
use Latte\Runtime\FilterInfo;

class LatteExtension extends Extension
{
    public function getTags(): array
    {
        $tags = [];

        if (option('jan-herman.barista.implicitEmbedBlock', true) !== false) {
            $block_name = option('jan-herman.barista.implicitEmbedBlockName', 'default');

            $tags['embed'] = function (Tag $tag, TemplateParser $parser) use ($block_name): Generator {
                return yield from EmbedNode::createWithImplicitBlock($tag, $parser, $block_name);
            };
        }

        return array_merge($tags, option('jan-herman.barista.tags', []));
    }

    public function getPasses(): array
    {
        if (option('jan-herman.barista.implicitLayoutBlock', false) === false) {
            return [];
        }

        return [
            'implicitLayoutBlock' => new ImplicitLayoutBlockPass(option('jan-herman.barista.implicitLayoutBlockName', 'default')),
        ];
    }

    public function getCacheKey(Engine $engine): mixed
    {
        return [
            'implicitEmbedBlock' => option('jan-herman.barista.implicitEmbedBlock', true),
            'implicitEmbedBlockName' => option('jan-herman.barista.implicitEmbedBlockName', 'default'),
            'implicitLayoutBlock' => option('jan-herman.barista.implicitLayoutBlock', false),
            'implicitLayoutBlockName' => option('jan-herman.barista.implicitLayoutBlockName', 'default'),
        ];
    }

    public function getFilters(): array
    {
        $built_in_filters = [
            'stripNewLines' => function (FilterInfo $info, string $html): string {
                $lines = preg_split('/\R/', $html);

                foreach ($lines as $i => $line) {
                    if ($i > 0) {
                        $lines[$i] = ltrim($line);
                    }
                }

                $result = implode('', $lines);

                if (preg_match('/(\R)$/', $html, $matches)) {
                    $result .= $matches[1];
                }

                return $result;
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
