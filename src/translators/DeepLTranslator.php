<?php

namespace vaersaagod\transmate\translators;

use vaersaagod\transmate\TransMate;

class DeepLTranslator extends BaseTranslator implements TranslatorInterface
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
    
    public function translate(string $content, array $params = []): mixed
    {
        // TODO : Things need to come from config passed to the translator
        // TODO : Add more options for format etc
        $translator = new \DeepL\Translator(TransMate::getInstance()->getSettings()->deepLApiKey);
        $result = $translator->translateText($content, self::$sourceCountryCodeLUM[$this->fromLanguage] ?? $this->fromLanguage, self::$targetCountryCodeLUM[$this->toLanguage] ?? $this->toLanguage);

        return $result->text;
    }
}
