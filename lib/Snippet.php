<?php

namespace JanHerman\Barista;

use Kirby\Template\Snippet as DefaultSnippet;

use Kirby\Cms\App;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

class Snippet extends DefaultSnippet
{
    /**
     * Returns either an open snippet capturing slots
     * or the template string for self-enclosed snippets
     */
    public static function factory(
        string|array|null $name,
        array $data = [],
        bool $slots = false
    ): static|string {
        $file = $name !== null ? static::file($name) : null;

        if (Str::endsWith($file, '.latte')) {
            return barista()->renderToString($file, $data);
        }

        if ($slots === true) {
            return static::begin($file, $data);
        }

        $data = static::scope($data);
        return static::load($file, $data);
    }

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

            // retrieve the file from the cache if it exists
			if (isset(static::$cache[$name]) === true) {
				return static::$cache[$name];
			}

            $phpFile = $root . '/' . $name . '.php';
            $latteFile = $root . '/' . $name . '.latte';

            if (F::exists($phpFile, $root) === true) {
                $file = $phpFile;
            } elseif (F::exists($latteFile, $root) === true) {
                $file = $latteFile;
            } else {
                $file = $kirby->extensions('snippets')[$name] ?? null;
            }

            if ($file) {
				// cache the file for future use
				return static::$cache[$name] = $file;
			}
        }

        return null;
    }
}
