<?php

namespace vaersaagod\transmate\models\fieldprocessors;

use Craft;
use craft\base\FieldInterface;
use craft\base\Model;
use vaersaagod\transmate\translators\TranslatorInterface;

/**
 * @property-read mixed $value
 * @property-read mixed $translatableValue
 */
class PlainTextProcessor extends Model implements ProcessorInterface
{
    public ?FieldInterface $field = null; // Note to self, $field can be null if the processor is used for native fields (title and alt)
    public ?string $originalValue = null;
    public ?string $translatedValue = null;
    
    
    public function getValue(): mixed
    {
        // TBD: Should it return the original value if not translated...? Probably not.
        return $this->translatedValue;
    }

    public function setTranslatedValue(mixed $translatedValue): void
    {
        $this->translatedValue = $translatedValue;
    }

    public function translate(TranslatorInterface $translator): void
    {
        if ($this->originalValue === null) {
            return;
        }
        
        $this->setTranslatedValue($translator->translate($this->originalValue));
    }
}
