<?php

namespace vaersaagod\transmate\models\fieldprocessors;

use Craft;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\base\Model;

use craft\ckeditor\data\FieldData;

use vaersaagod\transmate\helpers\TranslateHelper;
use vaersaagod\transmate\translators\TranslatorInterface;
use vaersaagod\transmate\TransMate;

/**
 * @property-read mixed $value
 */
class CKEditorProcessor extends Model implements ProcessorInterface
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
        
        $newPieces = [];
        
        foreach ($this->originalValue->getChunks(false) as $chunk) {
            if ($chunk instanceof \craft\ckeditor\data\Entry) {
                $translatedEntry = TransMate::getInstance()->translate->translateElement($chunk->entry, $this->source->site, $this->target->site, $translator->toLanguage);
                
                if ($translatedEntry) {
                    $newPieces[] = '<craft-entry data-entry-id="'.$translatedEntry->id.'">$nbsp;</craft-entry>';
                }
            } elseif ($chunk instanceof \craft\ckeditor\data\Markup) {
                $newPieces[] = $chunk->rawHtml; // think we need to get raw here to avoid parsing of refs with the wrong site?
            }
        }
        
        // This is the full blob of text and entry tags that we need to translate
        $newBlob = $this->field->serializeValue(implode('', $newPieces), null);
        
        // Translating link refs here, ie we change site if an entry exists for the target site
        $newBlob = TranslateHelper::translateRefs($newBlob, $this->source->site, $this->target->site);
        
        $this->setTranslatedValue($translator->translate($newBlob));
    }
}
