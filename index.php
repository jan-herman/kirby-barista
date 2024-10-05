<?php

use JanHerman\Barista\Barista;
use JanHerman\Barista\Template;
use JanHerman\Barista\Snippet;

use Kirby\Cms\App as Kirby;

use Kirby\Sane\Html as SaneHtml;
use Latte\Runtime\Html;

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('jan-herman/barista', [
    'options' => [
        'autoRefresh' => true,
        'tempDirectory' => null,
        'pathAliases' => null,
        'filters' => [],
        'functions' => [],
        'tags' => [],
    ],
    'components' => [
        'template' => function (Kirby $kirby, string $name, string $content_type = null) {
            return new Template($name, $content_type);
        },
        'snippet' => function (Kirby $kirby, string $name, array $data = [], bool $slots = false): Snippet|string {
            return Snippet::factory($name, $data, $slots);
        }
    ],
    'routes' => [
        [
            // Block all requests to /url.latte and return 404
            'pattern' => '(:all)\.latte',
            'action' => function ($all) {
                return false;
            }
        ]
    ],
    'fieldMethods' => [
        'toHtml' => function ($field): string|Html {
            if ($field->isEmpty()) {
                return '';
            }

            $safe_html = SaneHtml::sanitize($field->value);
            $safe_html = str_replace('&amp;nbsp;', '&nbsp;', $safe_html);
            return new Html($safe_html);
        }
    ]
]);

function barista()
{
    $kirby = kirby();
    return Barista::getInstance($kirby);
}
