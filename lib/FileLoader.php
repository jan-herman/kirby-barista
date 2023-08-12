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

		return parent::getReferredName($file, $referringFile);
	}
}
