<?php

namespace JanHerman\Barista\Latte\Nodes;

class ScriptNode extends SfcNode
{
    public const BlockName = '__sfc_script';
    public const EagerBlockName = '__sfc_script_eager';
    public const LazyBlockName = '__sfc_script_lazy';

    protected const Languages = ['js', 'ts'];
}
