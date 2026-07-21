<?php

namespace JanHerman\Barista\Latte;

use Latte\Extension;
use Latte\Runtime\Template;

/**
 * Collects the templates rendered by Latte and their runtime dependencies.
 */
class TemplateDependencies extends Extension
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
     * Returns every template rendered during the current request.
     *
     * @return Template[]
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * Returns the unique template file names rendered during the current request.
     *
     * @return string[]
     */
    public function getFiles(): array
    {
        return array_values(array_unique(array_map(
            static fn (Template $template): string => $template->getName(),
            $this->templates,
        )));
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
    public function getTree(): array
    {
        $children = [];
        $roots = [];

        foreach ($this->templates as $template) {
            $parent = $template->getReferringTemplate();

            if ($parent === null) {
                $roots[] = $template;
                continue;
            }

            $children[spl_object_id($parent)][] = $template;
        }

        return array_map(
            fn (Template $template): array => $this->buildTree($template, $children),
            $roots,
        );
    }

    /**
     * Builds one template dependency branch.
     *
     * @param array<int, Template[]> $children
     * @return array{file: string, relation: string|null, children: array}
     */
    protected function buildTree(Template $template, array $children): array
    {
        return [
            'file' => $template->getName(),
            'relation' => $template->getReferenceType(),
            'children' => array_map(
                fn (Template $child): array => $this->buildTree($child, $children),
                $children[spl_object_id($template)] ?? [],
            ),
        ];
    }
}
