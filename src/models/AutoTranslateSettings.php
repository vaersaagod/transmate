<?php

namespace vaersaagod\transmate\models;

use Craft;
use craft\base\Model;

class AutoTranslateSettings extends Model
{
    public string $elementType = \craft\elements\Entry::class;
    public string|int $fromSite = '';
    public string|int|array $toSite = '';
    public array $criteria = [];
    
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
