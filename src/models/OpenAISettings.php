<?php

namespace vaersaagod\transmate\models;

use Craft;
use craft\base\Model;

class OpenAISettings extends Model
{
    public string $apiKey = '';
    public string $engine = 'gpt-6.1-sol';

    /**
     * @var int Seconds to wait for a response from OpenAI before giving up.
     */
    public int $timeout = 60;

    /**
     * @deprecated in 1.3.0. Ignored, since current OpenAI reasoning models don't accept a temperature.
     */
    public ?float $temperature = null;

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();

        if ($this->temperature !== null) {
            Craft::$app->getDeprecator()->log('transmate.openai.temperature', 'TransMate’s `translatorConfig.openai.temperature` setting has been deprecated and is ignored. It can be removed from `config/transmate.php`.');
        }
    }

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
