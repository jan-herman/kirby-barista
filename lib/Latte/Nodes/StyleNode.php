<?php

namespace JanHerman\Barista\Latte\Nodes;

class StyleNode extends SfcNode
{
    public const BlockName = '__sfcStyle';
    public const EagerBlockName = '__sfcStyleEager';
    public const LazyBlockName = '__sfcStyleLazy';

    protected const Languages = ['css', 'scss'];
}
