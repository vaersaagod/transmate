<?php

namespace vaersaagod\transmate\models\fieldprocessors;

use Craft;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\base\Model;
use craft\fields\data\LinkData;
use vaersaagod\transmate\translators\TranslatorInterface;

/**
 * @property-read mixed $value
 * @property-read mixed $translatableValue
 */
class LinkProcessor extends Model implements ProcessorInterface
{
    public ?FieldInterface $field = null;
    public ?LinkData $originalValue = null;
    public ?array $translatedValue = null;
    
    
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
        
        $val = $this->field->serializeValue($this->originalValue, null);
        
        if (!empty($val['label'])) {
            $val['label'] = $translator->translate($val['label']);
        }
        
        if (!empty($val['ariaLabel'])) {
            $val['ariaLabel'] = $translator->translate($val['ariaLabel']);
        }
        
        if (!empty($val['title'])) {
            $val['title'] = $translator->translate($val['title']);
        }
        
        // TODO: Should we dive into elements and try to fix links to elements in different sites...? Probably not.
        // Could maybe piggyback on the code for fixing refs (that was added later) 
        
        $this->setTranslatedValue($val);
    }
}
