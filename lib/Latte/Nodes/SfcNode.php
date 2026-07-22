<?php

namespace JanHerman\Barista\Latte\Nodes;

use Generator;
use Latte\CompileException;
use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Php\IdentifierNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateLexer;
use Latte\Compiler\TemplateParser;

abstract class SfcNode extends StatementNode
{
    protected const Languages = [];

    /** @return Generator<int, ?list<string>, array{mixed, ?Tag}, static> */
    public static function create(Tag $tag, TemplateParser $parser): Generator
    {
        if ($tag->isNAttribute()) {
            throw new CompileException("Attribute {$tag->getNotation()} is not supported.", $tag->position);
        }

        if ($tag->void) {
            throw new CompileException("Tag {{$tag->name}/} must be paired with {{$tag->name}}.", $tag->position);
        }

        static::validateProperties($tag);
        $tag->outputMode = $tag::OutputRemoveIndentation;
        $node = $tag->node = new static;

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

    public function print(PrintContext $context): string
    {
        return '';
    }

    public function &getIterator(): Generator
    {
        false && yield;
    }

    private static function validateProperties(Tag $tag): void
    {
        $properties = $tag->parser->parseArguments();

        foreach ($properties->items as $property) {
            if (!$property->key instanceof IdentifierNode || $property->key->name !== 'lang') {
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
