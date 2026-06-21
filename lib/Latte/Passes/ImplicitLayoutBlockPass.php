<?php declare(strict_types=1);

namespace JanHerman\Barista\Latte\Passes;

use Latte\CompileException;
use Latte\Compiler\Block;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\Php\Scalar\BooleanNode;
use Latte\Compiler\Nodes\Php\Scalar\StringNode;
use Latte\Compiler\Nodes\TemplateNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\Position;
use Latte\Compiler\Range;
use Latte\Compiler\Tag;
use Latte\Compiler\Token;
use Latte\Essential\Nodes\BlockNode;
use Latte\Essential\Nodes\DefineNode;
use Latte\Essential\Nodes\ExtendsNode;
use Latte\Essential\Nodes\ImportNode;
use Latte\Runtime\Template;

use function array_pop;
use function array_shift;
use function array_splice;
use function count;
use function end;
use function preg_match;
use function trim;

/**
 * Treats loose top-level content in templates using {layout}/{extends} as one
 * configurable block.
 */
class ImplicitLayoutBlockPass
{
    public function __construct(
        private readonly mixed $implicit_block_name,
    ) {
    }

    public function __invoke(TemplateNode $template): void
    {
        $layout_node = self::findLayoutNode($template);

        if (!$layout_node || self::isDisabledLayout($layout_node)) {
            return;
        }

        $block_name = self::validateImplicitBlockName($this->implicit_block_name, $layout_node);
        self::wrapLooseContent($template, $layout_node, $block_name);
    }

    private static function findLayoutNode(TemplateNode $template): ?ExtendsNode
    {
        foreach ($template->head->children as $child) {
            if ($child instanceof ExtendsNode) {
                return $child;
            }
        }

        return null;
    }

    private static function isDisabledLayout(ExtendsNode $layout_node): bool
    {
        return $layout_node->extends instanceof BooleanNode && $layout_node->extends->value === false;
    }

    private static function validateImplicitBlockName(mixed $name, ExtendsNode $layout_node): string
    {
        if (!is_string($name) || trim($name) === '') {
            throw new CompileException(
                'Option jan-herman.barista.implicitLayoutBlockName must be a non-empty string.',
                $layout_node->position,
            );
        }

        if (!preg_match('#^[a-z][\w-]*$#iD', $name)) {
            throw new CompileException(
                "Option jan-herman.barista.implicitLayoutBlockName contains invalid block name '$name'.",
                $layout_node->position,
            );
        }

        return $name;
    }

    private static function wrapLooseContent(TemplateNode $template, ExtendsNode $layout_node, string $block_name): void
    {
        $children = [];
        $loose_content = [];
        $insert_at = null;

        foreach ($template->main->children as $child) {
            if (self::isDeclarationNode($child)) {
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

        if ($conflicting_node = self::findConflictingBlock($template->main, $block_name)) {
            throw new CompileException(
                "Cannot combine loose content with an explicit {block $block_name} inside a template using {layout}/{extends}; both define the $block_name block.",
                $conflicting_node->position ?? $loose_content[0]->position ?? $layout_node->position,
            );
        }

        $block = self::createImplicitBlock($loose_content, $layout_node, $block_name);
        array_splice($children, $insert_at ?? 0, 0, [$block]);
        $template->main->children = $children;
    }

    private static function isDeclarationNode(AreaNode $node): bool
    {
        return $node instanceof ImportNode
            || $node instanceof DefineNode
            || ($node instanceof BlockNode && $node->block !== null);
    }

    private static function findConflictingBlock(Node $node, string $block_name): BlockNode|DefineNode|null
    {
        if (($node instanceof BlockNode || $node instanceof DefineNode)
            && $node->block
            && $node->block->name instanceof StringNode
            && $node->block->name->value === $block_name
        ) {
            return $node;
        }

        foreach ($node as $child) {
            if ($child instanceof Node && ($conflicting_node = self::findConflictingBlock($child, $block_name))) {
                return $conflicting_node;
            }
        }

        return null;
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

    /** @param AreaNode[] $content */
    private static function createImplicitBlock(array $content, ExtendsNode $layout_node, string $block_name): BlockNode
    {
        $position = $content[0]->position ?? $layout_node->position;
        $tag_position = self::rangeFromPosition($position);
        $block_tag = new Tag(
            name: 'block',
            tokens: [new Token(Token::End, '', $tag_position)],
            position: $tag_position,
        );

        $block_node = new BlockNode();
        $block_node->position = $position;
        $block_node->tagRanges = [$tag_position];
        $block_node->modifier = new ModifierNode([]);
        $block_node->content = new FragmentNode($content);
        $block_node->block = new Block(new StringNode($block_name, $tag_position), Template::LayerTop, $block_tag);

        return $block_node;
    }

    private static function rangeFromPosition(?Position $position): Range
    {
        if ($position instanceof Range) {
            return $position;
        }

        return new Range(
            $position->line ?? 1,
            $position->column ?? 1,
            $position->offset ?? 0,
            0,
        );
    }
}
