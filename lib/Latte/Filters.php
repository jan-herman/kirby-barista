<?php

namespace JanHerman\Barista\Latte;

use Latte\Runtime\FilterInfo;

class Filters
{
    public static function stripNewLines(FilterInfo $info, string $html): string
    {
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
}
