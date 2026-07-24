<?php

namespace JanHerman\Barista\Latte;

use Latte\Extension;
use Latte\Runtime\Template;

/**
 * Collects the templates rendered by Latte and their runtime dependencies.
 */
class TemplateDependencies extends Extension
{
    protected const SfcScriptBlock = '__sfc_script';
    protected const SfcStyleBlock = '__sfc_style';

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
     * Returns the unique rendered template files that contain a {style} tag.
     *
     * @return string[]
     */
    public function filesWithStyle(): array
    {
        return $this->filesWithBlock(self::SfcStyleBlock);
    }

    /**
     * Returns the unique rendered template files that contain a {script} tag.
     *
     * @return string[]
     */
    public function filesWithScript(): array
    {
        return $this->filesWithBlock(self::SfcScriptBlock);
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
