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
    public static function createWithImplicitBlock(Tag $tag, TemplateParser $parser, mixed $implicit_block_name): Generator
    {
        if ($tag->isNAttribute()) {
            throw new CompileException('Attribute n:embed is not supported.', $tag->position);
        }

        $implicit_block_name = self::validateImplicitBlockName($implicit_block_name, $tag);

        $tag->outputMode = $tag::OutputRemoveIndentation;
        $tag->expectArguments();

        $node = $tag->node = new static();
        $mode = $tag->parser->tryConsumeTokenBeforeUnquotedString('block', 'file')?->text;
        $node->name = $tag->parser->parseUnquotedStringOrExpression();
        $node->mode = $mode ?? ($node->name instanceof StringNode && preg_match('~[\w-]+$~DA', $node->name->value) ? 'block' : 'file');
        $tag->parser->stream->tryConsume(',');
        $node->args = $tag->parser->parseArguments();

        $prev_index = $parser->blockLayer;
        $parser->blockLayer = $node->layer = count($parser->blocks);
        $parser->blocks[$parser->blockLayer] = [];
        $implicit_block = self::createImplicitBlock($tag, $implicit_block_name, $node->layer);
        $parser->pushTag($implicit_block->block->tag);

        try {
            try {
                [$node->blocks] = yield;
            } finally {
                $parser->popTag();
            }

            self::wrapLooseContent($node, $parser, $tag, $implicit_block);
        } finally {
            $parser->blockLayer = $prev_index;
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

    private static function wrapLooseContent(self $node, TemplateParser $parser, Tag $embed_tag, BlockNode $block): void
    {
        $block_name = $block->block->name->value;
        $children = [];
        $loose_content = [];
        $insert_at = null;

        foreach ($node->blocks->children as $child) {
            if ($child instanceof ImportNode || $child instanceof BlockNode) {
                $children[] = $child;
                continue;
            }

            $insert_at ??= count($children);
            $loose_content[] = $child;
        }

        self::trimOuterWhitespace($loose_content);

        if (!$loose_content) {
            return;
        }

        if (isset($parser->blocks[$node->layer][$block_name])) {
            throw new CompileException(
                "Cannot combine loose content with an explicit {block $block_name} inside {embed}; both define the $block_name block.",
                $loose_content[0]->position ?? $embed_tag->position,
            );
        }

        self::finalizeImplicitBlock($block, $loose_content, $embed_tag, $block_name, $node->layer);
        $parser->checkBlockIsUnique($block->block);

        array_splice($children, $insert_at ?? 0, 0, [$block]);
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

    private static function createImplicitBlock(Tag $embed_tag, string $block_name, int|string $layer): BlockNode
    {
        $block_node = new BlockNode();
        $block_node->position = $embed_tag->position;
        $block_node->tagRanges = [$embed_tag->position];
        $block_node->modifier = new ModifierNode([]);
        $block_node->content = new FragmentNode([]);
        $block_node->block = new Block(new StringNode($block_name, $embed_tag->position), $layer, self::createImplicitBlockTag($embed_tag->position, $embed_tag));
        $block_node->block->tag->node = $block_node;

        return $block_node;
    }

    /** @param AreaNode[] $content */
    private static function finalizeImplicitBlock(BlockNode $block_node, array $content, Tag $embed_tag, string $block_name, int|string $layer): void
    {
        $position = $content[0]->position ?? $embed_tag->position;
        $tag_position = $position instanceof Range ? $position : $embed_tag->position;
        $block_tag = self::createImplicitBlockTag($tag_position, $embed_tag);
        $block_tag->node = $block_node;

        $block_node->position = $position;
        $block_node->tagRanges = [$tag_position];
        $block_node->content = new FragmentNode($content);
        $block_node->block = new Block(new StringNode($block_name, $tag_position), $layer, $block_tag);
    }

    private static function createImplicitBlockTag(Range $position, Tag $embed_tag): Tag
    {
        return new Tag(
            name: 'block',
            tokens: [new Token(Token::End, '', $position)],
            position: $position,
            parent: $embed_tag,
        );
    }
}
