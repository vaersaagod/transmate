<?php

namespace vaersaagod\transmate\models;

use craft\base\Model;

use vaersaagod\transmate\translators\TranslatorResolver;

class Settings extends Model
{
    public string $saveMode = 'current'; // draft, current (?)
    public string $resetSlugMode = 'always'; // never, always, new (?)
    public array $excludedFields = [];
    public ?string $disableTranslationProperty = null;
    public ?string $creatorId = null;
    public array $translatorConfig = [
        'deepl' => [
            'apiKey' => '',
            'options' => [],
            'glossaries' => []
        ],
        'openai' => [
            'apiKey' => '',
            'engine' => 'gpt-4',
            'temperature' => 0.7,
        ],
    ];
    public array $autoTranslate = [];
    public bool $autoTranslateDrafts = false;
    public ?array $translationGroups = null;

    /**
     * Which translator backend(s) to use, by site.
     *
     * Accepts either a single translator handle for everything, or a map of
     * site handles to translator handles. A site-specific entry always wins
     * over the '*' fallback, in either translation direction — so if any site
     * involved in a translation needs a particular translator, that translator
     * is used.
     *
     * Three forms are supported:
     *
     * 1. A single translator handle, used for all sites:
     *
     *        'translator' => 'deepl',
     *
     * 2. A per-site map. The '*' key is required as the fallback for any site
     *    not listed explicitly:
     *
     *        'translator' => [
     *            '*'      => 'deepl',   // everything uses DeepL…
     *            'somali' => 'openai',  // …except the Somali site, which uses OpenAI
     *        ],
     *
     *    Resolution for a source → target pair: a site's explicit entry beats
     *    the '*' fallback. So translating between a DeepL site and the Somali
     *    site resolves to OpenAI, regardless of direction.
     *
     * 3. A per-site map plus pair overrides, for when both sites in a
     *    translation have conflicting explicit translators. A pair key is two
     *    site handles joined by a colon ('sourceSite:targetSite') and matches
     *    bidirectionally — order does not matter:
     *
     *        'translator' => [
     *            '*'                => 'deepl',
     *            'somali'           => 'openai',
     *            'icelandic'        => 'google',
     *            'somali:icelandic' => 'openai',  // matches both directions
     *        ],
     *
     * If both sites resolve to different explicit translators and no pair
     * override covers them, resolution throws an InvalidConfigException.
     *
     * The map form must always include a '*' key.
     *
     * @var string|array<string, string> A translator handle, or a map of
     *     site handles (and optional 'source:target' pair keys) to translator
     *     handles. Maps must include a '*' fallback.
     */
    public string|array $translator = '';

    /** @var TranslatorResolver|null */
    private ?TranslatorResolver $translatorResolver = null;

    /**
     * @param $values
     * @param $safeOnly
     * @return void
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        if (!empty($values['autoTranslate'])) {
            $r = [];
            
            foreach ($values['autoTranslate'] as $autoConfig) {
                $r[] = new AutoTranslateSettings($autoConfig);
            }
            
            $values['autoTranslate'] = $r;
        }
        
        parent::setAttributes($values, $safeOnly);
    }

    /**
     * @return TranslatorResolver
     */
    public function getTranslatorResolver(): TranslatorResolver
    {
        return $this->translatorResolver ??= new TranslatorResolver($this->translator);
    }

}
