<?php

namespace JanHerman\Barista;

use Latte\Loaders;

class FileLoader extends Loaders\FileLoader
{
    protected ?array $aliases = null;

    /**
	 * Parses file path & resolves aliases
	 */
    public function resolveAliases(string $file): string
    {
        if ($this->aliases === []) {
            return $file;
        }

        if ($this->aliases === null) {
            $aliases = option('jan-herman.barista.pathAliases');

            if (!$aliases) {
                $this->aliases = [];
                return $file;
            }

            $keys = array_map('strlen', array_keys($aliases));
            array_multisort($keys, SORT_DESC, $aliases);

            $this->aliases = $aliases;
        }

        foreach ($this->aliases as $search => $replace) {
            if (str_starts_with($file, $search)) {
                $file = substr_replace($file, $replace, 0, strlen($search));
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

		if ($this->baseDir || !preg_match('#/|\\\\|[a-z][a-z0-9+.-]*:#iA', $file)) {
			$file = $this->normalizePath($referringFile . '/../' . $file);
		}

		return $file;
	}
}
