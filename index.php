<?php

use JanHerman\Barista\Barista;
use JanHerman\Barista\Template;

use Kirby\Cms\App as Kirby;
use Kirby\Template\Snippet;

use Kirby\Toolkit\Str;
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
            $file = Snippet::file($name);

            if (Str::endsWith($file, '.latte')) {
                return barista()->renderToString($file, $data);
            }

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
        'toHtml' => function ($field): string|Html
        {
            if ($field->isEmpty()) {
                return '';
            }

            $safe_html = SaneHtml::sanitize($field->value);
            return new Html($safe_html);
        }
    ]
]);

function barista() {
    $kirby = kirby();
    return Barista::getInstance($kirby);
}
