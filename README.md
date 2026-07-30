# Kirby Barista

## SFC loading metadata

`{style}` and `{script}` blocks are eager by default. Use either supported
lazy form to mark a block for lazy loading:

```latte
{style lazy}
.component {}
{/style}

{script lazy: true}
export default () => ({});
{/script}
```

`lazy: false` explicitly selects eager loading. The value must be a static
boolean; quoted, dynamic, missing, and duplicate values are compilation
errors.

When the `templateDependencies` extension is enabled, collected files can be
filtered by loading mode:

```php
$dependencies->filesWithStyle();
$dependencies->filesWithStyle('eager');
$dependencies->filesWithStyle('lazy');

$dependencies->filesWithScript();
$dependencies->filesWithScript('eager');
$dependencies->filesWithScript('lazy');
```

Omitting the argument preserves the existing behavior and returns files with
either mode. A template containing both modes appears in both filtered
results. Unsupported loading modes throw `InvalidArgumentException`.

Compiled templates expose the generic `BlockName` marker together with the
matching `EagerBlockName` and `LazyBlockName` constants on `StyleNode` and
`ScriptNode`.
