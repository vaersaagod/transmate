<?php

namespace vaersaagod\transmate\translators;

use Craft;
use Illuminate\Support\Collection;
use vaersaagod\transmate\models\OpenAISettings;
use vaersaagod\transmate\TransMate;

class OpenAITranslator extends BaseTranslator
{
    
    public function __construct(?array $settings=null)
    {
        $this->config = new OpenAISettings($settings);
    }
    
    public function translate(string $content, array $params = []): mixed
    {
        $client = \OpenAI::client($this->config->apiKey);
        
        $prompt = 'Translate this text from ' . Craft::$app->getI18n()->getLocaleById($this->fromLanguage)->getDisplayName() . ' to ' . Craft::$app->getI18n()->getLocaleById($this->toLanguage)->getDisplayName() . ', keep html, dont add anything around the result: ' . $content;
        
        $clientParams = [
            'model' => $this->config->engine,
            'temperature' => $this->config->temperature,
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
