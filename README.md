# TransMate

Potato, patate, potet, kartoffel. We're not that different, mate!

TransMate translates Craft CMS content between sites using **DeepL** and/or
**OpenAI**. It can translate whole elements (entries, assets, Matrix blocks, and
nested content) or individual fields and strings — on demand from the control panel,
automatically on save, from the console, or programmatically.

## Requirements

- Craft CMS 5.9.0 or later
- PHP 8.2 or later
- An API key for [DeepL](https://www.deepl.com/pro-api) and/or
  [OpenAI](https://platform.openai.com/api-keys)

## Installation

```bash
composer require vaersaagod/transmate
php craft plugin/install transmate
```

## Configuration

TransMate has **no control-panel settings screen** — it's configured through a
`config/transmate.php` file, which behaves like any other
[Craft config file](https://craftcms.com/docs/5.x/configure.html#config-files).

A minimal setup — one translator, one API key:

```php
<?php

use craft\helpers\App;

return [
    'translator' => 'deepl',
    'translatorConfig' => [
        'deepl' => [
            'apiKey' => App::env('DEEPL_API_KEY'),
        ],
    ],
];
```

### Settings

| Setting | Type | Default | Description |
|---|---|---|---|
| `translator` | `string` \| `array` | `''` | Which translator backend to use, globally or per site (see [Translators](#translators)). |
| `translatorConfig` | `array` | see below | Per-translator API keys and options, keyed by translator handle. |
| `saveMode` | `string` | `current` | How translated elements are saved: `current` (save in place), `draft`, or `provisional` (a provisional draft). Drafts are only used for element types that support them. |
| `resetSlugMode` | `string` | `always` | Whether the slug is regenerated from the translated title: `always` or `never`. |
| `excludedFields` | `array` | `[]` | Field handles to skip when translating elements. |
| `disableTranslationProperty` | `?string` | `null` | Name of a property/field on the target element that, when truthy, skips translation for that element. |
| `translationGroups` | `?array` | `null` | Arrays of site handles that may be translated to/from each other. `null` = every site is in one group. |
| `autoTranslate` | `array` | `[]` | Rules for translating elements automatically on save (see [Auto-translate](#auto-translate)). Non-empty also enables the save listener. |
| `autoTranslateDrafts` | `bool` | `false` | Whether auto-translate also fires for drafts. |

`translatorConfig` defaults to:

```php
'translatorConfig' => [
    'deepl'  => ['apiKey' => '', 'options' => [], 'glossaries' => []],
    'openai' => ['apiKey' => '', 'engine' => 'gpt-4', 'temperature' => 0.7],
],
```

- **DeepL** — `options` is passed straight through to the DeepL API, so you can set
  things like formality, a `context`, or the model (e.g.
  `'options' => ['model' => 'prefer_quality_optimized']`). `glossaries` maps
  `sourceLang → targetLang → glossaryId` (create glossaries with the console command
  below).
- **OpenAI** — `engine` is the chat model and `temperature` controls determinism.

### Permissions

Translating from the control panel requires the **Can translate content**
(`transmateCanTranslate`) user permission, under the *TransMate* heading. TransMate
additionally enforces that the user can edit the target site (and, for entries, view
the source section), and that the two sites are in the same translation group.

## Translators

TransMate ships two translators:

| Handle | Backend | Notes |
|---|---|---|
| `deepl` | DeepL API | Native batch translation (one request for many strings); language-code normalisation; glossary and `context` support. |
| `openai` | OpenAI Chat API | Uses the configured `engine`/`temperature`; batches by looping. |

### Choosing a translator

The `translator` setting accepts either a single handle or a per-site map.

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

This is useful when your default translator doesn't support a language on one of your
sites — for example, DeepL doesn't support Somali, so that site falls back to OpenAI.

#### How a translator is chosen

A translation always involves two sites: the source and the target. An explicit,
site-specific entry always beats the `'*'` fallback — in **either** direction. So with
the config above, translating between a DeepL site and the Somali site uses OpenAI
whether you're going Norwegian → Somali or Somali → Norwegian.

#### Translation pairs

If both sites in a translation resolve to *different* explicit translators, the plugin
can't decide which to use and throws a configuration error. Resolve this with a pair
override — two site handles joined by a colon. Pair keys match **bidirectionally**, so
the order doesn't matter:

```php
'translator' => [
    '*'              => 'deepl',
    'somali'         => 'openai',
    'french'         => 'openai',
    'somali:french'  => 'openai',  // used for both Somali → French and French → Somali
],
```

## Translating from the control panel

Once a user has the *Can translate content* permission, TransMate adds:

- **A `Translate` button** on element edit pages, with *Translate from {site}* items
  (pull content in from another site) and a *Translate to site…* item (push this
  element out to other sites).
- **A `Translate to…` element-index action** for translating a selection of elements
  to one or more sites in bulk (queued). You can choose to save the results as drafts.
- **A per-field `Translate from site…` action** in a field's action menu, to translate
  a single field in place (as a provisional draft) without touching the rest of the
  element.

## Auto-translate

Translate elements automatically whenever they're saved in a source site. Each rule is
an entry in `autoTranslate`:

```php
'autoTranslate' => [
    [
        'elementType' => \craft\elements\Entry::class, // default
        'fromSite'    => 'default',                    // source site handle or id
        'toSite'      => ['french', 'somali'],         // one handle/id or an array
        'criteria'    => ['section' => 'news'],        // optional element-query criteria
    ],
],
'autoTranslateDrafts' => false,
```

When an element saved in `fromSite` matches the rule (type, site, and optional
`criteria`), a translation job is queued for each `toSite`. The asset indexer,
revisions and (unless `autoTranslateDrafts` is on) drafts are skipped.

## What gets translated

For elements, TransMate translates the native **title** and (for assets) **alt text**,
plus the element's custom fields — skipping anything listed in `excludedFields`. Field
support:

| Field type | Handling |
|---|---|
| Plain Text | Translated directly. |
| Table | Single-line / multi-line columns only. |
| Link / [LinkMate](https://github.com/vaersaagod/linkmate) | Label, ARIA label and title sub-values. |
| CKEditor | Markup preserved; nested entries translated recursively; references retargeted to the target site. |
| Redactor | Content translated; references retargeted to the target site. |
| Matrix | Each block translated recursively. |

Other field types are left untouched.

## Programmatic API

Everything runs through the `translate` service component
(`\vaersaagod\transmate\TransMate::getInstance()->translate`).

```php
use vaersaagod\transmate\TransMate;

$translate = TransMate::getInstance()->translate;
$sites = Craft::$app->getSites();
$from = $sites->getSiteByHandle('default');
$to   = $sites->getSiteByHandle('french');

// Translate a whole element (returns the target element, saved by default)
$translate->translateElement($entry, $from, $to);

// Translate a single string (returns ?string)
$translate->translateText('Hello, world', $from, $to);          // sites or handles
$translate->translateText('Hello, world', 'default', 'french');

// Translate many strings sharing one language pair, in as few requests as possible
// (DeepL does it in a single API call). Input keys are preserved.
$translate->translateTexts([
    'title'   => 'Hello',
    'summary' => 'A short summary',
], $from, $to);
// => ['title' => 'Bonjour', 'summary' => 'Un bref résumé']
```

`translateText()` / `translateTexts()` return the input untranslated when the source
and target languages are the same, and skip empty values. `translateElement()` accepts
optional `$language`, `$saveMode`, `$saveElement`, `$owner` and `$attributes` (limit to
specific field handles) arguments.

## Console commands

```bash
# Translate a single element, or every entry in a section, to one or more sites
php craft transmate/translate --fromSite=default --toSite=french,somali \
    { --elementId=<id> | --section=<handle> } [--language=<id>] [--asDraft=1]

# Manage DeepL glossaries
php craft transmate/deepl-glossary/create --name=… --sourceLanguage=en --targetLanguage=fr --entries="foo\tbar"
php craft transmate/deepl-glossary/list
php craft transmate/deepl-glossary/delete --glossaryId=…
```

## Price, license and support

The plugin is released under the Craft license and could be subject to license fees.
It's made for Værsågod and friends, and no support is given. Submitted issues are
resolved if it scratches an itch.

## Changelog

See [CHANGELOG.MD](https://raw.githubusercontent.com/vaersaagod/transmate/master/CHANGELOG.md).

## Credits

Brought to you by [Værsågod](https://www.vaersaagod.no)

Icon designed by [Freepik from Flaticon](https://www.flaticon.com/authors/freepik).
