<?php

namespace JanHerman\Barista;

class Translator
{
	public function __construct(private string $lang)
	{}

	public function translate(string $key, ...$params): string
	{
        $fallback = isset($params['fallback']) ? $params['fallback'] : null;
        $translation = $params ? tt($key, $fallback, $params) : t($key, $fallback);

        if (!$translation) {
            trigger_error('Missing translation for \'' . $key  . '\'', E_USER_NOTICE);
            return $key;
        }

		return $translation;
	}
}
