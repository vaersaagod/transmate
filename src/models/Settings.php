<?php

namespace vaersaagod\transmate\models;

use Craft;
use craft\base\Model;

/**
 * AIMate settings
 */
class Settings extends Model
{
    public string $saveMode = 'current'; // draft, current (?)
    public string $resetSlugMode = 'always'; // never, always, new (?)
    public array $excludedFields = [];
    public ?string $disableTranslationProperty = null;
    public ?string $creatorId = null;
    public string $openAIApiKey;
    public string $deepLApiKey;
    

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
