<?php

namespace JanHerman\Barista;

use Latte\Loaders\FileLoader as DefaultFileLoader;

class FileLoader extends DefaultFileLoader
{
    protected ?array $aliases = null;

    public function __construct(?string $baseDir = null)
    {
        parent::__construct($baseDir);

        $aliases = option('jan-herman.barista.pathAliases');

        if ($aliases) {
            $keys = array_map('strlen', array_keys($aliases));
            array_multisort($keys, SORT_DESC, $aliases);

            $this->aliases = $aliases;
        }
    }

    /**
     * Parses file path & resolves aliases
     */
    public function resolveAliases(string $file): string
    {
        if (!$this->aliases) {
            return $file;
        }

        foreach ($this->aliases as $search => $replace) {
            if (str_starts_with($file, $search)) {

                // replace alias with full path
                if (is_string($replace)) {
                    $file = substr_replace($file, $replace, 0, strlen($search));
                } elseif (is_callable($replace)) {
                    $name = substr($file, strlen($search) + 1);
                    $file = $replace($name);
                    if (!$file) {
                        return $search . '/' . $name;
                    }
                }

                // add .latte extension if it's not present
                if (pathinfo($file, PATHINFO_EXTENSION) === '') {
                    $file .= '.latte';
                }

                continue;
            }
        }

        return $file;
    }

    /**
     * Returns referred template name.
     */
    public function getReferredName(string $file, string $referringFile): string
    {
        $file = $this->resolveAliases($file);

        return parent::getReferredName($file, $referringFile);
    }
}
