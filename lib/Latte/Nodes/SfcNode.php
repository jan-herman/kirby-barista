<?php

namespace JanHerman\Barista\Latte\Nodes;

use Generator;
use Latte\CompileException;
use Latte\Compiler\Block;
use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Php\IdentifierNode;
use Latte\Compiler\Nodes\Php\Scalar\BooleanNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateLexer;
use Latte\Compiler\TemplateParser;
use Latte\Runtime\Template;

abstract class SfcNode extends StatementNode
{
    protected const Languages = [];

    protected Tag $tag;
    protected bool $lazy = false;

    /** @return Generator<int, ?list<string>, array{mixed, ?Tag}, static> */
    public static function create(Tag $tag, TemplateParser $parser): Generator
    {
        if ($tag->isNAttribute()) {
            throw new CompileException("Attribute {$tag->getNotation()} is not supported.", $tag->position);
        }

        if ($tag->void) {
            throw new CompileException("Tag {{$tag->name}/} must be paired with {{$tag->name}}.", $tag->position);
        }

        $lazy = static::validateProperties($tag);
        $tag->outputMode = $tag::OutputRemoveIndentation;
        $node = $tag->node = new static;
        $node->tag = $tag;
        $node->lazy = $lazy;

        $lexer = $parser->getLexer();
        $lexer->setSyntax('off', $tag->name);
        $lexer->pushState(TemplateLexer::StatePlain);

        try {
            [$content] = yield;
        } finally {
            $lexer->popState();
            $lexer->popSyntax();
        }

        static::validateContent($tag, $content);

        return $node;
    }

    /**
     * Registers an empty local block as durable metadata on the compiled template.
     *
     * Returning an empty string keeps the SFC tag out of the rendered template,
     * while Latte's generated Blocks constant makes the marker available through
     * Runtime\Template::hasBlock() before the template is rendered.
     */
    public function print(PrintContext $context): string
    {
        $this->registerBlock($context, $this::BlockName);
        $this->registerBlock(
            $context,
            $this::BlockName . ($this->lazy ? '_lazy' : '_eager'),
        );

        return '';
    }

    /**
     * Registers one empty local metadata block unless it already exists.
     */
    private function registerBlock(PrintContext $context, string $blockName): void
    {
        foreach ($context->blocks as $registeredBlock) {
            if (
                $registeredBlock->layer === Template::LayerLocal
                && $registeredBlock->name instanceof StringNode
                && $registeredBlock->name->value === $blockName
            ) {
                return;
            }
        }

        $block = new Block(
            new StringNode($blockName, $this->position),
            Template::LayerLocal,
            $this->tag,
        );

        $context->addBlock($block);
        $block->content = '';
    }

    public function &getIterator(): Generator
    {
        false && yield;
    }

    private static function validateProperties(Tag $tag): bool
    {
        $properties = $tag->parser->parseArguments();
        $lazy = false;
        $hasLazyProperty = false;

        foreach ($properties->items as $property) {
            $isPositionalLazy = $property->key === null
                && $property->value instanceof StringNode
                && $property->value->value === 'lazy';
            $isBareLazy = $isPositionalLazy
                && static::propertySource($tag, $property->value) === 'lazy';
            $isQuotedLazy = $isPositionalLazy && !$isBareLazy;
            $isNamedLazy = $property->key instanceof IdentifierNode
                && $property->key->name === 'lazy';

            if ($isBareLazy || $isQuotedLazy || $isNamedLazy) {
                if ($hasLazyProperty) {
                    throw new CompileException(
                        "The lazy property in {{$tag->name}} must not be declared more than once.",
                        $property->position,
                    );
                }

                $hasLazyProperty = true;

                if ($isBareLazy) {
                    $lazy = true;
                    continue;
                }

                if (!$isNamedLazy || !$property->value instanceof BooleanNode) {
                    throw new CompileException(
                        "The lazy property in {{$tag->name}} must be a bare flag or a static boolean.",
                        $property->value->position,
                    );
                }

                $lazy = $property->value->value;
                continue;
            }

            if (
                !$property->key instanceof IdentifierNode
                || $property->key->name !== 'lang'
            ) {
                continue;
            }

            if (!$property->value instanceof StringNode) {
                throw new CompileException("The lang property in {{$tag->name}} must be a static string.", $property->value->position);
            }

            if (!in_array($property->value->value, static::Languages, true)) {
                $languages = implode("', '", static::Languages);
                throw new CompileException(
                    "Unsupported lang '{$property->value->value}' in {{$tag->name}}. Allowed values are '$languages'.",
                    $property->value->position,
                );
            }
        }

        return $lazy;
    }

    /**
     * Returns the exact source represented by one parsed property value.
     */
    private static function propertySource(Tag $tag, StringNode $value): string
    {
        if ($value->position === null || $value->end === null) {
            return '';
        }

        return substr(
            $tag->getNotation(true),
            $value->position->offset - $tag->position->offset,
            $value->end->offset - $value->position->offset,
        );
    }

    private static function validateContent(Tag $tag, FragmentNode $content): void
    {
        foreach ($content->children as $child) {
            if (!$child instanceof TextNode || trim($child->content) !== '') {
                return;
            }
        }

        throw new CompileException("Tag {{$tag->name}} must not be empty.", $tag->position);
    }
}
