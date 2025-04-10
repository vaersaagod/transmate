<?php

namespace vaersaagod\transmate\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    public string $saveMode = 'current'; // draft, current (?)
    public string $resetSlugMode = 'always'; // never, always, new (?)
    public array $excludedFields = [];
    public ?string $disableTranslationProperty = null;
    public ?string $creatorId = null;
    
    public string $translator = '';
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

}
