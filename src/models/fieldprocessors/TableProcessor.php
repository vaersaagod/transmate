<?php

namespace vaersaagod\transmate\models\fieldprocessors;

use Craft;
use craft\base\FieldInterface;
use craft\base\Model;
use vaersaagod\transmate\translators\TranslatorInterface;

/**
 * @property-read mixed $value
 */
class TableProcessor extends Model implements ProcessorInterface
{
    public ?FieldInterface $field = null;
    public ?array $originalValue = null;
    public ?array $translatedValue = null;
    
    
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
        
        /** @var \craft\fields\Table $tableField */
        $tableField = $this->field;
        $columnDef = $tableField->columns;
        
        $val = [];
        
        foreach ($this->field->serializeValue($this->originalValue, null) as $row) {
            foreach ($row as $key=>$value) {
                $columnType = isset($columnDef[$key]) ? $columnDef[$key]['type'] : '';
                
                if (!empty($value) && in_array($columnType, ['singleline', 'multiline'])) {
                    $row[$key] = $translator->translate($value);
                }
            }
            $val[] = $row;
        }
        
        $this->setTranslatedValue($val);
    }
}
