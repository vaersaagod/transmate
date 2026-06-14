# TransMate

Potato, patate, potet, kartoffel. We're not that different, mate!

## Requirements

This plugin requires Craft CMS 5.0.0 or later, and PHP 8.2 or later.

---

## Price, license and support

The plugin is released under the Craft license and could be subject to license fees. 
It's made for Værsågod and friends, and no support is given. Submitted issues are resolved if it scratches an itch.

## Configuration

### `translator`

Which translator backend to use. Accepts either a single translator handle or a
per-site map.

#### Single translator

Use one translator for every site:

```php
'translator' => 'deepl',
```

#### Per-site translators

Map site handles to translator handles. The `'*'` key is **required** as the
fallback for any site you don't list explicitly:

```php
'translator' => [
    '*'      => 'deepl',   // every site uses DeepL…
    'somali' => 'openai',  // …except the Somali site, which uses OpenAI
],
```

This is useful when your default translator doesn't support a language on one of
your sites — for example, DeepL doesn't support Somali, so that site falls back
to OpenAI.

#### How a translator is chosen

A translation always involves two sites: the source and the target. An explicit,
site-specific entry always beats the `'*'` fallback — in **either** direction. So
with the config above, translating between a DeepL site and the Somali site uses
OpenAI whether you're going Norwegian → Somali or Somali → Norwegian.

#### Translation pairs

If both sites in a translation resolve to *different* explicit translators, the
plugin can't decide which to use and throws a configuration error. Resolve this
with a pair override — two site handles joined by a colon. Pair keys match
**bidirectionally**, so the order doesn't matter:

```php
'translator' => [
    '*'                => 'deepl',
    'somali'           => 'openai',
    'icelandic'        => 'google',
    'somali:icelandic' => 'openai',  // used for both Somali → Icelandic and Icelandic → Somali
],
```

## Changelog

See [CHANGELOG.MD](https://raw.githubusercontent.com/vaersaagod/transmate/master/CHANGELOG.md).

## Credits

Brought to you by [Værsågod](https://www.vaersaagod.no)

Icon designed by [Freepik from Flaticon](https://www.flaticon.com/authors/freepik).
