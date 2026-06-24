<?php

namespace JanHerman\Barista;

use Kirby\Filesystem\F;
use Latte\Loaders\FileLoader as DefaultFileLoader;
use const DIRECTORY_SEPARATOR;

class FileLoader extends DefaultFileLoader
{
    protected ?array $aliases = null;

    /**
     * Initializes the default loader and sorts aliases by length.
     */
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
     * Resolves configured aliases and applies the default extension.
     */
    public function resolvePathAlias(string $file): string
    {
        if (!$this->aliases) {
            return $this->withDefaultExtension($file);
        }

        foreach ($this->aliases as $search => $replace) {
            if (!str_starts_with($file, $search)) {
                continue;
            }

            $relativePath = $this->relativeAliasPath($file, $search);
            $resolvedPath = $this->resolveAliasPath($search, $replace, $relativePath);

            if ($resolvedPath !== null) {
                return $resolvedPath;
            }
        }

        return $this->withDefaultExtension($file);
    }

    /**
     * Returns the path segment after the matched alias.
     */
    private function relativeAliasPath(string $file, string $alias): string
    {
        return ltrim(substr($file, strlen($alias)), '/\\');
    }

    /**
     * Resolves one string or callable alias replacement.
     */
    private function resolveAliasPath(string $alias, mixed $replace, string $relativePath): ?string
    {
        if (is_string($replace)) {
            return $this->normalizeResolvedPath($this->joinPath($replace, $relativePath));
        }

        if (is_callable($replace)) {
            $file = $replace($relativePath);

            if (!$file) {
                return $alias . DIRECTORY_SEPARATOR . $relativePath;
            }

            return $this->normalizeResolvedPath($file);
        }

        return null;
    }

    /**
     * Normalizes a resolved path and appends the default extension.
     */
    private function normalizeResolvedPath(string $file): string
    {
        return $this->withDefaultExtension(static::normalizePath($file));
    }

    /**
     * Joins a base path and relative alias path.
     */
    private function joinPath(string $path, string $relativePath): string
    {
        $path = rtrim($path, '/\\');

        if ($relativePath === '') {
            return $path;
        }

        return $path . DIRECTORY_SEPARATOR . $relativePath;
    }

    /**
     * Appends the default Latte extension when the path has none.
     */
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
