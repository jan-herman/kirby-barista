<?php

namespace JanHerman\Barista;

use Kirby\Template\Snippet as DefaultSnippet;

use Kirby\Cms\App;
use Kirby\Toolkit\A;

class Snippet extends DefaultSnippet
{
    /**
     * Absolute path to the file for
     * the snippet/s taking snippets defined in plugins
     * into account
     */
    public static function file(string|array $name): string|null
    {
        $kirby = App::instance();
        $root  = static::root();
        $names = A::wrap($name);

        foreach ($names as $name) {
            $name = (string)$name;
            $php_file = $root . '/' . $name . '.php';
            $latte_file = $root . '/' . $name . '.latte';

            if (file_exists($php_file) === true) {
                $file = $php_file;
            } elseif (file_exists($latte_file) === true) {
                $file = $latte_file;
            } else {
                $file = $kirby->extensions('snippets')[$name] ?? null;
            }

            if ($file) {
                break;
            }
        }

        return $file;
    }
}
