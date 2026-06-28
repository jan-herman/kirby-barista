<?php

namespace JanHerman\Barista\Latte;

use Latte\Runtime\Html;

class Translator
{
    private bool $escapeHtml;

    public function __construct(private string $lang)
    {
        $this->escapeHtml = \option('jan-herman.barista.translator.escapeHtml', true);
    }

    public function translate(string $key, ...$params): string|Html
    {
        $fallback = $params[0] ?? $params['fallback'] ?? null;
        $translation = $params ? \tt($key, $fallback, $params) : \t($key, $fallback);

        if (!$translation) {
            \trigger_error('Missing translation for \'' . $key  . '\'', E_USER_NOTICE);
            return $key;
        }

        if ($this->escapeHtml) {
            return $translation;
        } else {
            return \safe_html($translation);
        }
    }
}
