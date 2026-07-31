<?php

namespace JanHerman\Barista\Latte;

use Closure;
use InvalidArgumentException;
use JanHerman\Barista\Latte\Nodes\ScriptNode;
use JanHerman\Barista\Latte\Nodes\StyleNode;
use Latte\Extension;
use Latte\Runtime\Template;

/**
 * Collects the templates rendered by Latte and their runtime dependencies.
 */
class TemplateDependenciesExtension extends Extension
{
    /** @var Template[] */
    protected array $templates = [];

    /**
     * Records each template that Latte renders.
     */
    public function beforeRender(Template $template): void
    {
        $this->templates[] = $template;
    }

    /**
     * Clears the recorded templates.
     */
    public function reset(): void
    {
        $this->templates = [];
    }

    /**
     * Returns a filtered snapshot of the collected templates.
     *
     * String filters accept the `file` and `directory` fields together with
     * the `==`, `!=`, `in`, and `not in` operators. Passing only a value uses
     * the `==` operator. Directory filters include nested directories.
     *
     * @param string|Closure(Template): bool $field
     */
    public function filter(string|Closure $field, mixed ...$args): static
    {
        if ($field instanceof Closure) {
            if ($args !== []) {
                throw new InvalidArgumentException('Callback filters do not accept additional arguments.');
            }

            return $this->withTemplates(array_filter($this->templates, $field));
        }

        if (count($args) === 1) {
            $operator = '==';
            $value = $args[0];
        } elseif (count($args) === 2 && is_string($args[0])) {
            [$operator, $value] = $args;
        } else {
            throw new InvalidArgumentException(
                'Filters require a field and value, or a field, operator, and value.',
            );
        }

        if (!in_array($field, ['file', 'directory'], true)) {
            throw new InvalidArgumentException("Unsupported template dependency field: $field");
        }

        if (!in_array($operator, ['==', '!=', 'in', 'not in'], true)) {
            throw new InvalidArgumentException("Unsupported template dependency operator: $operator");
        }

        if (in_array($operator, ['in', 'not in'], true)) {
            if (!is_array($value)) {
                throw new InvalidArgumentException("The $operator operator requires an array.");
            }

            $values = array_values($value);
        } else {
            $values = [$value];
        }

        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new InvalidArgumentException('Template dependency filter values must be non-empty strings.');
            }
        }

        $values = array_map(
            fn (string $value): string => $this->normalizeFilterValue($field, $value),
            $values,
        );

        return $this->withTemplates(array_filter(
            $this->templates,
            fn (Template $template): bool => $this->matches(
                $template,
                $field,
                $operator,
                $values,
            ),
        ));
    }

    /**
     * Alias for filter().
     */
    public function filterBy(mixed ...$args): static
    {
        return $this->filter(...$args);
    }

    /**
     * Returns every template rendered during the current request.
     *
     * @return Template[]
     */
    public function templates(): array
    {
        return $this->templates;
    }

    /**
     * Returns the unique template file names rendered during the current request.
     *
     * @return string[]
     */
    public function files(): array
    {
        return $this->filesFromTemplates($this->templates);
    }

    /**
     * Returns unique rendered template files containing matching {style} tags.
     *
     * @param 'eager'|'lazy'|null $loading
     * @return string[]
     */
    public function filesWithStyle(?string $loading = null): array
    {
        return $this->filesWithAssetBlock(
            StyleNode::BlockName,
            StyleNode::EagerBlockName,
            StyleNode::LazyBlockName,
            $loading,
        );
    }

    /**
     * Returns unique rendered template files containing matching {script} tags.
     *
     * @param 'eager'|'lazy'|null $loading
     * @return string[]
     */
    public function filesWithScript(?string $loading = null): array
    {
        return $this->filesWithAssetBlock(
            ScriptNode::BlockName,
            ScriptNode::EagerBlockName,
            ScriptNode::LazyBlockName,
            $loading,
        );
    }

    /**
     * Returns the runtime template dependency tree.
     *
     * Every branch contains its file name, its relation to the parent template,
     * and the templates it rendered directly. Multiple root templates are
     * returned when Barista renders more than one top-level template.
     *
     * @return array<int, array{file: string, relation: string|null, children: array}>
     */
    public function tree(): array
    {
        $children = [];
        $roots = [];
        $templateIds = array_fill_keys(array_map(
            static fn (Template $template): int => spl_object_id($template),
            $this->templates,
        ), true);

        foreach ($this->templates as $template) {
            $parent = $template->getReferringTemplate();

            if ($parent === null || !isset($templateIds[spl_object_id($parent)])) {
                $roots[] = $template;
                continue;
            }

            $children[spl_object_id($parent)][] = $template;
        }

        return array_map(
            fn (Template $template): array => $this->buildTree($template, $children, true),
            $roots,
        );
    }

    /**
     * Returns whether a template matches a parsed filter.
     *
     * @param string[] $values
     */
    protected function matches(
        Template $template,
        string $field,
        string $operator,
        array $values,
    ): bool {
        $matches = match ($field) {
            'file' => in_array(
                $this->normalizePath($template->getName()),
                $values,
                true,
            ),
            'directory' => $this->isWithinDirectory($template->getName(), $values),
        };

        return match ($operator) {
            '==', 'in' => $matches,
            '!=', 'not in' => !$matches,
        };
    }

    /**
     * Returns whether a template belongs to one of the directories.
     *
     * @param string[] $directories
     */
    protected function isWithinDirectory(string $file, array $directories): bool
    {
        $file = $this->normalizePath($file);

        foreach ($directories as $directory) {
            $prefix = $directory === '/' ? '/' : $directory . '/';

            if (str_starts_with($file, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalizes a filter value for comparisons.
     */
    protected function normalizeFilterValue(string $field, string $value): string
    {
        $value = $this->normalizePath($value);

        return $field === 'directory' && $value !== '/'
            ? rtrim($value, '/')
            : $value;
    }

    /**
     * Normalizes path separators without changing the loader's identifier.
     */
    protected function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Returns a cloned collector containing only the supplied templates.
     *
     * @param Template[] $templates
     */
    protected function withTemplates(array $templates): static
    {
        $clone = clone $this;
        $clone->templates = array_values($templates);

        return $clone;
    }

    /**
     * Returns unique file names for templates containing a specific block.
     *
     * @return string[]
     */
    protected function filesWithBlock(string $block): array
    {
        return $this->filesFromTemplates(array_filter(
            $this->templates,
            static fn (Template $template): bool => $template->hasBlock($block),
        ));
    }

    /**
     * Resolves an optional loading mode to its compiled SFC metadata block.
     *
     * @return string[]
     */
    protected function filesWithAssetBlock(
        string $block,
        string $eagerBlock,
        string $lazyBlock,
        ?string $loading,
    ): array {
        $block = match ($loading) {
            null => $block,
            'eager' => $eagerBlock,
            'lazy' => $lazyBlock,
            default => throw new InvalidArgumentException(
                "Unsupported SFC loading mode: $loading",
            ),
        };

        return $this->filesWithBlock($block);
    }

    /**
     * Maps templates to unique file names while preserving render order.
     *
     * @param Template[] $templates
     * @return string[]
     */
    protected function filesFromTemplates(array $templates): array
    {
        return array_values(array_unique(array_map(
            static fn (Template $template): string => $template->getName(),
            $templates,
        )));
    }

    /**
     * Builds one template dependency branch.
     *
     * @param array<int, Template[]> $children
     * @return array{file: string, relation: string|null, children: array}
     */
    protected function buildTree(
        Template $template,
        array $children,
        bool $root = false,
    ): array
    {
        return [
            'file' => $template->getName(),
            'relation' => $root ? null : $template->getReferenceType(),
            'children' => array_map(
                fn (Template $child): array => $this->buildTree($child, $children),
                $children[spl_object_id($template)] ?? [],
            ),
        ];
    }
}
