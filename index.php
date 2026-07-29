<?php

use JanHerman\Barista\Barista;
use JanHerman\Barista\Template;
use JanHerman\Barista\Snippet;
use Kirby\Cms\App as Kirby;
use Kirby\CLI\CLI;
use Latte\Runtime\Html;
use Kirby\Sane\Html as SaneHtml;

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('jan-herman/barista', [
    'options' => [
        'autoRefresh' => true,
        'strictTypes' => false,
        'dedent' => true,
        'scopedLoopVariables' => true,
        'cacheDirectory' => null,
        'extensions' => [
            'translator' => true,
            'coreFilters' => true,
            'sfc' => false,
            'templateDependencies' => false,
            'tracy' => true,
            'rawPhp' => false,
        ],
        'pathAliases' => null,
        'filters' => [],
        'functions' => [],
        'tags' => [],
        'translator' => [
            'escapeHtml' => true
        ],
    ],
    'components' => [
        'template' => function (Kirby $kirby, string $name, ?string $contentType = null) {
            return new Template($name, $contentType);
        },
        'snippet' => function (Kirby $kirby, string $name, array $data = [], bool $slots = false): Snippet|string {
            return Snippet::factory($name, $data, $slots);
        }
    ],
    'commands' => [
        'barista:flush-cache' => [
            'description' => 'Flushes the Barista Latte template cache',
            'command' => static function (CLI $cli): void {
                barista()->flushCache();

                $cli->success('The Barista cache has been flushed');
            }
        ]
    ],
    'routes' => [
        [
            // Block direct requests to Latte files, except Vite SFC requests.
            'pattern' => '(:all)\.latte',
            'action' => function ($all) {
                if (
                    kirby()->environment()->isLocal() &&
                    kirby()->request()->query()->get('sfc') !== null
                ) {
                    $this->next();
                }

                return false;
            }
        ]
    ],
    'fieldMethods' => [
        'toHtml' => function ($field): string|Html {
            if ($field->isEmpty()) {
                return '';
            }

            return safe_html($field->value);
        }
    ]
]);

function barista()
{
    $kirby = kirby();
    return Barista::getInstance($kirby);
}

function safe_html($html): Html
{
    $safeHtml = SaneHtml::sanitize($html);
    $safeHtml = str_replace('&amp;nbsp;', '&nbsp;', $safeHtml);
    return new Html($safeHtml);
}
