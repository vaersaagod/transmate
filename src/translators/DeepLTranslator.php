<?php

namespace vaersaagod\transmate\translators;

use craft\helpers\StringHelper;
use craft\web\View;
use vaersaagod\transmate\models\DeepLSettings;
use vaersaagod\transmate\TransMate;

class DeepLTranslator extends BaseTranslator
{
    // DeepL is particular about this, country codes need to be very specific, and different
    // values are allowed depending on source and target.
    // TODO : Need to do a more thorough deep dive here...
    
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
        $options = $this->config->options;
        
        if (isset($options['context'])) {
            $options['context'] = \Craft::$app->getView()->renderString($options['context']);
        }

        if (!isset($options['tag_handling']) && StringHelper::isHtml($content)) {
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

        $translator = new \DeepL\Translator($this->config->apiKey);
        $result = $translator->translateText($content, $sourceLang, $targetLang, $options);

        return $result->text;
    }
}
