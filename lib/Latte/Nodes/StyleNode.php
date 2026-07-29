<?php

namespace JanHerman\Barista\Latte\Nodes;

class StyleNode extends SfcNode
{
    public const BlockName = '__sfc_style';

    protected const Languages = ['css', 'scss'];
}
