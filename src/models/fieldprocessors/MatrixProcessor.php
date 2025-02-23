<?php

namespace vaersaagod\transmate\models\fieldprocessors;

use Craft;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\base\Model;

use craft\elements\db\EntryQuery;
use craft\enums\PropagationMethod;
use vaersaagod\transmate\translators\TranslatorInterface;
use vaersaagod\transmate\TransMate;

/**
 * @property-read mixed $value
 */
class MatrixProcessor extends Model implements ProcessorInterface
{
    public ?ElementInterface $source = null;
    public ?ElementInterface $target = null;
    public ?FieldInterface $field = null;
    public ?EntryQuery $originalValue = null;
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
        
        $r = [];
        $c = 0;
                 
        /** @var \craft\elements\Entry $block */
        foreach ($this->originalValue->all() as $block) {
            $translatedBlock = TransMate::getInstance()->translate->translateElement($block, $this->source->site, $this->target->site, $translator->toLanguage);
            
            if ($translatedBlock) {
                // If the uid for the translated block is the same, we keep it, if not, create a new.
                if ($block->uid === $translatedBlock->uid) {
                    $id = $block->id;
                } else {
                    $id = 'new:'.++$c;
                }
                
                $r[$id] = [
                    'type' => $block->type->handle,
                    'title' => $translatedBlock->title,
                    'fields' => $translatedBlock->serializedFieldValues
                ];
            }   
        }
        
        $this->setTranslatedValue($r);
    }
}
