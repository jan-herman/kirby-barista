<?php

namespace JanHerman\Barista;

use Kirby\Filesystem\F;
use Latte\Loaders\FileLoader as DefaultFileLoader;
use const DIRECTORY_SEPARATOR;

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
    public function resolvePathAlias(string $file): string
    {
        if (!$this->aliases) {
            return $file;
        }

        foreach ($this->aliases as $search => $replace) {
            if (!str_starts_with($file, $search)) {
                continue;
            }

            $relativePath = ltrim(substr($file, strlen($search)), '/\\');

            if (is_string($replace)) {
                return $this->withDefaultExtension(
                    static::normalizePath($this->joinPath($replace, $relativePath))
                );
            } elseif (is_callable($replace)) {
                $file = $replace($relativePath);

                if (!$file) {
                    return $search . DIRECTORY_SEPARATOR . $relativePath;
                }

                return $this->withDefaultExtension(static::normalizePath($file));
            }
        }

        return $file;
    }

    private function joinPath(string $path, string $relativePath): string
    {
        $path = rtrim($path, '/\\');

        if ($relativePath === '') {
            return $path;
        }

        return $path . DIRECTORY_SEPARATOR . $relativePath;
    }

    private function withDefaultExtension(string $file): string
    {
        if (F::extension($file) !== '') {
            return $file;
        }

        return $file . '.latte';
    }

    /**
     * Returns referred template name.
     */
    public function getReferredName(string $file, string $referringFile): string
    {
        $file = $this->resolvePathAlias($file);

        return parent::getReferredName($file, $referringFile);
    }
}
