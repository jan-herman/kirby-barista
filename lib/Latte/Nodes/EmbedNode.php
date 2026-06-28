<?php declare(strict_types=1);

namespace JanHerman\Barista\Latte\Nodes;

use Generator;
use Latte\CompileException;
use Latte\Compiler\Block;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\Range;
use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;
use Latte\Compiler\Token;
use Latte\Essential\Nodes\BlockNode;
use Latte\Essential\Nodes\EmbedNode as LatteEmbedNode;
use Latte\Essential\Nodes\ImportNode;

use function array_pop;
use function array_shift;
use function array_splice;
use function count;
use function end;
use function preg_match;
use function trim;

/**
 * {embed 'file.latte'|#block} ... {/embed}
 *
 * Extends Latte's native embed parser by treating loose direct content as a
 * configurable implicit block.
 */
class EmbedNode extends LatteEmbedNode
{
    /** @return Generator<int, ?list<string>, array{FragmentNode, ?Tag}, static> */
    public static function create(Tag $tag, TemplateParser $parser): Generator
    {
        return yield from self::createWithImplicitBlock($tag, $parser, 'default');
    }

    /** @return Generator<int, ?list<string>, array{FragmentNode, ?Tag}, static> */
    public static function createWithImplicitBlock(Tag $tag, TemplateParser $parser, mixed $implicitBlockName): Generator
    {
        if ($tag->isNAttribute()) {
            throw new CompileException('Attribute n:embed is not supported.', $tag->position);
        }

        $implicitBlockName = self::validateImplicitBlockName($implicitBlockName, $tag);

        $tag->outputMode = $tag::OutputRemoveIndentation;
        $tag->expectArguments();

        $node = $tag->node = new static();
        $mode = $tag->parser->tryConsumeTokenBeforeUnquotedString('block', 'file')?->text;
        $node->name = $tag->parser->parseUnquotedStringOrExpression();
        $node->mode = $mode ?? ($node->name instanceof StringNode && preg_match('~[\w-]+$~DA', $node->name->value) ? 'block' : 'file');
        $tag->parser->stream->tryConsume(',');
        $node->args = $tag->parser->parseArguments();

        $prevIndex = $parser->blockLayer;
        $parser->blockLayer = $node->layer = count($parser->blocks);
        $parser->blocks[$parser->blockLayer] = [];
        $implicitBlock = self::createImplicitBlock($tag, $implicitBlockName, $node->layer);
        $parser->pushTag($implicitBlock->block->tag);

        try {
            try {
                [$node->blocks] = yield;
            } finally {
                $parser->popTag();
            }

            self::wrapLooseContent($node, $parser, $tag, $implicitBlock);
        } finally {
            $parser->blockLayer = $prevIndex;
        }

        return $node;
    }

    private static function validateImplicitBlockName(mixed $name, Tag $tag): string
    {
        if (!is_string($name) || trim($name) === '') {
            throw new CompileException('Option jan-herman.barista.implicitEmbedBlockName must be a non-empty string.', $tag->position);
        }

        if (!preg_match('#^[a-z][\w-]*$#iD', $name)) {
            throw new CompileException("Option jan-herman.barista.implicitEmbedBlockName contains invalid block name '$name'.", $tag->position);
        }

        return $name;
    }

    private static function wrapLooseContent(self $node, TemplateParser $parser, Tag $embedTag, BlockNode $block): void
    {
        $blockName = $block->block->name->value;
        $children = [];
        $looseContent = [];
        $insertAt = null;

        foreach ($node->blocks->children as $child) {
            if ($child instanceof ImportNode || $child instanceof BlockNode) {
                $children[] = $child;
                continue;
            }

            $insertAt ??= count($children);
            $looseContent[] = $child;
        }

        self::trimOuterWhitespace($looseContent);

        if (!$looseContent) {
            return;
        }

        if (isset($parser->blocks[$node->layer][$blockName])) {
            throw new CompileException(
                "Cannot combine loose content with an explicit {block $blockName} inside {embed}; both define the $blockName block.",
                $looseContent[0]->position ?? $embedTag->position,
            );
        }

        self::finalizeImplicitBlock($block, $looseContent, $embedTag, $blockName, $node->layer);
        $parser->checkBlockIsUnique($block->block);

        array_splice($children, $insertAt ?? 0, 0, [$block]);
        $node->blocks->children = $children;
    }

    /** @param AreaNode[] $content */
    private static function trimOuterWhitespace(array &$content): void
    {
        while ($content && $content[0] instanceof TextNode && $content[0]->isWhitespace()) {
            array_shift($content);
        }

        while ($content && ($last = end($content)) instanceof TextNode && $last->isWhitespace()) {
            array_pop($content);
        }
    }

    private static function createImplicitBlock(Tag $embedTag, string $blockName, int|string $layer): BlockNode
    {
        $blockNode = new BlockNode();
        $blockNode->position = $embedTag->position;
        $blockNode->tagRanges = [$embedTag->position];
        $blockNode->modifier = new ModifierNode([]);
        $blockNode->content = new FragmentNode([]);
        $blockNode->block = new Block(new StringNode($blockName, $embedTag->position), $layer, self::createImplicitBlockTag($embedTag->position, $embedTag));
        $blockNode->block->tag->node = $blockNode;

        return $blockNode;
    }

    /** @param AreaNode[] $content */
    private static function finalizeImplicitBlock(BlockNode $blockNode, array $content, Tag $embedTag, string $blockName, int|string $layer): void
    {
        $position = $content[0]->position ?? $embedTag->position;
        $tagPosition = $position instanceof Range ? $position : $embedTag->position;
        $blockTag = self::createImplicitBlockTag($tagPosition, $embedTag);
        $blockTag->node = $blockNode;

        $blockNode->position = $position;
        $blockNode->tagRanges = [$tagPosition];
        $blockNode->content = new FragmentNode($content);
        $blockNode->block = new Block(new StringNode($blockName, $tagPosition), $layer, $blockTag);
    }

    private static function createImplicitBlockTag(Range $position, Tag $embedTag): Tag
    {
        return new Tag(
            name: 'block',
            tokens: [new Token(Token::End, '', $position)],
            position: $position,
            parent: $embedTag,
        );
    }
}
