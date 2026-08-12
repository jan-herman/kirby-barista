<?php

namespace JanHerman\Barista\LatteSfc\Nodes;

class StyleNode extends SfcNode
{
    public const BlockName = '__sfcStyle';
    public const EagerBlockName = '__sfcStyleEager';
    public const LazyBlockName = '__sfcStyleLazy';

    protected const Languages = ['css', 'scss'];
}
