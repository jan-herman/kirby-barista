<?php

namespace JanHerman\Barista\LatteSfc\Nodes;

class ScriptNode extends SfcNode
{
    public const BlockName = '__sfcScript';
    public const EagerBlockName = '__sfcScriptEager';
    public const LazyBlockName = '__sfcScriptLazy';

    protected const Languages = ['js', 'ts'];
}
