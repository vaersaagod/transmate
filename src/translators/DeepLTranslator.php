<?php

namespace vaersaagod\transmate\translators;

use craft\helpers\StringHelper;

use vaersaagod\transmate\models\DeepLSettings;

class DeepLTranslator extends BaseTranslator
{
    // DeepL is particular about this, country codes need to be very specific, and different
    // values are allowed depending on source and target.
    // TODO : Need to do a more thorough deep dive here...

    /** @var string */
    public const HANDLE = 'deepl';
    
    private static array $sourceCountryCodeLUM = [
        'en-US' => 'en',
        'en-GB' => 'en'
    ];
    private static array $targetCountryCodeLUM = [
        'en' => 'en-US'
    ];
    
    public function __construct(?array $settings=null)
    {
        $this->config = new DeepLSettings($settings);
    }

    public function translate(string $content, array $params = []): mixed
    {
        [$sourceLang, $targetLang, $options] = $this->prepare(StringHelper::isHtml($content));

        $translator = new \DeepL\Translator($this->config->apiKey);
        $result = $translator->translateText($content, $sourceLang, $targetLang, $options);

        return $result->text;
    }

    /**
     * Translates all strings in a single DeepL request (the SDK accepts an array of texts, up to
     * 50, sharing one source/target language pair). Input keys are preserved on the result.
     *
     * @param array $contents
     * @param array $params
     * @return array
     */
    public function translateMany(array $contents, array $params = []): array
    {
        if (empty($contents)) {
            return [];
        }

        $keys = array_keys($contents);
        $values = array_values($contents);

        $containsHtml = false;
        foreach ($values as $value) {
            if (StringHelper::isHtml($value)) {
                $containsHtml = true;
                break;
            }
        }

        [$sourceLang, $targetLang, $options] = $this->prepare($containsHtml);

        $translator = new \DeepL\Translator($this->config->apiKey);
        $results = $translator->translateText($values, $sourceLang, $targetLang, $options);

        $out = [];
        foreach ($keys as $i => $key) {
            $out[$key] = $results[$i]->text ?? null;
        }

        return $out;
    }

    /**
     * Resolves the DeepL source/target language codes and request options.
     *
     * @param bool $containsHtml Whether any of the content being translated is HTML
     * @return array{0: string, 1: string, 2: array} [$sourceLang, $targetLang, $options]
     */
    private function prepare(bool $containsHtml): array
    {
        $options = $this->config->options;

        if (isset($options['context'])) {
            $options['context'] = \Craft::$app->getView()->renderString($options['context']);
        }

        if (!isset($options['tag_handling']) && $containsHtml) {
            $options['tag_handling'] = 'html';
        }

        $sourceLang = self::$sourceCountryCodeLUM[$this->fromLanguage] ?? $this->fromLanguage;
        $targetLang = self::$targetCountryCodeLUM[$this->toLanguage] ?? $this->toLanguage;

        if (!empty($this->config->glossaries)) {
            $glossaries = $this->config->glossaries;
            if (isset($glossaries[$sourceLang][$targetLang])) {
                $options['glossary'] = $glossaries[$sourceLang][$targetLang];
            }
        }

        return [$sourceLang, $targetLang, $options];
    }
}
