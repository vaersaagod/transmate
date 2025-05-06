<?php

namespace vaersaagod\transmate\models\fieldprocessors;

use Craft;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\base\Model;


use craft\redactor\FieldData;
use vaersaagod\transmate\helpers\TranslateHelper;
use vaersaagod\transmate\translators\TranslatorInterface;
use vaersaagod\transmate\TransMate;

/**
 * @property-read mixed $value
 */
class RedactorProcessor extends Model implements ProcessorInterface
{
    public ?ElementInterface $source = null;
    public ?ElementInterface $target = null;
    public ?FieldInterface $field = null;
    public ?FieldData $originalValue = null;
    public ?string $translatedValue = null;
    
    
    public function getValue(): mixed
    {
        return $this->translatedValue;
    }

    public function setTranslatedValue(mixed $translatedValue): void
    {
        $this->translatedValue = $translatedValue;
    }

    public function translate(TranslatorInterface $translator): void
    {
        if (empty($this->originalValue)) {
            return;
        }

        // Translating link refs here, ie we change site if an entry exists for the target site
        $preppedValue = TranslateHelper::translateRefs($this->originalValue->getRawContent(), $this->source->site, $this->target->site);
        
        $this->setTranslatedValue($translator->translate($preppedValue));
    }
}
