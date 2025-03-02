<?php

namespace vaersaagod\transmate\models;

use Craft;
use craft\base\Model;

class DeepLSettings extends Model
{
    public string $apiKey = '';
    public array $options = [];
    
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
