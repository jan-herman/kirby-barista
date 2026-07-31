<?php

namespace JanHerman\Barista\Latte\Nodes;

use Generator;
use Latte\CompileException;
use Latte\Compiler\Block;
use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Php\ArrayItemNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\IdentifierNode;
use Latte\Compiler\Nodes\Php\Scalar\BooleanNode;
use Latte\Compiler\Nodes\Php\Scalar\NullNode;
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

    /**
     * Parses and validates an SFC tag while preserving its metadata.
     *
     * @return Generator<int, ?list<string>, array{mixed, ?Tag}, static>
     */
    public static function create(Tag $tag, TemplateParser $parser): Generator
    {
        if ($tag->isNAttribute()) {
            throw new CompileException("Attribute {$tag->getNotation()} is not supported.", $tag->position);
        }

        if ($tag->void) {
            throw new CompileException("Tag {{$tag->name}/} must be paired with {{$tag->name}}.", $tag->position);
        }

        $properties = $tag->parser->parseArguments();
        static::validateProperties($tag, $properties);

        $tag->outputMode = $tag::OutputRemoveIndentation;
        $node = $tag->node = new static;
        $node->tag = $tag;
        $node->lazy = static::isLazy($tag, $properties);

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
            $this->lazy ? $this::LazyBlockName : $this::EagerBlockName,
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

    /**
     * Exposes no child nodes because SFC content is removed during parsing.
     */
    public function &getIterator(): Generator
    {
        false && yield;
    }

    /**
     * Rejects duplicate properties and dispatches each one to its validator.
     */
    private static function validateProperties(Tag $tag, ArrayNode $properties): void
    {
        $declaredProperties = [];

        foreach ($properties->items as $property) {
            $propertyIsFlag = static::propertyIsFlag($tag, $property);

            if ($property->key instanceof IdentifierNode) {
                $propertyName = $property->key->name;
            } elseif ($propertyIsFlag) {
                $propertyName = $property->value->value;
            } else {
                continue;
            }

            if ($propertyIsFlag && $propertyName !== 'lazy') {
                throw new CompileException(
                    "The $propertyName property in {{$tag->name}} must have a value.",
                    $property->position,
                );
            }

            if (!in_array($propertyName, ['lazy', 'layer', 'lang'], true)) {
                continue;
            }

            if (isset($declaredProperties[$propertyName])) {
                throw new CompileException(
                    "The $propertyName property in {{$tag->name}} must not be declared more than once.",
                    $property->position,
                );
            }

            $declaredProperties[$propertyName] = true;

            if ($propertyIsFlag) {
                continue;
            }

            match ($propertyName) {
                'lazy' => static::validateLazyProperty($tag, $property),
                'layer' => static::validateLayerProperty($tag, $property),
                'lang' => static::validateLangProperty($tag, $property),
            };
        }
    }

    /**
     * Validates one lazy property.
     */
    private static function validateLazyProperty(
        Tag $tag,
        ArrayItemNode $property,
    ): void {
        if (!$property->value instanceof BooleanNode) {
            throw new CompileException(
                "The lazy property in {{$tag->name}} must be a bare flag or a static boolean.",
                $property->value->position,
            );
        }
    }

    /**
     * Validates one style layer property.
     */
    private static function validateLayerProperty(
        Tag $tag,
        ArrayItemNode $property,
    ): void {
        if ($tag->name !== 'style') {
            throw new CompileException(
                "The layer property is only supported in {style}.",
                $property->position,
            );
        }

        if (
            !$property->value instanceof StringNode
            && !$property->value instanceof NullNode
        ) {
            throw new CompileException(
                "The layer property in {{$tag->name}} must be a static string or null.",
                $property->value->position,
            );
        }
    }

    /**
     * Validates one language property against the node's supported languages.
     */
    private static function validateLangProperty(Tag $tag, ArrayItemNode $property): void
    {
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

    /**
     * Returns the validated lazy state, defaulting to eager loading.
     */
    private static function isLazy(Tag $tag, ArrayNode $properties): bool
    {
        foreach ($properties->items as $property) {
            if (
                static::propertyIsFlag($tag, $property)
                && $property->value->value === 'lazy'
            ) {
                return true;
            }

            if (
                $property->key instanceof IdentifierNode
                && $property->key->name === 'lazy'
            ) {
                return $property->value instanceof BooleanNode
                    && $property->value->value;
            }
        }

        return false;
    }

    /**
     * Checks whether a property uses unquoted flag syntax.
     */
    private static function propertyIsFlag(Tag $tag, ArrayItemNode $property): bool
    {
        if (
            $property->key !== null
            || !$property->value instanceof StringNode
        ) {
            return false;
        }

        return static::propertySource($tag, $property->value)
            === $property->value->value;
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

    /**
     * Ensures the SFC block contains meaningful source content.
     */
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
