<?php

namespace vaersaagod\transmate\models;

use Craft;
use craft\base\Model;

class OpenAISettings extends Model
{
    public string $apiKey = '';
    public string $engine = 'gpt-4';
    public float $temperature = 0.7;
    
    /**
     * @param $values
     * @param $safeOnly
     * @return void
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        // ...
        
        parent::setAttributes($values, $safeOnly);
    }

}
