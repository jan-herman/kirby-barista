<?php

namespace JanHerman\Barista\Latte;

use JanHerman\Barista\Latte\Nodes\ScriptNode;
use JanHerman\Barista\Latte\Nodes\StyleNode;
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
