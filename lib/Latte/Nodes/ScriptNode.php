<?php

namespace JanHerman\Barista\Latte\Nodes;

class ScriptNode extends SfcNode
{
    public const BlockName = '__sfc_script';

    protected const Languages = ['js', 'ts'];
}
