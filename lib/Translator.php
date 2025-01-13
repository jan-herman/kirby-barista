<?php

namespace JanHerman\Barista;

use Latte\Runtime\Html;

class Translator
{
    private bool $escape_html;

    public function __construct(private string $lang)
    {
        $this->escape_html = option('jan-herman.barista.translator.escapeHtml', true);
    }

    public function translate(string $key, ...$params): string|Html
    {
        $fallback = isset($params['fallback']) ? $params['fallback'] : null;
        $translation = $params ? tt($key, $fallback, $params) : t($key, $fallback);

        if (!$translation) {
            trigger_error('Missing translation for \'' . $key  . '\'', E_USER_NOTICE);
            return $key;
        }

        if ($this->escape_html) {
            return $translation;
        } else {
            return safe_html($translation);
        }
    }
}
