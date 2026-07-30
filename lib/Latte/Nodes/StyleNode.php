<?php

namespace JanHerman\Barista\Latte\Nodes;

class StyleNode extends SfcNode
{
    public const BlockName = '__sfc_style';
    public const EagerBlockName = '__sfc_style_eager';
    public const LazyBlockName = '__sfc_style_lazy';

    protected const Languages = ['css', 'scss'];
}
