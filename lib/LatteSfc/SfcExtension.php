<?php

namespace JanHerman\Barista\LatteSfc;

use JanHerman\Barista\LatteSfc\Nodes\ScriptNode;
use JanHerman\Barista\LatteSfc\Nodes\StyleNode;
use Latte\Extension;

class SfcExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'style' => StyleNode::create(...),
            'script' => ScriptNode::create(...),
        ];
    }
}
