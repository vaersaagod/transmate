<?php

namespace vaersaagod\transmate\translators;

use Craft;
use Illuminate\Support\Collection;
use vaersaagod\transmate\TransMate;

class OpenAITranslator extends BaseTranslator implements TranslatorInterface
{
    
    public function translate(string $content, array $params = []): mixed
    {
        // TODO : Things need to come from config passed to the translator
        $client = \OpenAI::client(TransMate::getInstance()->getSettings()->openAIApiKey);
        
        $prompt = 'Translate this text from ' . Craft::$app->getI18n()->getLocaleById($this->fromLanguage)->getDisplayName() . ' to ' . Craft::$app->getI18n()->getLocaleById($this->toLanguage)->getDisplayName() . ', keep html, dont add anything around the result: ' . $content;
        
        $clientParams = [
            'model' => 'gpt-4',
            'temperature' => 0.7,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];
        
        $result = $client->chat()->create($clientParams);
        
        $response = Collection::make($result['choices'] ?? [])->first(static fn(array $choice) => $choice['finish_reason'] === 'stop' && !empty($choice['message']['content'] ?? null));
        
        if (!$response) {
            return null;
        }
        
        $message = trim($response['message']['content']);

        //if (!empty($this->directives)) {
        //    $message = StringHelper::removeLeft($message, $this->directives);
        //    $message = StringHelper::removeRight($message, $this->directives);
        //}
        
        return trim($message) ?: null;
    }
}
